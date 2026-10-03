<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El único lugar que cambia una cotización. No toca stock: eso pasa recién
 * cuando el pedido que nace de ella se confirma.
 */
class Cotizaciones
{
    public function __construct(private Pedidos $pedidos) {}

    /**
     * Crea o reemplaza los ítems de una cotización PENDIENTE y recalcula los
     * totales (nunca se confía en los del navegador).
     *
     * @param  array<string, mixed>  $datos  cotización validada (con `items`)
     */
    public function guardar(Cotizacion $cotizacion, array $datos, User $usuario): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion, $datos, $usuario) {
            $nueva = ! $cotizacion->exists;

            $cotizacion->fill($datos);
            if ($nueva) {
                $cotizacion->forceFill([
                    'estado' => Cotizacion::PENDIENTE,
                    'sede_id' => $usuario->sedeOperativa(),
                    'user_id' => $usuario->id,
                    'subtotal' => 0,
                    'total' => 0,
                ]);
            }
            $cotizacion->save();

            if ($nueva) {
                $cotizacion->forceFill(['codigo' => 'COT-'.str_pad((string) $cotizacion->id, 6, '0', STR_PAD_LEFT)])->save();
            }

            $cotizacion->items()->delete();
            $subtotal = 0.0;
            foreach ($datos['items'] as $item) {
                $linea = round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2);
                $subtotal += $linea;

                $cotizacion->items()->create([
                    'variante_id' => $item['variante_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $linea,
                ]);
            }

            $cotizacion->forceFill([
                'subtotal' => round($subtotal, 2),
                'total' => round($subtotal - (float) ($datos['descuento'] ?? 0), 2),
            ])->save();

            return $cotizacion;
        });
    }

    /**
     * El cliente aceptó: nace un pedido PENDIENTE con los mismos ítems y
     * precios (se confirma, cobra y entrega por el flujo de pedidos).
     */
    public function convertir(Cotizacion $cotizacion, User $usuario): Pedido
    {
        return DB::transaction(function () use ($cotizacion, $usuario) {
            $cotizacion = Cotizacion::query()->whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();
            $this->exigirPendiente($cotizacion, 'convertir');

            if ($cotizacion->vencida()) {
                throw ValidationException::withMessages([
                    'estado' => 'La cotización venció el '.$cotizacion->valida_hasta->format('d/m/Y').': actualizá la validez (y los precios) antes de convertirla.',
                ])->status(409);
            }

            $pedido = $this->pedidos->guardar(new Pedido, [
                'cliente_id' => $cotizacion->cliente_id,
                'canal' => 'cotizacion',
                'descuento' => $cotizacion->descuento,
                'observacion' => mb_substr("Cotización {$cotizacion->codigo}".($cotizacion->condiciones ? " · {$cotizacion->condiciones}" : ''), 0, 500),
                'items' => $cotizacion->items()->orderBy('id')->get()->map(fn ($i) => [
                    'variante_id' => $i->variante_id,
                    'cantidad' => $i->cantidad,
                    'precio_unitario' => $i->precio_unitario,
                ])->all(),
            ], $usuario);

            $cotizacion->forceFill(['estado' => Cotizacion::CONVERTIDA, 'pedido_id' => $pedido->id])->save();

            return $pedido;
        });
    }

    public function rechazar(Cotizacion $cotizacion): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion) {
            $cotizacion = Cotizacion::query()->whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();
            $this->exigirPendiente($cotizacion, 'rechazar');

            $cotizacion->forceFill(['estado' => Cotizacion::RECHAZADA])->save();

            return $cotizacion;
        });
    }

    private function exigirPendiente(Cotizacion $cotizacion, string $accion): void
    {
        if ($cotizacion->estado !== Cotizacion::PENDIENTE) {
            throw ValidationException::withMessages([
                'estado' => "No se puede {$accion} una cotización {$cotizacion->estado}.",
            ])->status(409);
        }
    }
}
