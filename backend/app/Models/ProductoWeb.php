<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * Cómo se presenta un producto en la tienda web (usos, ventajas, fichas…),
 * con su borrador y lo publicado. Lo que se vende (precio, stock, ofertas)
 * no está acá: sigue en el producto. Se edita sólo a través de
 * App\Services\PublicacionWeb.
 */
#[Fillable(['producto_id', 'slug', 'borrador', 'publicado', 'editado_por', 'publicado_por', 'publicado_at'])]
class ProductoWeb extends Model
{
    protected $table = 'productos_web';

    protected function casts(): array
    {
        return [
            'borrador' => 'array',
            'publicado' => 'array',
            'publicado_at' => 'datetime',
        ];
    }

    /**
     * La dirección del producto en la web (/p/{slug}): la publicada o, si
     * nunca se publicó, la que sale de su nombre ("Sika®-1" → "sika-1").
     * Cargar `web` antes en listados: si no, es una consulta por producto.
     */
    public static function slugPara(Producto $producto): string
    {
        return $producto->web?->slug ?? Str::slug($producto->nombre);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Fichas técnicas (PDF); el JSON las cita por id.
     */
    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->orderBy('orden');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editado_por');
    }

    public function publicador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }
}
