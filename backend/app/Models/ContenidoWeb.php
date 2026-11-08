<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Una sección de la tienda web (aviso, carrusel, preguntas…), con su
 * borrador y lo publicado. Se edita sólo a través de App\Services\PublicacionWeb.
 */
#[Fillable(['clave', 'borrador', 'publicado', 'editado_por', 'publicado_por', 'publicado_at'])]
class ContenidoWeb extends Model
{
    /** Las secciones que existen; la forma de cada una la fija su FormRequest. */
    public const CLAVES = [
        'aviso',
        'carrusel',
        'campanas',
        'kits',
        'preguntas',
        'texto_tienda',
        'nosotros',
        'categorias',
        'soluciones',
        'banners',
    ];

    protected $table = 'contenido_web';

    protected function casts(): array
    {
        return [
            'borrador' => 'array',
            'publicado' => 'array',
            'publicado_at' => 'datetime',
        ];
    }

    /**
     * Imágenes de la sección (banners, fotos de categoría); el JSON las cita por id.
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
