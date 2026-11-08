<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Respuesta del proveedor a una hoja de reclamación. Payload anidado en
 * `reclamacion`.
 */
class ResponderReclamacionRequest extends FormRequest
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
        return [
            'reclamacion' => ['required', 'array'],
            'reclamacion.respuesta' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reclamacion.respuesta' => 'respuesta'];
    }
}
