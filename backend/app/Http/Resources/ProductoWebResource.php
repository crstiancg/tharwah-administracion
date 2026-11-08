<?php

namespace App\Http\Resources;

use App\Models\Producto;
use App\Models\ProductoWeb;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un producto en la lista del panel de la tienda web: qué es y en qué estado
 * está su contenido web (para ver de un vistazo cuáles faltan).
 *
 * @mixin Producto
 */
class ProductoWebResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'activo' => $this->activo,
            'categoria' => $this->categoria?->only(['id', 'nombre']),
            'slug' => ProductoWeb::slugPara($this->resource),
            'estado' => PublicacionWebResource::estado($this->web),
            'tiene_foto' => $this->portada !== null,
            'updated_at' => $this->web?->updated_at,
        ];
    }
}
