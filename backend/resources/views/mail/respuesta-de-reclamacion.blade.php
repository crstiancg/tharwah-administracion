<x-mail::message>
# Respuesta a tu hoja {{ $r->codigo }}

Hola {{ $r->nombres }}, esta es nuestra respuesta a tu {{ $r->tipo === 'queja' ? 'queja' : 'reclamo' }} del
{{ $r->created_at->setTimezone(\App\Support\Fechas::zona())->format('d/m/Y') }}:

<x-mail::panel>
{{ $r->respuesta }}
</x-mail::panel>

Si no estás conforme, puedes acudir al INDECOPI.

{{ $proveedor['razon_social'] }} — RUC {{ $proveedor['ruc'] }}
</x-mail::message>
