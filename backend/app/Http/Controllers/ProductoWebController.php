<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarProductoWebRequest;
use App\Http\Requests\SubirArchivoWebRequest;
use App\Http\Resources\ArchivoResource;
use App\Http\Resources\ProductoWebResource;
use App\Http\Resources\PublicacionWebResource;
use App\Models\Producto;
use App\Models\ProductoWeb;
use App\Services\PublicacionWeb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * Contenido web de cada producto (usos, ventajas, fichas…) para el panel de
 * edición. No toca nada de lo que se vende: precio, presentaciones y stock se
 * editan en el producto.
 */
class ProductoWebController extends Controller
{
    public function __construct(private PublicacionWeb $publicacion) {}

    public function index(Request $request): JsonResponse
    {
        $query = Producto::query()->with(['categoria:id,nombre', 'web', 'portada']);

        // Qué falta: los que nunca se editaron o los que tienen cambios sin publicar.
        match ($request->input('estado')) {
            PublicacionWebResource::VACIO => $query->whereDoesntHave('web', fn ($w) => $w->where(fn ($q) => $q->whereNotNull('borrador')->orWhereNotNull('publicado'))),
            PublicacionWebResource::SIN_PUBLICAR => $query->whereHas('web', fn ($w) => $w->whereNull('publicado')->whereNotNull('borrador')),
            PublicacionWebResource::CON_CAMBIOS => $query->whereHas('web', fn ($w) => $w->whereNotNull('publicado')->whereNotNull('borrador')),
            PublicacionWebResource::PUBLICADO => $query->whereHas('web', fn ($w) => $w->whereNotNull('publicado')->whereNull('borrador')),
            default => null,
        };

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => 'nombre']);
        }

        return $this->generateViewSetList(
            $request,
            $query,
            ['activo', 'categoria_id'],
            ['nombre'],
            ['id', 'nombre'],
            ProductoWebResource::class,
        );
    }

    public function show(Producto $producto): JsonResponse
    {
        return response()->json($this->detalle($this->registro($producto)));
    }

    public function update(GuardarProductoWebRequest $request, Producto $producto): JsonResponse
    {
        $registro = $this->registro($producto);
        $this->publicacion->guardarBorrador($registro, $request->validated('web'), $request->user());

        return response()->json($this->detalle($registro));
    }

    public function subirArchivo(SubirArchivoWebRequest $request, Producto $producto): JsonResponse
    {
        $archivo = $this->publicacion->subirArchivo($this->registro($producto), $request->file('archivo'), "tienda-web/productos/{$producto->id}");

        return response()->json((new ArchivoResource($archivo))->resolve($request), 201);
    }

    public function publicar(Request $request, Producto $producto): JsonResponse
    {
        $registro = $this->registro($producto);
        abort_if($registro->borrador === null, 409, 'No hay cambios para publicar.');

        // Entre guardar y publicar otro producto pudo tomar la dirección.
        $slug = $registro->borrador['slug'];
        Validator::make(['slug' => $slug], ['slug' => [GuardarProductoWebRequest::slugLibre($producto)]], [], ['slug' => 'dirección'])->validate();

        $this->publicacion->publicar($registro, $request->user(), ['slug' => $slug]);
        // La web lo vuelve a pedir al publicar: que no reciba el de hace un minuto.
        Cache::forget(CatalogoPublicoController::CACHE_CLAVE);
        // Las secciones citan productos por su dirección: si cambió, también.
        Cache::forget(ContenidoPublicoController::CACHE_CLAVE);

        return response()->json($this->detalle($registro));
    }

    public function descartar(Producto $producto): JsonResponse
    {
        $registro = $this->registro($producto);
        $this->publicacion->descartar($registro);

        return response()->json($this->detalle($registro));
    }

    private function registro(Producto $producto): ProductoWeb
    {
        return $producto->web()->firstOrNew();
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(ProductoWeb $registro): array
    {
        $registro->load(['archivos', 'editor:id,name', 'publicador:id,name', 'producto:id,nombre,descripcion']);

        return [
            ...(new PublicacionWebResource($registro))->resolve(request()),
            // Para el encabezado del panel y como texto de respaldo.
            'producto' => $registro->producto->only(['id', 'nombre', 'descripcion']),
        ];
    }
}
