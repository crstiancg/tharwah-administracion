<?php

namespace Tests\Feature;

use App\Mail\HojaDeReclamacion;
use App\Mail\RespuestaDeReclamacion;
use App\Models\Reclamacion;
use App\Support\RutasProtegidas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class ReclamacionTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    private const CLAVE = 'clave-de-la-web';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.tienda_web.key' => self::CLAVE]);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function hoja(array $cambios = []): array
    {
        return [
            'tipo' => 'reclamo',
            'nombres' => 'Ana',
            'apellidos' => 'Quispe',
            'tipo_documento' => 'DNI',
            'numero_documento' => '45678912',
            'menor_de_edad' => false,
            'email' => 'ana@example.com',
            'bien_tipo' => 'producto',
            'canal' => 'presencial',
            'tienda' => 'Juliaca',
            'descripcion_bien' => 'Sikaflex 1A, cartucho gris',
            'monto' => 38.5,
            'detalle' => 'El cartucho vino reseco.',
            'pedido' => 'Cambio del producto.',
            ...$cambios,
        ];
    }

    private function presentar(array $hoja, ?string $clave = self::CLAVE)
    {
        return $this->postJson('/api/publico/reclamaciones', ['reclamacion' => $hoja], $clave ? ['X-Api-Key' => $clave] : []);
    }

    public function test_sin_la_clave_de_la_web_no_se_registra(): void
    {
        $this->presentar($this->hoja(), null)->assertUnauthorized();
        $this->assertFalse(app(RutasProtegidas::class)->todas()->has('publico.reclamaciones'));
    }

    public function test_registra_la_hoja_con_numero_correlativo_y_envia_la_copia(): void
    {
        Carbon::setTestNow('2026-11-09 15:00:00'); // lunes

        $this->presentar($this->hoja())
            ->assertCreated()
            ->assertJsonPath('codigo', '2026-000001')
            ->assertJsonPath('estado', 'pendiente')
            ->assertJsonPath('proveedor.ruc', config('greenter.company.ruc'))
            // 15 días hábiles: tres semanas.
            ->assertJsonPath('fecha_limite', '2026-11-30');
        $this->presentar($this->hoja(['tipo' => 'queja', 'email' => null, 'direccion' => 'Jr. Lima 123']))
            ->assertJsonPath('codigo', '2026-000002');

        Mail::assertSent(HojaDeReclamacion::class, fn ($m) => $m->hasTo('ana@example.com'));
        Mail::assertSentCount(1);
        // El correo se arma con la hoja, el plazo y los datos del proveedor.
        $correo = (new HojaDeReclamacion(Reclamacion::query()->firstWhere('codigo', '2026-000001')))->render();
        $this->assertStringContainsString('2026-000001', $correo);
        $this->assertStringContainsString('30/11/2026', $correo);
        $this->assertStringContainsString(config('greenter.company.razonSocial'), $correo);
    }

    public function test_exige_lo_minimo_del_reglamento(): void
    {
        // Ni correo ni domicilio.
        $this->presentar($this->hoja(['email' => null]))
            ->assertJsonValidationErrors(['reclamacion.email', 'reclamacion.direccion']);
        // DNI de 8 dígitos; RUC de 11.
        $this->presentar($this->hoja(['numero_documento' => '123']))->assertJsonValidationErrors('reclamacion.numero_documento');
        $this->presentar($this->hoja(['tipo_documento' => 'RUC', 'numero_documento' => '20616230949']))->assertCreated();
        // Menor de edad: con apoderado.
        $this->presentar($this->hoja(['menor_de_edad' => true]))->assertJsonValidationErrors('reclamacion.apoderado');
        $this->presentar($this->hoja(['detalle' => '', 'pedido' => '']))
            ->assertJsonValidationErrors(['reclamacion.detalle', 'reclamacion.pedido']);
        // Lo mismo que filtra la web al escribir: letras en nombres, números donde van números.
        $this->presentar($this->hoja(['nombres' => 'Ana2', 'telefono' => '987-abc', 'monto' => 10.555, 'comprobante_numero' => 'B001 #1']))
            ->assertJsonValidationErrors(['reclamacion.nombres', 'reclamacion.telefono', 'reclamacion.monto', 'reclamacion.comprobante_numero']);
        $this->presentar($this->hoja(['nombres' => "José O'Brien-Núñez", 'telefono' => '+51 987 654 321', 'distrito' => 'J. L. Bustamante y Rivero', 'direccion' => 'Mz. D Lte 4, Av. 28 de Julio #120 - 2.° piso', 'comprobante_numero' => 'B001-00001234']))
            ->assertCreated();
    }

    public function test_se_lista_y_se_responde_una_sola_vez(): void
    {
        $id = $this->presentar($this->hoja())->json('id');
        $this->actuarCon('reclamaciones.responder');

        $this->getJson('/api/reclamaciones?estado=pendiente')->assertOk()->assertJsonPath('data.0.codigo', '2026-000001');
        $this->postJson("/api/reclamaciones/{$id}/respuesta", ['reclamacion' => ['respuesta' => 'Le cambiamos el cartucho en tienda.']])
            ->assertOk()
            ->assertJsonPath('estado', 'respondida')
            ->assertJsonPath('respuesta', 'Le cambiamos el cartucho en tienda.');
        Mail::assertSent(RespuestaDeReclamacion::class, fn ($m) => $m->hasTo('ana@example.com'));
        $this->assertStringContainsString('Le cambiamos el cartucho', (new RespuestaDeReclamacion(Reclamacion::find($id)))->render());

        $this->postJson("/api/reclamaciones/{$id}/respuesta", ['reclamacion' => ['respuesta' => 'Otra respuesta distinta.']])
            ->assertStatus(409);
        $this->assertSame('Le cambiamos el cartucho en tienda.', Reclamacion::find($id)->respuesta);
    }

    public function test_sin_permiso_no_ve_ni_responde(): void
    {
        $id = $this->presentar($this->hoja())->json('id');
        $this->actuarCon();

        $this->getJson('/api/reclamaciones')->assertForbidden();
        $this->postJson("/api/reclamaciones/{$id}/respuesta", ['reclamacion' => ['respuesta' => 'Respuesta de prueba.']])->assertForbidden();
    }
}
