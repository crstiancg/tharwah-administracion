<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarVentaRequest;
use App\Http\Resources\CatalogoProductoResource;
use App\Http\Resources\PedidoResource;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Services\Cajas;
use App\Services\Pedidos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Punto de venta: una venta de mostrador ES un pedido que nace y termina en
 * el acto. No hay lógica propia de stock ni de dinero: encadena los mismos
 * servicios que el flujo de pedidos, en UNA transacción. Si falla el stock o
 * un cobro, no queda nada a medias (ni pedido, ni descuento de stock, ni
 * pagos).
 */
class VentaController extends Controller
{
    public function __construct(private Pedidos $pedidos, private Cajas $cajas) {}

    /**
     * Catálogo del punto de venta, con el stock de la sede del usuario:
     * productos activos con portada, stock,
     * vendidos (últimos 90 días) y sus variantes. Filtra por categoría (con
     * sus subcategorías), marca, color, "con stock" y nombre/SKU.
     */
    public function catalogo(Request $request): JsonResponse
    {
        $sedeId = $request->user()->sedeOperativa();

        $vendidos = PedidoItem::query()
            ->selectRaw('COALESCE(SUM(pedido_items.cantidad), 0)')
            ->join('variantes', 'variantes.id', '=', 'pedido_items.variante_id')
            ->join('pedidos', 'pedidos.id', '=', 'pedido_items.pedido_id')
            ->whereColumn('variantes.producto_id', 'productos.id')
            ->where('pedidos.sede_id', $sedeId)
            ->whereIn('pedidos.estado', [Pedido::CONFIRMADO, Pedido::ENTREGADO])
            ->where('pedidos.confirmado_at', '>=', now()->subDays(90));

        $query = Producto::query()
            ->select('productos.*')
            ->addSelect(['vendidos' => $vendidos])
            ->withSum(['stocks as stock_total' => fn ($q) => $q->where('stocks.sede_id', $sedeId)], 'cantidad')
            ->where('activo', true)
            ->with([
                'categoria:id,nombre',
                'marca:id,nombre',
                'portada',
                'variantes' => fn ($q) => $q->conStockDeSede($sedeId)->orderBy('variantes.id'),
                'variantes.unidad:id,nombre,abreviatura,fraccionable',
                'variantes.color:id,nombre,hexadecimal',
                'variantes.portada',
            ]);

        if ($request->filled('categoria_id')) {
            $categoria = Categoria::find($request->integer('categoria_id'));
            $query->whereIn('categoria_id', $categoria ? [$categoria->id, ...$categoria->descendientesIds()] : []);
        }

        if ($request->filled('marca_id')) {
            $query->where('marca_id', $request->integer('marca_id'));
        }

        // Color filtra por presentaciones que existan (y, con "con stock", que
        // tengan unidades): "gris" muestra lo que se puede vender en gris.
        $conStock = $request->boolean('con_stock');
        $variante = fn (Builder $v) => $v
            ->when($request->filled('color_id'), fn ($q) => $q->where('color_id', $request->integer('color_id')))
            ->when($conStock, fn ($q) => $q->whereHas('stocks', fn ($s) => $s->where('sede_id', $sedeId)->where('cantidad', '>', 0)));
        if ($request->filled('color_id') || $conStock) {
            $query->whereHas('variantes', $variante);
        }

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('productos.nombre', 'like', $term)
                ->orWhereHas('variantes', fn (Builder $v) => $v
                    ->where('sku', 'like', $term)
                    ->orWhere('codigo_barras', 'like', $term)));
        }

        match ($request->input('order_by', 'vendidos')) {
            'nombre' => $query->orderBy('productos.nombre'),
            'precio' => $query->orderBy('productos.precio'),
            '-precio' => $query->orderByDesc('productos.precio'),
            'stock' => $query->orderByDesc('stock_total'),
            default => $query->orderByDesc('vendidos')->orderBy('productos.nombre'),
        };

        return response()->json(
            $query->paginate($this->getPageSize($request))
                ->through(fn ($producto) => (new CatalogoProductoResource($producto))->resolve($request))
        );
    }

    public function store(RegistrarVentaRequest $request): JsonResponse
    {
        $venta = $request->validated('venta');
        $usuario = $request->user();

        $pedido = DB::transaction(function () use ($venta, $usuario) {
            $pedido = $this->pedidos->guardar(new Pedido, [
                'cliente_id' => $venta['cliente_id'] ?? null,
                'canal' => 'mostrador',
                'descuento' => $venta['descuento'] ?? 0,
                'observacion' => null,
                'items' => $venta['items'],
            ], $usuario);

            // Los errores de los servicios vienen con claves de pedidos
            // (pedido.items.N, pago.monto): se muestran en el carrito.
            $this->traducirErrores(fn () => $this->pedidos->confirmar($pedido, $usuario), 'pedido.items.', 'venta.items.');

            foreach (array_values($venta['pagos']) as $j => $pago) {
                $this->traducirErrores(fn () => $this->cajas->cobrar($pedido, $pago, $usuario), 'pago.', "venta.pagos.{$j}.");
            }

            $this->pedidos->entregar($pedido);

            return $pedido;
        });

        return response()->json($this->conDetalle($pedido->refresh()), 201);
    }

    private function traducirErrores(callable $accion, string $de, string $a): void
    {
        try {
            $accion();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn ($mensajes, $clave) => [str_replace($de, $a, $clave) => $mensajes])
                ->all())->status($e->status);
        }
    }

    /**
     * El pedido con todo lo necesario para imprimir el ticket.
     *
     * @return array<string, mixed>
     */
    private function conDetalle(Pedido $pedido): array
    {
        $pedido->load([
            'cliente',
            'sede:id,nombre,direccion,telefono',
            'usuario:id,name',
            'items' => fn ($q) => $q->orderBy('id'),
            'items.variante.producto:id,nombre,precio,categoria_id',
            'items.variante.unidad:id,nombre,abreviatura,fraccionable',
            'items.variante.color:id,nombre,hexadecimal',
            'pagos' => fn ($q) => $q->orderBy('id'),
            'pagos.usuario:id,name',
        ]);

        return (new PedidoResource($pedido))->resolve(request());
    }
}
