<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class ProductoWebTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function producto(string $nombre): Producto
    {
        return Producto::create([
            'nombre' => $nombre,
            'categoria_id' => Categoria::firstOrCreate(['parent_id' => null, 'nombre' => 'Impermeabilizantes'])->id,
            'marca_id' => Marca::firstOrCreate(['nombre' => 'Sika'])->id,
            'precio' => 30,
            'activo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function web(array $cambios = []): array
    {
        return [
            'slug' => 'sika-1',
            'resumen' => 'Impermeabilizante en polvo para morteros.',
            'usos' => ['Cisternas', 'Sótanos'],
            'ventajas' => ['Fácil de dosificar'],
            'modo_uso' => ['Mezclar con el agua de amasado'],
            'rendimiento' => '1 kg por bolsa de cemento',
            'soluciones' => ['cisternas'],
            'destacado' => true,
            'nuevo' => false,
            'orden' => 1,
            'fichas' => [],
            ...$cambios,
        ];
    }

    private function guardar(Producto $producto, array $web)
    {
        return $this->putJson("/api/productos/{$producto->id}/web", ['web' => $web]);
    }

    public function test_sin_permiso_devuelve_403(): void
    {
        $this->actuarCon();
        $producto = $this->producto('Sika-1');

        $this->getJson("/api/productos/{$producto->id}/web")->assertForbidden();
        $this->guardar($producto, $this->web())->assertForbidden();
        $this->getJson('/api/productos-web')->assertForbidden();
    }

    public function test_guardar_deja_un_borrador_y_no_toca_el_producto(): void
    {
        $this->actuarCon('productos-web.update');
        $producto = $this->producto('Sika-1');

        $this->guardar($producto, $this->web())
            ->assertOk()
            ->assertJsonPath('producto_id', $producto->id)
            ->assertJsonPath('estado', 'sin_publicar')
            ->assertJsonPath('borrador.usos', ['Cisternas', 'Sótanos']);

        // Lo que se vende queda igual.
        $this->assertSame('30.00', $producto->fresh()->precio);
        $this->assertDatabaseHas('productos_web', ['producto_id' => $producto->id, 'slug' => null]);
    }

    public function test_la_direccion_no_puede_ser_la_de_otro_producto(): void
    {
        $this->actuarCon('productos-web.update', 'productos-web.publicar');
        $sika1 = $this->producto('Sika-1');
        $otro = $this->producto('Sika-1 Polvo');
        $this->guardar($sika1, $this->web());

        // Ni la de un borrador de otro (al publicarlo chocarían)…
        $this->guardar($otro, $this->web())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['web.slug']);
        // …ni con mayúsculas o espacios.
        $this->guardar($otro, $this->web(['slug' => 'Sika 1']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['web.slug']);
    }

    public function test_publicar_copia_la_direccion_y_vacia_el_borrador(): void
    {
        $user = $this->actuarCon('productos-web.update', 'productos-web.publicar');
        $producto = $this->producto('Sika-1');
        $this->guardar($producto, $this->web());

        $this->postJson("/api/productos/{$producto->id}/web/publicar")
            ->assertOk()
            ->assertJsonPath('estado', 'publicado')
            ->assertJsonPath('borrador', null)
            ->assertJsonPath('publicado.slug', 'sika-1')
            ->assertJsonPath('publicado_por.id', $user->id);
        $this->assertDatabaseHas('productos_web', ['producto_id' => $producto->id, 'slug' => 'sika-1']);
    }

    public function test_quien_redacta_no_publica(): void
    {
        $this->actuarCon('productos-web.update');
        $producto = $this->producto('Sika-1');
        $this->guardar($producto, $this->web());

        $this->postJson("/api/productos/{$producto->id}/web/publicar")->assertForbidden();
    }

    public function test_las_fichas_son_pdf_de_ese_producto(): void
    {
        $this->actuarCon('productos-web.update', 'productos-web.archivos');
        $producto = $this->producto('Sika-1');
        $otro = $this->producto('Sikadur-32');

        $this->postJson("/api/productos/{$producto->id}/web/archivos", ['archivo' => UploadedFile::fake()->image('foto.jpg')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
        $ficha = $this->postJson("/api/productos/{$producto->id}/web/archivos", [
            'archivo' => UploadedFile::fake()->create('ficha.pdf', 200, 'application/pdf'),
        ])->assertCreated()->json('id');

        $this->guardar($otro, $this->web(['slug' => 'sikadur-32', 'fichas' => [['titulo' => 'Ficha técnica', 'archivo_id' => $ficha]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['web.fichas.0.archivo_id']);
        $this->guardar($producto, $this->web(['fichas' => [['titulo' => 'Ficha técnica', 'archivo_id' => $ficha]]]))
            ->assertOk()
            ->assertJsonCount(1, 'archivos');
    }

    public function test_la_lista_muestra_que_falta(): void
    {
        $this->actuarCon('productos-web.update', 'productos-web.publicar');
        $publicado = $this->producto('Sika-1');
        $this->producto('Sikadur-32');
        $this->guardar($publicado, $this->web());
        $this->postJson("/api/productos/{$publicado->id}/web/publicar");

        $this->getJson('/api/productos-web?rowsPerPage=10&estado=vacio')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.nombre', 'Sikadur-32')
            ->assertJsonPath('data.0.slug', 'sikadur-32');
        $this->getJson('/api/productos-web?rowsPerPage=10&estado=publicado')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.estado', 'publicado');
    }
}
