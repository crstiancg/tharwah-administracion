<?php

namespace App\Http\Resources;

use App\Models\Reclamacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una hoja de reclamación completa, con los datos del proveedor (los de la
 * facturación electrónica) y el plazo para responder.
 *
 * @mixin Reclamacion
 */
class ReclamacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipo' => $this->tipo,
            'presentado_at' => $this->created_at,
            'fecha_limite' => $this->fechaLimite()->toDateString(),
            'estado' => $this->respondida() ? 'respondida' : 'pendiente',
            'proveedor' => self::proveedor(),
            'consumidor' => [
                'nombres' => $this->nombres,
                'apellidos' => $this->apellidos,
                'tipo_documento' => $this->tipo_documento,
                'numero_documento' => $this->numero_documento,
                'menor_de_edad' => $this->menor_de_edad,
                'apoderado' => $this->apoderado,
                'direccion' => $this->direccion,
                'distrito' => $this->distrito,
                'provincia' => $this->provincia,
                'departamento' => $this->departamento,
                'email' => $this->email,
                'telefono' => $this->telefono,
            ],
            'bien' => [
                'tipo' => $this->bien_tipo,
                'canal' => $this->canal,
                'fecha_incidente' => $this->fecha_incidente?->toDateString(),
                'tienda' => $this->tienda,
                'comprobante_tipo' => $this->comprobante_tipo,
                'comprobante_numero' => $this->comprobante_numero,
                'descripcion' => $this->descripcion_bien,
                'monto' => $this->monto,
            ],
            'detalle' => $this->detalle,
            'pedido' => $this->pedido,
            'respuesta' => $this->respuesta,
            'respondido_at' => $this->respondido_at,
            'respondido_por' => $this->whenLoaded('responsable', fn () => $this->responsable?->only(['id', 'name'])),
        ];
    }

    /**
     * El proveedor como figura en la facturación electrónica.
     *
     * @return array{razon_social: string, ruc: string, direccion: string}
     */
    public static function proveedor(): array
    {
        $empresa = config('greenter.company');
        $direccion = $empresa['address'];

        return [
            'razon_social' => $empresa['razonSocial'],
            'ruc' => $empresa['ruc'],
            'direccion' => trim("{$direccion['direccion']}, {$direccion['distrito']} - {$direccion['provincia']} - {$direccion['departamento']}"),
        ];
    }
}
