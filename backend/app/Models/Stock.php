<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock de una presentación en una sede. Sin fillable a propósito: lo mueve
 * sólo App\Services\Inventario, dejando su movimiento en el libro.
 */
class Stock extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
        ];
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
