<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarContenidoWebRequest;
use App\Http\Requests\SubirArchivoWebRequest;
use App\Http\Resources\ArchivoResource;
use App\Http\Resources\PublicacionWebResource;
use App\Models\ContenidoWeb;
use App\Services\PublicacionWeb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Secciones de la tienda web (aviso, carrusel, preguntas…) para el panel de
 * edición. La {clave} de la ruta ya viene restringida a ContenidoWeb::CLAVES;
 * una sección que nunca se tocó existe igual (vacía).
 */
class ContenidoWebController extends Controller
{
    public function __construct(private PublicacionWeb $publicacion) {}

    public function index(): JsonResponse
    {
        $secciones = ContenidoWeb::query()
            ->with(['editor:id,name', 'publicador:id,name'])
            ->get()
            ->keyBy('clave');

        return response()->json([
            'data' => collect(ContenidoWeb::CLAVES)->map(fn (string $clave) => [
                'clave' => $clave,
                'estado' => PublicacionWebResource::estado($secciones->get($clave)),
                'editado_por' => $secciones->get($clave)?->editor?->only(['id', 'name']),
                'publicado_at' => $secciones->get($clave)?->publicado_at,
                'updated_at' => $secciones->get($clave)?->updated_at,
            ])->all(),
        ]);
    }

    public function show(string $clave): JsonResponse
    {
        return response()->json($this->detalle($this->seccion($clave)));
    }

    public function update(GuardarContenidoWebRequest $request, string $clave): JsonResponse
    {
        $seccion = $this->seccion($clave);
        $this->publicacion->guardarBorrador($seccion, $request->validated('contenido'), $request->user());

        return response()->json($this->detalle($seccion));
    }

    public function subirArchivo(SubirArchivoWebRequest $request, string $clave): JsonResponse
    {
        $archivo = $this->publicacion->subirArchivo($this->seccion($clave), $request->file('archivo'), "tienda-web/{$clave}");

        return response()->json((new ArchivoResource($archivo))->resolve($request), 201);
    }

    public function publicar(Request $request, string $clave): JsonResponse
    {
        $seccion = $this->seccion($clave);
        abort_if($seccion->borrador === null, 409, 'No hay cambios para publicar.');

        $this->publicacion->publicar($seccion, $request->user());
        // La web lo vuelve a pedir al publicar: que no reciba el de hace un minuto.
        Cache::forget(ContenidoPublicoController::CACHE_CLAVE);

        return response()->json($this->detalle($seccion));
    }

    public function descartar(string $clave): JsonResponse
    {
        $seccion = $this->seccion($clave);
        $this->publicacion->descartar($seccion);

        return response()->json($this->detalle($seccion));
    }

    private function seccion(string $clave): ContenidoWeb
    {
        return ContenidoWeb::query()->firstOrNew(['clave' => $clave]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(ContenidoWeb $seccion): array
    {
        $seccion->load(['archivos', 'editor:id,name', 'publicador:id,name']);

        return (new PublicacionWebResource($seccion))->resolve(request());
    }
}
