<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoWeb;
use App\Models\Sede;
use App\Models\Variante;
use App\Services\Precios;
use App\Support\HtmlSeguro;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Catálogo para la tienda web: sólo lectura y sin sesión. Lo pide el servidor
 * de la web (nunca el navegador) con la clave de `services.tienda_web.key`
 * (la revisa App\Http\Middleware\ClaveTiendaWeb).
 *
 * Sale sólo lo que la web publica: precio de hoy y disponibilidad en la sede
 * de la tienda. Nada de costos, lotes, precio por mayor ni cantidades exactas.
 */
class CatalogoPublicoController extends Controller
{
    public const DISPONIBLE = 'disponible';

    public const POCAS = 'pocas';

    public const AGOTADO = 'agotado';

    /**
     * Segundos que se reutiliza el catálogo armado: por más que la web lo pida
     * seguido, la base de datos lo arma una vez por minuto.
     */
    public const CACHE_SEGUNDOS = 60;

    /** Se olvida al publicar el contenido web de un producto (sale al toque). */
    public const CACHE_CLAVE = 'tienda-web:catalogo';

    public function index(Precios $precios): JsonResponse
    {
        return response()->json(
            Cache::remember(self::CACHE_CLAVE, self::CACHE_SEGUNDOS, fn () => $this->catalogo($precios, config('services.tienda_web'))),
        );
    }

    private function catalogo(Precios $precios, array $config): array
    {
        // Sin sede configurada, la primera (como los usuarios nuevos).
        $sede = Sede::query()
            ->when($config['sede_id'], fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->firstOrFail(['id', 'nombre']);
        $disco = Storage::disk('public');
        // Con medidas: la web reserva el espacio de la foto antes de cargarla.
        $foto = fn ($a) => ['url' => $disco->url($a->ruta), 'ancho' => $a->ancho, 'alto' => $a->alto];

        $productos = Producto::query()
            ->where('activo', true)
            ->whereHas('variantes', fn ($v) => $v->habilitadaEnSede($sede->id))
            ->with([
                'categoria:id,nombre,parent_id',
                'categoria.padre:id,nombre',
                'marca:id,nombre',
                'archivos',
                'web.archivos',
                'variantes' => fn ($q) => $q->habilitadaEnSede($sede->id)->conStockDeSede($sede->id)->conStockVencidoDeSede($sede->id)->orderBy('variantes.id'),
                'variantes.unidad:id,nombre',
                'variantes.color:id,nombre,hexadecimal',
                'variantes.portada',
            ])
            ->orderBy('nombre')
            ->get();
        // Los complementos se citan por id: salen por la dirección de cada uno.
        $slugs = $productos->mapWithKeys(fn (Producto $p) => [$p->id => ProductoWeb::slugPara($p)]);

        return [
            'sede' => $sede->nombre,
            // Sin ids internos: la web identifica cada presentación por su SKU.
            'productos' => $productos->map(fn (Producto $p) => [
                'nombre' => $p->nombre,
                'slug' => $slugs[$p->id],
                'descripcion' => HtmlSeguro::limpiar($p->descripcion),
                'marca' => $p->marca?->nombre,
                'categoria' => $p->categoria?->nombre,
                // La raíz, si es subcategoría ("Sellantes y Adhesivos" de "Siliconas").
                'categoria_padre' => $p->categoria?->padre?->nombre,
                'fotos' => $p->archivos->map($foto)->all(),
                'presentaciones' => $p->variantes->map(function (Variante $v) use ($p, $precios, $config, $foto) {
                    $vigente = $precios->vigente($v->precioBase((float) $p->precio), $p->id, $p->categoria_id, $v->id);

                    return [
                        'sku' => $v->sku,
                        'presentacion' => $v->presentacion,
                        'unidad' => $v->unidad?->nombre,
                        'color' => $v->color?->only(['nombre', 'hexadecimal']),
                        'precio' => $vigente['precio'],
                        'precio_lista' => $vigente['precio_lista'],
                        'oferta' => $vigente['oferta']?->etiqueta(),
                        'disponibilidad' => $this->disponibilidad($v->stockVendible(), (float) $config['pocas_unidades']),
                        'foto' => $v->portada ? $foto($v->portada) : null,
                    ];
                })->all(),
                // Cómo se presenta en la web (usos, ventajas…): sólo lo publicado.
                'web' => $p->web?->publicado ? $this->web($p->web, $p->web->publicado, $slugs) : null,
            ])->all(),
        ];
    }

    /**
     * El contenido web de un producto sin ids: las fichas como URL y los
     * complementos por su dirección (los que no se venden no salen).
     *
     * @param  array<string, mixed>  $datos
     * @param  Collection<int, string>  $slugs
     * @return array<string, mixed>
     */
    private function web(ProductoWeb $web, array $datos, Collection $slugs): array
    {
        $disco = Storage::disk('public');
        $archivos = $web->archivos->keyBy('id');

        return [
            ...collect($datos)->except(['slug', 'fichas', 'complementos'])->all(),
            'fichas' => collect($datos['fichas'] ?? [])
                ->filter(fn (array $ficha) => $archivos->has($ficha['archivo_id']))
                ->map(fn (array $ficha) => [
                    'titulo' => $ficha['titulo'],
                    'tipo' => $ficha['tipo'] ?? null,
                    'url' => $disco->url($archivos[$ficha['archivo_id']]->ruta),
                ])
                ->values()
                ->all(),
            'complementos' => collect($datos['complementos'] ?? [])
                ->map(fn (int $id) => $slugs->get($id))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * Lo que ve el cliente en vez de la cantidad: con muy poquitas, "pocas".
     */
    private function disponibilidad(float $stock, float $pocas): string
    {
        return match (true) {
            $stock <= 0 => self::AGOTADO,
            $stock <= $pocas => self::POCAS,
            default => self::DISPONIBLE,
        };
    }
}
