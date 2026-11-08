<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Un archivo suelto para el borrador de la tienda web: imagen para una
 * sección (banners, categorías) o ficha técnica en PDF para un producto.
 */
class SubirArchivoWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Mismos límites que las fotos de producto (StoreProductoRequest).
        $tipo = $this->routeIs('productos-web.archivos')
            ? ['mimes:pdf', 'max:10240']
            : ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'];

        return [
            'archivo' => ['required', 'file', ...$tipo],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['archivo' => 'archivo'];
    }
}
