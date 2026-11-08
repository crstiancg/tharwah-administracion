<?php

namespace Tests\Feature;

use App\Http\Controllers\CatalogoPublicoController;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\Stock;
use App\Models\Unidad;
use App\Models\Variante;
use App\Support\RutasProtegidas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class CatalogoPublicoTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    private const CLAVE = 'clave-de-la-web';

    private Sede $tacna;

    private Sede $lima;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tacna = Sede::create(['nombre' => 'Tacna', 'activo' => true]);
        $this->lima = Sede::create(['nombre' => 'Lima', 'activo' => true]);
        config([
            'services.tienda_web.key' => self::CLAVE,
            'services.tienda_web.sede_id' => $this->tacna->id,
            'services.tienda_web.pocas_unidades' => 5,
        ]);
    }

    private function producto(string $nombre, bool $activo = true): Producto
    {
        $sellantes = Categoria::firstOrCreate(['parent_id' => null, 'nombre' => 'Sellantes y Adhesivos']);

        return Producto::create([
            'nombre' => $nombre,
            'categoria_id' => Categoria::firstOrCreate(['parent_id' => $sellantes->id, 'nombre' => 'Siliconas'])->id,
            'marca_id' => Marca::firstOrCreate(['nombre' => 'Sika'])->id,
            'descripcion' => '<p>Sellador</p><script>alert(1)</script>',
            'precio' => 30,
            'activo' => $activo,
        ]);
    }

    /**
     * Presentación habilitada en la sede con esa cantidad (como la deja el
     * formulario de productos más las entradas).
     */
    private function presentacion(Producto $producto, string $sku, Sede $sede, float $cantidad, ?float $precioSede = null): Variante
    {
        $variante = $producto->variantes()->create([
            'presentacion' => "Cartucho {$sku}",
            'unidad_id' => Unidad::firstOrCreate(['nombre' => 'Cartucho'], ['abreviatura' => 'CRT'])->id,
            'sku' => $sku,
            'precio_mayor' => 20,
        ]);
        $stock = new Stock;
        $stock->forceFill([
            'variante_id' => $variante->id,
            'sede_id' => $sede->id,
            'cantidad' => $cantidad,
            'activo' => true,
            'precio' => $precioSede,
            'stock_minimo' => 0,
        ])->save();

        return $variante;
    }

    private function catalogo(?string $clave = self::CLAVE)
    {
        return $this->getJson('/api/publico/catalogo', $clave ? ['X-Api-Key' => $clave] : []);
    }

    public function test_sin_clave_configurada_el_catalogo_no_existe(): void
    {
        config(['services.tienda_web.key' => null]);

        $this->catalogo()->assertNotFound();
    }

    public function test_sin_la_clave_correcta_devuelve_401(): void
    {
        $this->catalogo(null)->assertUnauthorized();
        $this->catalogo('otra')->assertUnauthorized();
    }

    public function test_no_es_una_ruta_con_permiso(): void
    {
        $this->assertFalse(app(RutasProtegidas::class)->todas()->has('publico.catalogo'));
    }

    public function test_devuelve_precio_y_disponibilidad_de_la_sede_de_la_tienda(): void
    {
        $sikasil = $this->producto('Sikasil');
        $this->presentacion($sikasil, 'SIL-MUCHO', $this->tacna, 40, precioSede: 28.5);
        $this->presentacion($sikasil, 'SIL-POCO', $this->tacna, 5);
        $this->presentacion($sikasil, 'SIL-NADA', $this->tacna, 0);
        // Sólo se vende en Lima: no sale.
        $this->presentacion($sikasil, 'SIL-LIMA', $this->lima, 100);
        // Inactivo: no sale.
        $this->presentacion($this->producto('Descontinuado', activo: false), 'OLD', $this->tacna, 10);

        $respuesta = $this->catalogo()
            ->assertOk()
            ->assertJsonPath('sede', 'Tacna')
            ->assertJsonCount(1, 'productos')
            ->assertJsonPath('productos.0.nombre', 'Sikasil')
            ->assertJsonPath('productos.0.categoria', 'Siliconas')
            ->assertJsonPath('productos.0.categoria_padre', 'Sellantes y Adhesivos')
            ->assertJsonPath('productos.0.descripcion', '<p>Sellador</p>')
            ->assertJsonCount(3, 'productos.0.presentaciones')
            ->assertJsonPath('productos.0.presentaciones.0.sku', 'SIL-MUCHO')
            ->assertJsonPath('productos.0.presentaciones.0.precio', '28.50')
            ->assertJsonPath('productos.0.presentaciones.0.disponibilidad', 'disponible')
            ->assertJsonPath('productos.0.presentaciones.1.precio', '30.00')
            ->assertJsonPath('productos.0.presentaciones.1.disponibilidad', 'pocas')
            ->assertJsonPath('productos.0.presentaciones.2.disponibilidad', 'agotado');

        // Lo interno no se publica.
        $this->assertArrayNotHasKey('id', $respuesta->json('productos.0'));
        $presentacion = $respuesta->json('productos.0.presentaciones.0');
        foreach (['id', 'stock', 'precio_mayor', 'costo_promedio', 'codigo_barras'] as $campo) {
            $this->assertArrayNotHasKey($campo, $presentacion);
        }
    }

    public function test_incluye_solo_el_contenido_web_publicado_y_su_direccion(): void
    {
        Storage::fake('public');
        $this->actuarCon('productos-web.update', 'productos-web.publicar', 'productos-web.archivos');
        $sikasil = $this->producto('Sikasil®-C');
        $this->presentacion($sikasil, 'SIL', $this->tacna, 40);
        $this->presentacion($this->producto('Sanisil'), 'SAN', $this->tacna, 40);

        // Sin publicar: sale con la dirección de su nombre y sin bloque web.
        $this->catalogo()
            ->assertJsonPath('productos.1.slug', 'sikasil-c')
            ->assertJsonPath('productos.1.web', null);

        $ficha = $this->postJson("/api/productos/{$sikasil->id}/web/archivos", [
            'archivo' => UploadedFile::fake()->create('ficha.pdf', 100, 'application/pdf'),
        ])->json('id');
        $this->putJson("/api/productos/{$sikasil->id}/web", ['web' => [
            'slug' => 'sikasil-c-neutra', 'resumen' => 'Silicona neutra', 'usos' => ['Vidrios'], 'ventajas' => [],
            'modo_uso' => [], 'soluciones' => [], 'destacado' => true, 'nuevo' => false,
            'fichas' => [['titulo' => 'Ficha técnica', 'archivo_id' => $ficha]],
        ]])->assertOk();
        $this->postJson("/api/productos/{$sikasil->id}/web/publicar")->assertOk();

        // Publicar se ve al momento, aunque el catálogo estuviera guardado.
        $web = $this->catalogo()
            ->assertJsonPath('productos.1.slug', 'sikasil-c-neutra')
            ->assertJsonPath('productos.1.web.resumen', 'Silicona neutra')
            ->assertJsonPath('productos.1.web.usos', ['Vidrios'])
            ->assertJsonPath('productos.1.web.fichas.0.titulo', 'Ficha técnica')
            ->json('productos.1.web');
        $this->assertArrayNotHasKey('slug', $web);
        $this->assertStringEndsWith('.pdf', $web['fichas'][0]['url']);
    }

    public function test_los_complementos_salen_por_su_direccion(): void
    {
        $this->actuarCon('productos-web.update', 'productos-web.publicar');
        $sikasil = $this->producto('Sikasil');
        $pistola = $this->producto('Pistola calafateadora');
        $sinVenta = $this->producto('Sin venta en Tacna');
        $this->presentacion($sikasil, 'SIL', $this->tacna, 40);
        $this->presentacion($pistola, 'PIS', $this->tacna, 40);
        $web = [
            'slug' => 'sikasil', 'resumen' => 'Silicona', 'usos' => [], 'ventajas' => [], 'modo_uso' => [],
            'soluciones' => [], 'destacado' => false, 'nuevo' => false, 'fichas' => [],
            'razones' => ['Seca rápido'], 'especificaciones' => [['etiqueta' => 'Base', 'valor' => 'Silicona']],
            'complementos' => [$pistola->id, $sinVenta->id], 'etiquetas' => ['silicon'],
        ];
        $this->putJson("/api/productos/{$sikasil->id}/web", ['web' => $web])->assertOk();
        $this->postJson("/api/productos/{$sikasil->id}/web/publicar")->assertOk();
        // La pistola sale por la dirección de su nombre; lo que no se vende, no.
        $this->catalogo()
            ->assertJsonPath('productos.1.slug', 'sikasil')
            ->assertJsonPath('productos.1.web.resumen', 'Silicona')
            ->assertJsonPath('productos.1.web.razones', ['Seca rápido'])
            ->assertJsonPath('productos.1.web.complementos', ['pistola-calafateadora']);
    }

    public function test_un_producto_no_puede_ser_su_propio_complemento(): void
    {
        $this->actuarCon('productos-web.update');
        $sikasil = $this->producto('Sikasil');

        $this->putJson("/api/productos/{$sikasil->id}/web", ['web' => [
            'slug' => 'sikasil', 'resumen' => 'Silicona', 'usos' => [], 'ventajas' => [], 'modo_uso' => [],
            'soluciones' => [], 'destacado' => false, 'nuevo' => false, 'fichas' => [], 'complementos' => [$sikasil->id],
        ]])->assertJsonValidationErrors('web.complementos.0');
    }

    public function test_reutiliza_el_catalogo_armado_durante_un_minuto(): void
    {
        $sikasil = $this->producto('Sikasil');
        $this->presentacion($sikasil, 'SIL', $this->tacna, 40);
        $this->catalogo()->assertJsonPath('productos.0.presentaciones.0.precio', '30.00');

        $sikasil->update(['precio' => 35]);
        $this->catalogo()->assertJsonPath('productos.0.presentaciones.0.precio', '30.00');

        $this->travel(CatalogoPublicoController::CACHE_SEGUNDOS + 1)->seconds();
        $this->catalogo()->assertJsonPath('productos.0.presentaciones.0.precio', '35.00');
    }
}
