<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ContenidoWeb;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class ContenidoWebTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    private const PREGUNTAS = [
        ['pregunta' => '¿Hacen envíos?', 'respuesta' => 'Sí, a todo el Perú.'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function producto(string $nombre): Producto
    {
        return Producto::create([
            'nombre' => $nombre,
            'categoria_id' => Categoria::firstOrCreate(['parent_id' => null, 'nombre' => 'Sellantes'])->id,
            'marca_id' => Marca::firstOrCreate(['nombre' => 'Sika'])->id,
            'precio' => 30,
            'activo' => true,
        ]);
    }

    private function guardar(string $clave, mixed $contenido)
    {
        return $this->putJson("/api/contenido-web/{$clave}", ['contenido' => $contenido]);
    }

    private function subir(string $clave, UploadedFile $archivo)
    {
        return $this->postJson("/api/contenido-web/{$clave}/archivos", ['archivo' => $archivo]);
    }

    /**
     * @return array<string, mixed>
     */
    private function diapositiva(int $imagenId, Producto $producto): array
    {
        return [
            'etiqueta' => 'Sellar',
            'titulo' => 'Juntas selladas',
            'texto' => 'Siliconas para baños y cocinas.',
            'imagen_id' => $imagenId,
            'productos' => [['producto_id' => $producto->id, 'escala' => 0.8]],
            'boton' => ['texto' => 'Ver sellantes', 'enlace' => '/c/sellantes'],
        ];
    }

    public function test_sin_permiso_devuelve_403(): void
    {
        $this->actuarCon();

        $this->getJson('/api/contenido-web/preguntas')->assertForbidden();
        $this->guardar('preguntas', self::PREGUNTAS)->assertForbidden();
    }

    public function test_una_seccion_que_no_existe_da_404(): void
    {
        $this->actuarCon('contenido-web.show');

        $this->getJson('/api/contenido-web/inventada')->assertNotFound();
    }

    public function test_quien_redacta_ve_la_lista_y_la_seccion_pero_no_publica(): void
    {
        $this->actuarCon('contenido-web.update');

        $this->getJson('/api/contenido-web')
            ->assertOk()
            ->assertJsonCount(count(ContenidoWeb::CLAVES), 'data')
            ->assertJsonPath('data.0.estado', 'vacio');
        $this->getJson('/api/contenido-web/preguntas')->assertOk()->assertJsonPath('estado', 'vacio');
        $this->guardar('preguntas', self::PREGUNTAS)->assertOk();
        $this->postJson('/api/contenido-web/preguntas/publicar')->assertForbidden();
    }

    public function test_guardar_deja_un_borrador_sin_tocar_lo_publicado(): void
    {
        $user = $this->actuarCon('contenido-web.update');

        $this->guardar('preguntas', self::PREGUNTAS)
            ->assertOk()
            ->assertJsonPath('estado', 'sin_publicar')
            ->assertJsonPath('borrador.0.pregunta', '¿Hacen envíos?')
            ->assertJsonPath('publicado', null)
            ->assertJsonPath('editado_por.id', $user->id);
    }

    public function test_guarda_solo_los_campos_de_la_seccion(): void
    {
        $this->actuarCon('contenido-web.update');

        $this->guardar('aviso', ['texto' => 'Envíos a todo el Perú', 'script' => '<script>'])
            ->assertOk()
            ->assertJsonPath('borrador', ['texto' => 'Envíos a todo el Perú']);
    }

    public function test_valida_la_forma_de_cada_seccion(): void
    {
        $this->actuarCon('contenido-web.update');

        $this->guardar('aviso', ['texto' => str_repeat('a', 121)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contenido.texto']);
        $this->guardar('preguntas', [['pregunta' => '¿Sin respuesta?']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contenido.0.respuesta']);
        $this->guardar('campanas', [[
            'titulo' => 'Repara', 'boton' => 'Ver', 'enlace' => 'javascript:alert(1)', 'tono' => 'rojo', 'productos' => [999],
        ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contenido.0.enlace', 'contenido.0.tono', 'contenido.0.productos.0']);
        $this->guardar('soluciones', [
            ['clave' => 'techos', 'nombre' => 'Techos', 'resumen' => 'Goteras', 'pasos' => []],
            ['clave' => 'techos', 'nombre' => 'Otra', 'resumen' => 'Igual', 'pasos' => []],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contenido.1.clave']);
    }

    public function test_un_producto_puede_ir_en_varias_diapositivas(): void
    {
        $this->actuarCon('contenido-web.update', 'contenido-web.archivos');
        $grout = $this->producto('SikaGrout-212');
        $imagen = $this->subir('carrusel', UploadedFile::fake()->image('obra.jpg', 1200, 600))->json('id');

        $this->guardar('carrusel', [$this->diapositiva($imagen, $grout), $this->diapositiva($imagen, $grout)])->assertOk();
    }

    public function test_el_segundo_boton_es_opcional_pero_necesita_a_donde_ir(): void
    {
        $this->actuarCon('contenido-web.update', 'contenido-web.archivos');
        $grout = $this->producto('SikaGrout-212');
        $imagen = $this->subir('carrusel', UploadedFile::fake()->image('obra.jpg'))->json('id');
        $diapositiva = $this->diapositiva($imagen, $grout);

        $this->guardar('carrusel', [[...$diapositiva, 'boton_secundario' => ['texto' => 'Cotizar']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contenido.0.boton_secundario.enlace']);
        $this->guardar('carrusel', [[...$diapositiva, 'boton_secundario' => ['texto' => 'Cotizar', 'whatsapp' => 'Hola, quiero cotizar.']]])
            ->assertOk()
            ->assertJsonPath('borrador.0.boton_secundario.whatsapp', 'Hola, quiero cotizar.');
    }

    public function test_publicar_pasa_el_borrador_a_lo_publicado(): void
    {
        $user = $this->actuarCon('contenido-web.update', 'contenido-web.publicar');
        $this->guardar('preguntas', self::PREGUNTAS);

        $this->postJson('/api/contenido-web/preguntas/publicar')
            ->assertOk()
            ->assertJsonPath('estado', 'publicado')
            ->assertJsonPath('borrador', null)
            ->assertJsonPath('publicado.0.respuesta', 'Sí, a todo el Perú.')
            ->assertJsonPath('publicado_por.id', $user->id);
    }

    public function test_publicar_sin_cambios_devuelve_409(): void
    {
        $this->actuarCon('contenido-web.publicar');

        $this->postJson('/api/contenido-web/preguntas/publicar')->assertConflict();
    }

    public function test_descartar_vuelve_a_lo_publicado(): void
    {
        $this->actuarCon('contenido-web.update', 'contenido-web.publicar', 'contenido-web.descartar');
        $this->guardar('aviso', ['texto' => 'Primera versión']);
        $this->postJson('/api/contenido-web/aviso/publicar');
        $this->guardar('aviso', ['texto' => 'Segunda versión'])->assertJsonPath('estado', 'con_cambios');

        $this->deleteJson('/api/contenido-web/aviso/borrador')
            ->assertOk()
            ->assertJsonPath('estado', 'publicado')
            ->assertJsonPath('publicado.texto', 'Primera versión');
    }

    public function test_solo_acepta_imagenes_de_esa_seccion(): void
    {
        $this->actuarCon('contenido-web.update', 'contenido-web.archivos');
        $grout = $this->producto('SikaGrout-212');
        $deOtraSeccion = $this->subir('categorias', UploadedFile::fake()->image('foto.jpg'))->assertCreated()->json('id');

        $this->subir('carrusel', UploadedFile::fake()->create('ficha.pdf', 10, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
        $this->guardar('carrusel', [$this->diapositiva($deOtraSeccion, $grout)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contenido.0.imagen_id']);
    }

    public function test_borra_las_imagenes_que_ya_no_usa_nadie(): void
    {
        $this->actuarCon('contenido-web.update', 'contenido-web.archivos', 'contenido-web.publicar', 'contenido-web.descartar');
        $grout = $this->producto('SikaGrout-212');
        $publicada = $this->subir('carrusel', UploadedFile::fake()->image('a.jpg'))->json('id');
        $this->guardar('carrusel', [$this->diapositiva($publicada, $grout)]);
        $this->postJson('/api/contenido-web/carrusel/publicar');

        // El borrador usa otra: la publicada se conserva (la sigue mostrando la web).
        $nueva = $this->subir('carrusel', UploadedFile::fake()->image('b.jpg'))->json('id');
        $sinUsar = $this->subir('carrusel', UploadedFile::fake()->image('c.jpg'))->json('id');
        $this->guardar('carrusel', [$this->diapositiva($nueva, $grout)])
            ->assertJsonCount(2, 'archivos');
        $this->assertDatabaseMissing('archivos', ['id' => $sinUsar]);

        // Al descartar, la del borrador sobra.
        $this->deleteJson('/api/contenido-web/carrusel/borrador')->assertJsonCount(1, 'archivos');
        $this->assertDatabaseHas('archivos', ['id' => $publicada]);
        $this->assertDatabaseMissing('archivos', ['id' => $nueva]);
    }
}
