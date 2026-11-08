<?php

namespace App\Http\Controllers;

use App\Models\Archivo;
use App\Models\Categoria;
use App\Models\ContenidoWeb;
use App\Models\Producto;
use App\Models\ProductoWeb;
use App\Models\Variante;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Contenido publicado de la tienda web (aviso, carrusel, preguntas…): sólo
 * lectura y sin sesión, con la clave de la tienda web (ClaveTiendaWeb).
 *
 * Sale sólo lo publicado y sin ids internos, igual que el catálogo: los
 * productos por su dirección web (slug), las presentaciones por su SKU, las
 * categorías por su nombre y las imágenes como URL. Lo que se borró del
 * sistema simplemente no sale.
 */
class ContenidoPublicoController extends Controller
{
    /** Se olvida al publicar (sale al toque). */
    public const CACHE_CLAVE = 'tienda-web:contenido';

    public function index(): JsonResponse
    {
        return response()->json(
            Cache::remember(self::CACHE_CLAVE, CatalogoPublicoController::CACHE_SEGUNDOS, fn () => $this->contenido()),
        );
    }

    /**
     * Una sección que nunca se publicó sale en null (no como lista vacía):
     * así la web sabe que debe seguir con su contenido de respaldo.
     *
     * @return array<string, mixed>
     */
    private function contenido(): array
    {
        $secciones = ContenidoWeb::query()->whereNotNull('publicado')->with('archivos')->get()->keyBy('clave');
        $datos = fn (string $clave) => $secciones->get($clave)?->publicado;

        // Todas las referencias de una vez (no una consulta por diapositiva).
        $productos = Producto::query()
            ->with('web:id,producto_id,slug')
            ->findMany([
                ...collect($datos('carrusel'))->flatMap(fn ($s) => array_column($s['productos'], 'producto_id')),
                ...collect($datos('campanas'))->flatMap(fn ($c) => $c['productos']),
                ...array_filter(array_column($datos('banners') ?? [], 'producto_id')),
            ])
            ->keyBy('id');
        $variantes = Variante::query()
            ->with('producto.web:id,producto_id,slug')
            ->findMany(collect($datos('kits'))->flatMap(fn ($k) => array_column($k['items'], 'variante_id')))
            ->keyBy('id');
        $categorias = Categoria::query()
            ->with('padre:id,nombre')
            ->findMany(array_column($datos('categorias') ?? [], 'categoria_id'))
            ->keyBy('id');

        $imagen = function (?int $id, ContenidoWeb $seccion): ?array {
            $archivo = $id ? $seccion->archivos->firstWhere('id', $id) : null;

            return $archivo ? $this->imagen($archivo) : null;
        };
        $slug = fn (int $id) => $productos->has($id) ? ProductoWeb::slugPara($productos[$id]) : null;

        return [
            'aviso' => $datos('aviso'),
            'preguntas' => $datos('preguntas'),
            'texto_tienda' => $datos('texto_tienda'),
            'nosotros' => $datos('nosotros'),
            'carrusel' => $this->lista($datos('carrusel'), fn (array $s) => [
                ...collect($s)->except(['imagen_id', 'productos'])->all(),
                'imagen' => $imagen($s['imagen_id'], $secciones['carrusel']),
                'productos' => collect($s['productos'])
                    ->filter(fn (array $p) => $slug($p['producto_id']))
                    ->map(fn (array $p) => ['slug' => $slug($p['producto_id']), 'escala' => $p['escala']])
                    ->values()
                    ->all(),
            ]),
            'campanas' => $this->lista($datos('campanas'), fn (array $c) => [
                ...collect($c)->except('productos')->all(),
                'productos' => collect($c['productos'])->map($slug)->filter()->values()->all(),
            ]),
            'kits' => $this->lista($datos('kits'), fn (array $k) => [
                ...collect($k)->except('items')->all(),
                'items' => collect($k['items'])
                    ->filter(fn (array $i) => $variantes->has($i['variante_id']))
                    ->map(fn (array $i) => [
                        'slug' => ProductoWeb::slugPara($variantes[$i['variante_id']]->producto),
                        'sku' => $variantes[$i['variante_id']]->sku,
                        'cantidad' => $i['cantidad'],
                        'rol' => $i['rol'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ]),
            'categorias' => $this->lista($datos('categorias'), fn (array $c) => $categorias->has($c['categoria_id']) ? [
                'categoria' => $categorias[$c['categoria_id']]->nombre,
                'categoria_padre' => $categorias[$c['categoria_id']]->padre?->nombre,
                'descripcion' => $c['descripcion'] ?? null,
                'imagen' => $imagen($c['imagen_id'] ?? null, $secciones['categorias']),
            ] : null),
            'soluciones' => $this->lista($datos('soluciones'), fn (array $s) => [
                ...collect($s)->except('imagen_id')->all(),
                'imagen' => $imagen($s['imagen_id'] ?? null, $secciones['soluciones']),
            ]),
            // Sin imagen o con el producto borrado (y sin otro enlace), no sale.
            'banners' => $this->lista($datos('banners'), function (array $b) use ($imagen, $slug, $secciones) {
                $foto = $imagen($b['imagen_id'], $secciones['banners']);
                $producto = isset($b['producto_id']) ? $slug($b['producto_id']) : null;
                $enlace = $b['enlace'] ?? null;

                return $foto && ($producto || $enlace) ? [
                    'imagen' => $foto,
                    'descripcion' => $b['descripcion'],
                    'producto' => $producto,
                    'enlace' => $producto ? null : $enlace,
                ] : null;
            }),
        ];
    }

    /**
     * Cada ítem traducido; los que quedan sin sentido (null) no salen. Una
     * sección sin publicar sigue en null.
     *
     * @param  array<int, array<string, mixed>>|null  $items
     * @param  callable(array<string, mixed>): (array<string, mixed>|null)  $traducir
     * @return array<int, array<string, mixed>>|null
     */
    private function lista(?array $items, callable $traducir): ?array
    {
        return $items === null ? null : (new Collection($items))->map($traducir)->filter()->values()->all();
    }

    /**
     * Con medidas, como las fotos del catálogo: la web reserva el espacio antes de cargarla.
     *
     * @return array{url: string, ancho: int|null, alto: int|null}
     */
    private function imagen(Archivo $archivo): array
    {
        return [
            'url' => Storage::disk('public')->url($archivo->ruta),
            'ancho' => $archivo->ancho,
            'alto' => $archivo->alto,
        ];
    }
}
