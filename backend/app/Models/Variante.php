<?php

namespace App\Models;

use App\Support\Ean13;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Una presentación de un producto ("Cartucho 300 ml", opcionalmente en un
 * color): lo que efectivamente tiene SKU y stock.
 * `stock` y `costo_promedio` no son fillable a propósito: sólo los mueve
 * App\Services\Inventario, dejando su movimiento en el libro.
 * `codigo_barras` tampoco: lo asigna el sistema al crear y no cambia nunca
 * (ya está impreso en las etiquetas).
 */
#[Fillable(['presentacion', 'unidad_id', 'color_id', 'sku', 'precio', 'stock_minimo'])]
class Variante extends Model
{
    protected static function booted(): void
    {
        // Sale del id, que recién existe después del INSERT.
        static::created(function (Variante $variante) {
            $variante->forceFill(['codigo_barras' => Ean13::paraVariante($variante->id)])->saveQuietly();
        });
    }

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            // float y no decimal:3: el front compara números (stock > 0).
            'stock' => 'float',
            'stock_minimo' => 'float',
            'costo_promedio' => 'decimal:4',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    /**
     * Agrega `stock_sede`: el stock en esa sede (0 si nunca tuvo).
     */
    public function scopeConStockDeSede(Builder $query, int $sedeId): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('variantes.*');
        }

        $query->addSelect(['stock_sede' => Stock::query()
            ->selectRaw('COALESCE(SUM(cantidad), 0)')
            ->whereColumn('stocks.variante_id', 'variantes.id')
            ->where('stocks.sede_id', $sedeId)]);
    }

    /**
     * El stock que corresponde mostrar: el de la sede si se pidió con
     * conStockDeSede(), si no el total de la empresa.
     */
    public function stockVisible(): float
    {
        return array_key_exists('stock_sede', $this->attributes)
            ? (float) $this->attributes['stock_sede']
            : (float) $this->stock;
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->orderBy('orden');
    }

    /**
     * La primera foto de la presentación (orden 0), para el punto de venta.
     */
    public function portada(): MorphOne
    {
        return $this->morphOne(Archivo::class, 'archivable')->ofMany('orden', 'min');
    }
}
