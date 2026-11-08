<?php

namespace App\Http\Requests;

use App\Models\ContenidoWeb;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guarda el borrador de una sección de la tienda web. Payload anidado en
 * `contenido`; su forma depende de la sección ({clave} de la ruta).
 *
 * Productos, presentaciones y categorías van por id (si se renombran, nada se
 * rompe); las imágenes, por el id que devolvió la subida
 * (POST contenido-web/{clave}/archivos).
 */
class GuardarContenidoWebRequest extends FormRequest
{
    /** Enlaces de botones: una página de la web ("/c/…") o una dirección https. */
    private const ENLACE = 'regex:/^(\/[^\s]*|https:\/\/[^\s]+)$/';

    /** @var int[]|null */
    private ?array $archivosDeLaSeccion = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->route('clave')) {
            'aviso' => [
                'contenido' => ['required', 'array'],
                'contenido.texto' => ['required', 'string', 'max:120'],
            ],
            'preguntas' => [
                'contenido' => ['present', 'array', 'max:20'],
                'contenido.*.pregunta' => ['required', 'string', 'max:160'],
                'contenido.*.respuesta' => ['required', 'string', 'max:800'],
            ],
            'texto_tienda' => [
                'contenido' => ['required', 'array'],
                'contenido.titulo' => ['required', 'string', 'max:80'],
                'contenido.bloques' => ['present', 'array', 'max:9'],
                'contenido.bloques.*.titulo' => ['required', 'string', 'max:80'],
                'contenido.bloques.*.texto' => ['required', 'string', 'max:600'],
            ],
            'nosotros' => [
                'contenido' => ['required', 'array'],
                'contenido.titulo' => ['required', 'string', 'max:120'],
                'contenido.texto' => ['required', 'string', 'max:2000'],
            ],
            'carrusel' => [
                'contenido' => ['present', 'array', 'max:8'],
                'contenido.*.etiqueta' => ['required', 'string', 'max:30'],
                'contenido.*.titulo' => ['required', 'string', 'max:90'],
                'contenido.*.texto' => ['nullable', 'string', 'max:240'],
                'contenido.*.imagen_id' => ['required', 'integer', $this->archivoDeLaSeccion()],
                'contenido.*.productos' => ['present', 'array', 'max:8'],
                'contenido.*.productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
                // Altura relativa al envase más alto (1 = el más alto).
                'contenido.*.productos.*.escala' => ['required', 'numeric', 'min:0.2', 'max:1'],
                'contenido.*.boton' => ['required', 'array'],
                'contenido.*.boton.texto' => ['required', 'string', 'max:40'],
                'contenido.*.boton.enlace' => ['required', 'string', 'max:200', self::ENLACE],
                // Segundo botón (opcional): a una página o un mensaje de
                // WhatsApp ya escrito. Sin él, sus campos ni se miran.
                'contenido.*.boton_secundario' => ['nullable', 'array'],
                'contenido.*.boton_secundario.texto' => ['exclude_without:contenido.*.boton_secundario', 'required', 'string', 'max:40'],
                'contenido.*.boton_secundario.enlace' => ['exclude_without:contenido.*.boton_secundario', 'nullable', 'required_without:contenido.*.boton_secundario.whatsapp', 'string', 'max:200', self::ENLACE],
                'contenido.*.boton_secundario.whatsapp' => ['exclude_without:contenido.*.boton_secundario', 'nullable', 'string', 'max:300'],
            ],
            'campanas' => [
                'contenido' => ['present', 'array', 'max:6'],
                'contenido.*.etiqueta' => ['nullable', 'string', 'max:40'],
                'contenido.*.titulo' => ['required', 'string', 'max:90'],
                'contenido.*.texto' => ['nullable', 'string', 'max:240'],
                'contenido.*.boton' => ['required', 'string', 'max:40'],
                'contenido.*.enlace' => ['required', 'string', 'max:200', self::ENLACE],
                'contenido.*.tono' => ['required', 'in:dark,yellow,light'],
                'contenido.*.productos' => ['present', 'array', 'max:6'],
                'contenido.*.productos.*' => ['integer', 'exists:productos,id'],
            ],
            'kits' => [
                'contenido' => ['present', 'array', 'max:6'],
                'contenido.*.nombre' => ['required', 'string', 'max:30'],
                'contenido.*.titulo' => ['required', 'string', 'max:80'],
                'contenido.*.texto' => ['nullable', 'string', 'max:300'],
                // Descuento que se muestra para el kit completo (el precio
                // final lo confirma el vendedor en la cotización).
                'contenido.*.descuento' => ['required', 'integer', 'min:0', 'max:50'],
                'contenido.*.items' => ['required', 'array', 'min:1', 'max:6'],
                'contenido.*.items.*.variante_id' => ['required', 'integer', 'exists:variantes,id'],
                'contenido.*.items.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
                'contenido.*.items.*.rol' => ['nullable', 'string', 'max:80'],
            ],
            'categorias' => [
                'contenido' => ['present', 'array', 'max:100'],
                'contenido.*.categoria_id' => ['required', 'integer', 'distinct', 'exists:categorias,id'],
                'contenido.*.descripcion' => ['nullable', 'string', 'max:300'],
                'contenido.*.imagen_id' => ['nullable', 'integer', $this->archivoDeLaSeccion()],
            ],
            'soluciones' => [
                'contenido' => ['present', 'array', 'max:20'],
                // La clave es la dirección (/soluciones/{clave}) y la citan los productos.
                'contenido.*.clave' => ['required', 'string', 'max:60', 'distinct', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
                'contenido.*.nombre' => ['required', 'string', 'max:80'],
                'contenido.*.resumen' => ['required', 'string', 'max:200'],
                'contenido.*.pasos' => ['present', 'array', 'max:8'],
                'contenido.*.pasos.*' => ['string', 'max:300'],
                'contenido.*.imagen_id' => ['nullable', 'integer', $this->archivoDeLaSeccion()],
            ],
            // Imágenes de promoción (afiches): cada una lleva a un producto o a
            // una página de la web.
            'banners' => [
                'contenido' => ['present', 'array', 'max:6'],
                'contenido.*.imagen_id' => ['required', 'integer', $this->archivoDeLaSeccion()],
                // Lo que dice la imagen, para quien no la ve (lectores de pantalla, Google).
                'contenido.*.descripcion' => ['required', 'string', 'max:160'],
                'contenido.*.producto_id' => ['nullable', 'integer', 'required_without:contenido.*.enlace', 'exists:productos,id'],
                'contenido.*.enlace' => ['nullable', 'string', 'required_without:contenido.*.producto_id', 'max:200', self::ENLACE],
            ],
        };
    }

    /**
     * Sólo imágenes subidas para ESTA sección (y que sigan existiendo: si otro
     * editor guardó sin ella, ya se borró).
     */
    private function archivoDeLaSeccion(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $this->archivosDeLaSeccion ??= ContenidoWeb::query()
                ->where('clave', $this->route('clave'))
                ->first()
                ?->archivos()
                ->pluck('id')
                ->all() ?? [];

            if (! in_array((int) $value, $this->archivosDeLaSeccion, true)) {
                $fail('La imagen ya no está disponible; súbela de nuevo.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'contenido.texto' => 'texto',
            'contenido.titulo' => 'título',
            'contenido.bloques' => 'bloques',
            'contenido.*.pregunta' => 'pregunta',
            'contenido.*.respuesta' => 'respuesta',
            'contenido.*.etiqueta' => 'etiqueta',
            'contenido.*.titulo' => 'título',
            'contenido.*.texto' => 'texto',
            'contenido.*.imagen_id' => 'imagen',
            'contenido.*.productos' => 'productos',
            'contenido.*.productos.*.producto_id' => 'producto',
            'contenido.*.productos.*' => 'producto',
            'contenido.*.productos.*.escala' => 'escala',
            'contenido.*.boton' => 'botón',
            'contenido.*.boton.texto' => 'texto del botón',
            'contenido.*.boton.enlace' => 'enlace del botón',
            'contenido.*.enlace' => 'enlace',
            'contenido.*.tono' => 'tono',
            'contenido.*.nombre' => 'nombre',
            'contenido.*.descuento' => 'descuento',
            'contenido.*.items' => 'productos del kit',
            'contenido.*.items.*.variante_id' => 'presentación',
            'contenido.*.items.*.cantidad' => 'cantidad',
            'contenido.*.items.*.rol' => 'para qué sirve',
            'contenido.*.categoria_id' => 'categoría',
            'contenido.*.descripcion' => 'descripción',
            'contenido.*.clave' => 'dirección',
            'contenido.*.resumen' => 'resumen',
            'contenido.*.pasos' => 'pasos',
            'contenido.*.pasos.*' => 'paso',
            'contenido.*.producto_id' => 'producto',
        ];
    }
}
