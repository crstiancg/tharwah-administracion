<?php

namespace App\Http\Resources;

use App\Models\ContenidoWeb;
use App\Models\ProductoWeb;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una sección o el contenido web de un producto, para el panel de edición:
 * el borrador, lo publicado y sus archivos (para la vista previa).
 *
 * @mixin ContenidoWeb|ProductoWeb
 */
class PublicacionWebResource extends JsonResource
{
    public const VACIO = 'vacio';

    public const SIN_PUBLICAR = 'sin_publicar';

    public const CON_CAMBIOS = 'con_cambios';

    public const PUBLICADO = 'publicado';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...($this->resource instanceof ContenidoWeb ? ['clave' => $this->clave] : ['producto_id' => $this->producto_id]),
            'estado' => self::estado($this->resource),
            'borrador' => $this->borrador,
            'publicado' => $this->publicado,
            'archivos' => ArchivoResource::collection($this->whenLoaded('archivos')),
            'editado_por' => $this->whenLoaded('editor', fn () => $this->editor?->only(['id', 'name'])),
            'publicado_por' => $this->whenLoaded('publicador', fn () => $this->publicador?->only(['id', 'name'])),
            'publicado_at' => $this->publicado_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Para el panel: nunca tocado, nunca publicado, publicado con cambios
     * pendientes o publicado al día.
     */
    public static function estado(ContenidoWeb|ProductoWeb|null $registro): string
    {
        return match (true) {
            $registro?->publicado === null && $registro?->borrador === null => self::VACIO,
            $registro->publicado === null => self::SIN_PUBLICAR,
            $registro->borrador !== null => self::CON_CAMBIOS,
            default => self::PUBLICADO,
        };
    }
}
