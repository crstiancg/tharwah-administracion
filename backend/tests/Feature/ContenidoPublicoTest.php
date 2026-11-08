<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\Variante;
use App\Support\RutasProtegidas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class ContenidoPublicoTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    private const CLAVE = 'clave-de-la-web';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['services.tienda_web.key' => self::CLAVE]);
    }

    private function producto(string $nombre, ?Categoria $categoria = null): Producto
    {
        return Producto::create([
            'nombre' => $nombre,
            'categoria_id' => ($categoria ?? Categoria::firstOrCreate(['parent_id' => null, 'nombre' => 'Sellantes']))->id,
            'marca_id' => Marca::firstOrCreate(['nombre' => 'Sika'])->id,
            'precio' => 30,
            'activo' => true,
        ]);
    }

    private function variante(Producto $producto, string $sku): Variante
    {
        return $producto->variantes()->create([
            'presentacion' => "Balde {$sku}",
            'unidad_id' => Unidad::firstOrCreate(['nombre' => 'Balde'], ['abreviatura' => 'BLD'])->id,
            'sku' => $sku,
        ]);
    }

    private function contenido(?string $clave = self::CLAVE)
    {
        return $this->getJson('/api/publico/contenido', $clave ? ['X-Api-Key' => $clave] : []);
    }

    /**
     * Guarda y publica una sección como lo haría el panel.
     */
    private function publicar(string $seccion, mixed $contenido): void
    {
        $this->putJson("/api/contenido-web/{$seccion}", ['contenido' => $contenido])->assertOk();
        $this->postJson("/api/contenido-web/{$seccion}/publicar")->assertOk();
    }

    public function test_sin_clave_configurada_no_existe(): void
    {
        config(['services.tienda_web.key' => null]);

        $this->contenido()->assertNotFound();
    }

    public function test_sin_la_clave_correcta_devuelve_401(): void
    {
        $this->contenido(null)->assertUnauthorized();
        $this->contenido('otra')->assertUnauthorized();
    }

    public function test_no_es_una_ruta_con_permiso(): void
    {
        $this->assertFalse(app(RutasProtegidas::class)->todas()->has('publico.contenido'));
    }

    public function test_solo_sale_lo_publicado(): void
    {
        $this->actuarCon('contenido-web.update', 'contenido-web.publicar');
        $this->publicar('aviso', ['texto' => 'Envíos a todo el Perú']);
        $this->putJson('/api/contenido-web/aviso', ['contenido' => ['texto' => 'Borrador a medias']]);
        $this->putJson('/api/contenido-web/preguntas', ['contenido' => [['pregunta' => '¿Sin publicar?', 'respuesta' => 'Sí']]]);

        // Lo que nunca se publicó sale en null: la web sigue con su respaldo.
        $this->contenido()
            ->assertOk()
            ->assertJsonPath('aviso.texto', 'Envíos a todo el Perú')
            ->assertJsonPath('preguntas', null)
            ->assertJsonPath('carrusel', null)
            ->assertJsonPath('nosotros', null);
    }

    public function test_publicar_se_ve_al_momento(): void
    {
        $this->actuarCon('contenido-web.update', 'contenido-web.publicar');
        $this->publicar('aviso', ['texto' => 'Primera']);
        $this->contenido()->assertJsonPath('aviso.texto', 'Primera');

        $this->publicar('aviso', ['texto' => 'Segunda']);
        $this->contenido()->assertJsonPath('aviso.texto', 'Segunda');
    }

    public function test_las_referencias_salen_sin_ids_internos(): void
    {
        $this->actuarCon('contenido-web.update', 'contenido-web.publicar', 'contenido-web.archivos', 'productos-web.update', 'productos-web.publicar');
        $grout = $this->producto('SikaGrout®-212');
        $sika1 = $this->producto('Sika®-1');
        $borrado = $this->producto('Descontinuado');
        $balde = $this->variante($grout, 'GROUT-BLS');
        $siliconas = Categoria::create(['nombre' => 'Siliconas', 'parent_id' => Categoria::firstOrCreate(['parent_id' => null, 'nombre' => 'Sellantes'])->id]);

        // Sika-1 tiene dirección propia publicada; Grout sale con la de su nombre.
        $this->putJson("/api/productos/{$sika1->id}/web", ['web' => [
            'slug' => 'sika-uno', 'resumen' => 'Polvo', 'usos' => [], 'ventajas' => [], 'modo_uso' => [],
            'soluciones' => [], 'destacado' => false, 'nuevo' => false, 'fichas' => [],
        ]]);
        $this->postJson("/api/productos/{$sika1->id}/web/publicar");

        $imagen = $this->postJson('/api/contenido-web/carrusel/archivos', ['archivo' => UploadedFile::fake()->image('obra.jpg', 1200, 600)])->json('id');
        $this->publicar('carrusel', [[
            'etiqueta' => 'Reparar', 'titulo' => 'Repara concreto', 'imagen_id' => $imagen,
            'productos' => [
                ['producto_id' => $grout->id, 'escala' => 1],
                ['producto_id' => $sika1->id, 'escala' => 0.6],
                ['producto_id' => $borrado->id, 'escala' => 0.5],
            ],
            'boton' => ['texto' => 'Ver', 'enlace' => '/c/reparacion'],
        ]]);
        $this->publicar('kits', [[
            'nombre' => 'Anclaje', 'titulo' => 'Ancla fierros', 'descuento' => 5,
            'items' => [['variante_id' => $balde->id, 'cantidad' => 2, 'rol' => 'Rellena']],
        ]]);
        $this->publicar('categorias', [['categoria_id' => $siliconas->id, 'descripcion' => 'Para juntas']]);
        $borrado->delete();

        $respuesta = $this->contenido()
            ->assertOk()
            ->assertJsonPath('carrusel.0.productos', [
                ['slug' => 'sikagrout-212', 'escala' => 1],
                ['slug' => 'sika-uno', 'escala' => 0.6],
            ])
            ->assertJsonPath('carrusel.0.imagen.ancho', 1200)
            ->assertJsonPath('kits.0.items.0', ['slug' => 'sikagrout-212', 'sku' => 'GROUT-BLS', 'cantidad' => 2, 'rol' => 'Rellena'])
            ->assertJsonPath('categorias.0', ['categoria' => 'Siliconas', 'categoria_padre' => 'Sellantes', 'descripcion' => 'Para juntas', 'imagen' => null]);

        $this->assertStringContainsString('/storage/tienda-web/carrusel/', $respuesta->json('carrusel.0.imagen.url'));
        foreach (['imagen_id', 'producto_id', 'variante_id', 'categoria_id'] as $id) {
            $this->assertStringNotContainsString("\"{$id}\"", $respuesta->getContent());
        }
    }

    public function test_los_banners_llevan_a_su_producto_o_a_su_enlace(): void
    {
        $this->actuarCon('contenido-web.update', 'contenido-web.publicar', 'contenido-web.archivos');
        $sikasil = $this->producto('Sikasil®-C');
        $borrado = $this->producto('Descontinuado');
        $subir = fn () => $this->postJson('/api/contenido-web/banners/archivos', ['archivo' => UploadedFile::fake()->image('afiche.jpg', 1860, 1060)])->json('id');

        // Sin producto ni enlace no se puede guardar.
        $this->putJson('/api/contenido-web/banners', ['contenido' => [['imagen_id' => $subir(), 'descripcion' => 'Promo']]])
            ->assertJsonValidationErrors(['contenido.0.producto_id', 'contenido.0.enlace']);

        $this->publicar('banners', [
            ['imagen_id' => $subir(), 'descripcion' => 'Silicona para vidrios', 'producto_id' => $sikasil->id],
            ['imagen_id' => $subir(), 'descripcion' => 'Techos', 'enlace' => '/c/impermeabilizantes'],
            ['imagen_id' => $subir(), 'descripcion' => 'Ya no se vende', 'producto_id' => $borrado->id],
        ]);
        $borrado->delete();

        $this->contenido()
            ->assertJsonCount(2, 'banners')
            ->assertJsonPath('banners.0.producto', 'sikasil-c')
            ->assertJsonPath('banners.0.enlace', null)
            ->assertJsonPath('banners.0.descripcion', 'Silicona para vidrios')
            ->assertJsonPath('banners.0.imagen.ancho', 1860)
            ->assertJsonPath('banners.1.producto', null)
            ->assertJsonPath('banners.1.enlace', '/c/impermeabilizantes');
    }
}
