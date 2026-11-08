<?php

namespace App\Http\Requests;

use App\Models\Producto;
use App\Models\ProductoWeb;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guarda el borrador del contenido web de un producto. Payload anidado en
 * `web`. Lo que se vende (precio, presentaciones, stock) no va acá: se edita
 * en el producto.
 */
class GuardarProductoWebRequest extends FormRequest
{
    public const SLUG = 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /** @var int[]|null */
    private ?array $archivosDelProducto = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $web = $this->input('web');
        if (! is_array($web)) {
            return;
        }

        if (is_string($web['slug'] ?? null)) {
            $web['slug'] = trim($web['slug']);
        }

        $this->merge(['web' => $web]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'web' => ['required', 'array'],
            'web.slug' => ['required', 'string', 'max:120', self::SLUG, self::slugLibre($this->route('producto'))],
            'web.resumen' => ['required', 'string', 'max:200'],
            // Texto para la web; sin él se usa la descripción del producto.
            'web.descripcion' => ['nullable', 'string', 'max:2000'],
            // Tres razones de compra, junto al botón de la ficha.
            'web.razones' => ['sometimes', 'array', 'max:3'],
            'web.razones.*' => ['string', 'max:80'],
            'web.usos' => ['present', 'array', 'max:10'],
            'web.usos.*' => ['string', 'max:200'],
            'web.ventajas' => ['present', 'array', 'max:10'],
            'web.ventajas.*' => ['string', 'max:200'],
            'web.modo_uso' => ['present', 'array', 'max:10'],
            'web.modo_uso.*' => ['string', 'max:300'],
            'web.rendimiento' => ['nullable', 'string', 'max:200'],
            'web.especificaciones' => ['sometimes', 'array', 'max:12'],
            'web.especificaciones.*.etiqueta' => ['required', 'string', 'max:60'],
            'web.especificaciones.*.valor' => ['required', 'string', 'max:120'],
            // Lo que se usa junto a este producto (pistola, imprimante…).
            'web.complementos' => ['sometimes', 'array', 'max:6'],
            'web.complementos.*' => ['integer', 'distinct', 'not_in:'.$this->route('producto')->id, 'exists:productos,id'],
            // Palabras con las que también lo buscan.
            'web.etiquetas' => ['sometimes', 'array', 'max:20'],
            'web.etiquetas.*' => ['string', 'max:40'],
            // Claves de las soluciones (contenido web "soluciones").
            'web.soluciones' => ['present', 'array', 'max:10'],
            'web.soluciones.*' => ['string', 'max:60', 'distinct', self::SLUG],
            'web.destacado' => ['required', 'boolean'],
            'web.nuevo' => ['required', 'boolean'],
            'web.orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'web.fichas' => ['present', 'array', 'max:6'],
            'web.fichas.*.titulo' => ['required', 'string', 'max:80'],
            'web.fichas.*.tipo' => ['nullable', 'in:ficha-tecnica,hoja-seguridad,otro'],
            'web.fichas.*.archivo_id' => ['required', 'integer', 'distinct', $this->fichaDelProducto()],
        ];
    }

    /**
     * La dirección no puede ser la de otro producto, ni la publicada ni la de
     * un borrador (si no, al publicarlo chocarían). También la usa el
     * controlador al publicar.
     */
    public static function slugLibre(Producto $producto): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($producto) {
            $ocupado = ProductoWeb::query()
                ->where('producto_id', '!=', $producto->id)
                ->where(fn ($q) => $q->where('slug', $value)->orWhere('borrador->slug', $value))
                ->exists();

            if ($ocupado) {
                $fail('Otro producto ya usa esa dirección.');
            }
        };
    }

    /**
     * Sólo fichas subidas para ESTE producto (y que sigan existiendo).
     */
    private function fichaDelProducto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $this->archivosDelProducto ??= $this->route('producto')->web?->archivos()->pluck('id')->all() ?? [];

            if (! in_array((int) $value, $this->archivosDelProducto, true)) {
                $fail('La ficha ya no está disponible; súbela de nuevo.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'web.slug' => 'dirección',
            'web.resumen' => 'resumen',
            'web.descripcion' => 'descripción',
            'web.razones' => 'razones de compra',
            'web.razones.*' => 'razón de compra',
            'web.especificaciones' => 'especificaciones',
            'web.especificaciones.*.etiqueta' => 'nombre de la especificación',
            'web.especificaciones.*.valor' => 'valor de la especificación',
            'web.complementos' => 'complementos',
            'web.complementos.*' => 'complemento',
            'web.etiquetas' => 'palabras de búsqueda',
            'web.etiquetas.*' => 'palabra de búsqueda',
            'web.fichas.*.tipo' => 'tipo de ficha',
            'web.usos' => 'usos',
            'web.usos.*' => 'uso',
            'web.ventajas' => 'ventajas',
            'web.ventajas.*' => 'ventaja',
            'web.modo_uso' => 'modo de uso',
            'web.modo_uso.*' => 'paso',
            'web.rendimiento' => 'rendimiento',
            'web.soluciones' => 'soluciones',
            'web.soluciones.*' => 'solución',
            'web.destacado' => 'destacado',
            'web.nuevo' => 'nuevo',
            'web.orden' => 'orden',
            'web.fichas' => 'fichas técnicas',
            'web.fichas.*.titulo' => 'título de la ficha',
            'web.fichas.*.archivo_id' => 'ficha',
        ];
    }
}
