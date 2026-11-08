<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarReclamacionRequest;
use App\Http\Resources\ReclamacionResource;
use App\Mail\HojaDeReclamacion;
use App\Models\Reclamacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Libro de Reclamaciones de la tienda web: la hoja la envía el servidor de
 * la web con la clave de la tienda (ClaveTiendaWeb), sin sesión.
 */
class ReclamacionPublicaController extends Controller
{
    public function store(GuardarReclamacionRequest $request): JsonResponse
    {
        $reclamacion = Reclamacion::registrar($request->validated('reclamacion'));

        // La copia por correo es obligatoria, pero si el correo falla la hoja
        // ya quedó registrada: no se pierde (la web muestra la hoja para imprimir).
        if ($reclamacion->email) {
            try {
                Mail::to($reclamacion->email)->send(new HojaDeReclamacion($reclamacion));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json((new ReclamacionResource($reclamacion))->resolve($request), 201);
    }
}
