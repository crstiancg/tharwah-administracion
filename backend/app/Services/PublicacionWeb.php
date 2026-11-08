<?php

namespace App\Services;

use App\Models\Archivo;
use App\Models\ContenidoWeb;
use App\Models\ProductoWeb;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Borrador y publicación del contenido de la tienda web (una sección o un
 * producto). La web sólo lee lo publicado: guardar un borrador nunca cambia
 * lo que ve el público.
 *
 * Los archivos (imágenes, fichas) se suben sueltos y el JSON los cita por
 * id; después de cada cambio se borran los que ya no cita ni el borrador ni
 * lo publicado.
 */
class PublicacionWeb
{
    /** Claves del JSON que guardan el id de un archivo. */
    private const CLAVES_DE_ARCHIVO = ['imagen_id', 'archivo_id'];

    public function __construct(private ArchivosService $archivos) {}

    /**
     * @param  array<mixed>  $borrador
     */
    public function guardarBorrador(ContenidoWeb|ProductoWeb $registro, array $borrador, User $user): void
    {
        DB::transaction(function () use ($registro, $borrador, $user) {
            $registro->fill(['borrador' => $borrador, 'editado_por' => $user->id])->save();
            $this->limpiarArchivos($registro);
        });
    }

    /**
     * Lo publicado pasa a ser el borrador, y el borrador se vacía (ya no hay
     * cambios pendientes). `$extra` son columnas que se derivan de lo
     * publicado (el slug de un producto).
     *
     * @param  array<string, mixed>  $extra
     */
    public function publicar(ContenidoWeb|ProductoWeb $registro, User $user, array $extra = []): void
    {
        DB::transaction(function () use ($registro, $user, $extra) {
            $registro->update([
                'publicado' => $registro->borrador,
                'borrador' => null,
                'publicado_por' => $user->id,
                'publicado_at' => now(),
                ...$extra,
            ]);
            $this->limpiarArchivos($registro);
        });
    }

    /**
     * Vuelve a lo publicado: los archivos que sólo usaba el borrador se borran.
     */
    public function descartar(ContenidoWeb|ProductoWeb $registro): void
    {
        DB::transaction(function () use ($registro) {
            $registro->update(['borrador' => null]);
            $this->limpiarArchivos($registro);
        });
    }

    /**
     * Sube un archivo para usarlo en el borrador. Queda sin citar hasta que
     * se guarde el borrador que lo usa.
     */
    public function subirArchivo(ContenidoWeb|ProductoWeb $registro, UploadedFile $archivo, string $carpeta): Archivo
    {
        return DB::transaction(function () use ($registro, $archivo, $carpeta) {
            // Se puede subir una imagen antes del primer guardado.
            $registro->save();

            return $this->archivos->agregar($registro, $archivo, $carpeta);
        });
    }

    /**
     * Ids de archivo que cita un JSON (a cualquier profundidad).
     *
     * @param  array<mixed>|null  $datos
     * @return int[]
     */
    public static function idsDeArchivos(?array $datos): array
    {
        $ids = [];
        $datos ??= [];
        array_walk_recursive($datos, function (mixed $valor, int|string $clave) use (&$ids) {
            if (in_array($clave, self::CLAVES_DE_ARCHIVO, true) && is_numeric($valor)) {
                $ids[] = (int) $valor;
            }
        });

        return array_values(array_unique($ids));
    }

    private function limpiarArchivos(ContenidoWeb|ProductoWeb $registro): void
    {
        $usados = [
            ...self::idsDeArchivos($registro->borrador),
            ...self::idsDeArchivos($registro->publicado),
        ];

        $this->archivos->eliminarExcepto($registro, $usados);
    }
}
