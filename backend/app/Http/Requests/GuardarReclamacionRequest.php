<?php

namespace App\Http\Requests;

use App\Models\Reclamacion;
use App\Support\Fechas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Hoja de reclamación enviada desde la tienda web. Payload anidado en
 * `reclamacion`. Lo mínimo que exige el reglamento (art. 5): nombre,
 * documento, domicilio o correo, y el detalle; sin eso la hoja no vale.
 */
class GuardarReclamacionRequest extends FormRequest
{
    /** Nombres de personas: letras (con tildes y ñ), espacios, apóstrofo y guion. */
    private const NOMBRE = "regex:/^[\pL\s'’-]+$/u";

    /** Distrito, provincia, departamento: como un nombre, más el punto. */
    private const LUGAR = "regex:/^[\pL\s'’.-]+$/u";

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $documento = $this->input('reclamacion.tipo_documento');

        return [
            'reclamacion' => ['required', 'array'],
            'reclamacion.tipo' => ['required', Rule::in(Reclamacion::TIPOS)],

            // Mismas reglas que los filtros de escritura de la web (complaints/domain/input.ts).
            'reclamacion.nombres' => ['required', 'string', 'max:120', self::NOMBRE],
            'reclamacion.apellidos' => ['required', 'string', 'max:120', self::NOMBRE],
            'reclamacion.tipo_documento' => ['required', Rule::in(Reclamacion::DOCUMENTOS)],
            'reclamacion.numero_documento' => ['required', 'string', 'max:20', ...match ($documento) {
                'DNI' => ['digits:8'],
                'RUC' => ['digits:11'],
                default => ['regex:/^[A-Za-z0-9]{5,12}$/'],
            }],
            'reclamacion.menor_de_edad' => ['required', 'boolean'],
            'reclamacion.apoderado' => ['nullable', 'required_if:reclamacion.menor_de_edad,true', 'string', 'max:200', self::NOMBRE],

            // Como mínimo correo o domicilio: con uno, el otro no es obligatorio.
            'reclamacion.email' => ['nullable', 'required_without:reclamacion.direccion', 'email', 'max:160'],
            'reclamacion.direccion' => ['nullable', 'required_without:reclamacion.email', 'string', 'max:200', 'regex:/^[\pL\d\s.,#°º\/()-]+$/u'],
            'reclamacion.distrito' => ['nullable', 'string', 'max:80', self::LUGAR],
            'reclamacion.provincia' => ['nullable', 'string', 'max:80', self::LUGAR],
            'reclamacion.departamento' => ['nullable', 'string', 'max:80', self::LUGAR],
            'reclamacion.telefono' => ['nullable', 'string', 'max:20', 'regex:/^\+?[\d ]{6,20}$/'],

            'reclamacion.bien_tipo' => ['required', Rule::in(Reclamacion::BIENES)],
            'reclamacion.canal' => ['nullable', Rule::in(Reclamacion::CANALES)],
            'reclamacion.fecha_incidente' => ['nullable', 'date', 'before_or_equal:'.Fechas::hoyIso()],
            'reclamacion.tienda' => ['nullable', 'string', 'max:80'],
            'reclamacion.comprobante_tipo' => ['nullable', Rule::in(Reclamacion::COMPROBANTES)],
            'reclamacion.comprobante_numero' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'reclamacion.descripcion_bien' => ['required', 'string', 'max:1000'],
            'reclamacion.monto' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999'],

            'reclamacion.detalle' => ['required', 'string', 'max:3000'],
            'reclamacion.pedido' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reclamacion.tipo' => 'reclamo o queja',
            'reclamacion.nombres' => 'nombre',
            'reclamacion.apellidos' => 'apellido',
            'reclamacion.tipo_documento' => 'tipo de documento',
            'reclamacion.numero_documento' => 'número de documento',
            'reclamacion.menor_de_edad' => 'menor de edad',
            'reclamacion.apoderado' => 'nombre del apoderado',
            'reclamacion.email' => 'correo',
            'reclamacion.direccion' => 'dirección',
            'reclamacion.distrito' => 'distrito',
            'reclamacion.provincia' => 'provincia',
            'reclamacion.departamento' => 'departamento',
            'reclamacion.telefono' => 'teléfono',
            'reclamacion.bien_tipo' => 'producto o servicio',
            'reclamacion.canal' => 'canal de compra',
            'reclamacion.fecha_incidente' => 'fecha del incidente',
            'reclamacion.tienda' => 'tienda',
            'reclamacion.comprobante_tipo' => 'tipo de comprobante',
            'reclamacion.comprobante_numero' => 'número de comprobante',
            'reclamacion.descripcion_bien' => 'descripción',
            'reclamacion.monto' => 'monto reclamado',
            'reclamacion.detalle' => 'detalle',
            'reclamacion.pedido' => 'pedido',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reclamacion.email.required_without' => 'Indica tu correo o tu dirección (al menos uno).',
            'reclamacion.direccion.required_without' => 'Indica tu dirección o tu correo (al menos uno).',
            'reclamacion.apoderado.required_if' => 'Si eres menor de edad, indica el nombre de tu padre, madre o apoderado.',
        ];
    }
}
