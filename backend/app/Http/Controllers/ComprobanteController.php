<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComprobanteResource;
use App\Models\Comprobante;
use App\Models\Pedido;
use App\Services\Facturacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprobantes electrónicos (boletas y facturas): emitir el de un pedido ya
 * vendido, reenviar el que no llegó a SUNAT y descargar el XML y el CDR.
 */
class ComprobanteController extends Controller
{
    public function __construct(private Facturacion $facturacion) {}

    /**
     * Los emitidos, el más nuevo primero. Filtros: estado, tipo, sede_id.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-id']);
        }

        return $this->generateViewSetList(
            $request,
            Comprobante::query()->with(['pedido:id,codigo', 'sede:id,nombre', 'usuario:id,name']),
            ['estado', 'tipo', 'sede_id'],
            ['serie', 'cliente_nombre', 'cliente_num_doc'],
            ['id', 'fecha_emision', 'total'],
            ComprobanteResource::class,
        );
    }

    public function store(Request $request, Pedido $pedido): JsonResponse
    {
        $request->validate(['tipo' => ['required', Rule::in(array_keys(Comprobante::TIPOS))]]);

        $comprobante = $this->facturacion->emitir($pedido, $request->input('tipo'), $request->user());

        return response()->json((new ComprobanteResource($comprobante))->resolve($request), 201);
    }

    public function reenviar(Request $request, Comprobante $comprobante): JsonResponse
    {
        return response()->json((new ComprobanteResource($this->facturacion->reenviar($comprobante)))->resolve($request));
    }

    public function xml(Comprobante $comprobante): Response
    {
        return $this->descargar($comprobante->xml_path, "{$comprobante->serie}-{$comprobante->correlativo}.xml", 'application/xml');
    }

    public function cdr(Comprobante $comprobante): Response
    {
        return $this->descargar($comprobante->cdr_path, "R-{$comprobante->serie}-{$comprobante->correlativo}.zip", 'application/zip');
    }

    private function descargar(?string $ruta, string $nombre, string $tipo): Response
    {
        if (! $ruta || ! Storage::disk('local')->exists($ruta)) {
            return response()->json(['message' => 'El archivo no está disponible.'], 404);
        }

        return Storage::disk('local')->download($ruta, $nombre, ['Content-Type' => $tipo]);
    }
}
