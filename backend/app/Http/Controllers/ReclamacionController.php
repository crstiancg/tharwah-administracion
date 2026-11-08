<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResponderReclamacionRequest;
use App\Http\Resources\ReclamacionResource;
use App\Mail\RespuestaDeReclamacion;
use App\Models\Reclamacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Libro de Reclamaciones en el panel: ver las hojas y responderlas dentro
 * del plazo. Las hojas no se editan ni se borran.
 */
class ReclamacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Pendientes primero (las que vencen antes arriba); luego las respondidas.
        $query = Reclamacion::query()
            ->when($request->input('estado') === 'pendiente', fn ($q) => $q->whereNull('respondido_at'))
            ->when($request->input('estado') === 'respondida', fn ($q) => $q->whereNotNull('respondido_at'))
            ->orderByRaw('respondido_at is not null')
            ->orderBy('created_at', 'asc');

        return $this->generateViewSetList(
            $request,
            $query,
            ['tipo'],
            ['codigo', 'nombres', 'apellidos', 'numero_documento'],
            ['id', 'created_at'],
            ReclamacionResource::class,
        );
    }

    public function show(Reclamacion $reclamacion): JsonResponse
    {
        return response()->json((new ReclamacionResource($reclamacion->load('responsable:id,name')))->resolve(request()));
    }

    public function responder(ResponderReclamacionRequest $request, Reclamacion $reclamacion): JsonResponse
    {
        abort_if($reclamacion->respondida(), 409, 'Esta hoja ya fue respondida.');

        $reclamacion->update([
            'respuesta' => $request->validated('reclamacion.respuesta'),
            'respondido_at' => now(),
            'respondido_por' => $request->user()->id,
        ]);

        // Si el correo falla, la respuesta queda registrada igual.
        if ($reclamacion->email) {
            try {
                Mail::to($reclamacion->email)->send(new RespuestaDeReclamacion($reclamacion));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json((new ReclamacionResource($reclamacion->load('responsable:id,name')))->resolve($request));
    }
}
