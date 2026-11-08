@php
    $zona = \App\Support\Fechas::zona();
    $tipos = ['reclamo' => 'Reclamo', 'queja' => 'Queja'];
@endphp
<x-mail::message>
# Hoja de reclamación {{ $r->codigo }}

Hola {{ $r->nombres }}, recibimos tu {{ strtolower($tipos[$r->tipo]) }} el
**{{ $r->created_at->setTimezone($zona)->format('d/m/Y \a \l\a\s H:i') }}**.
Te responderemos a más tardar el **{{ $r->fechaLimite()->format('d/m/Y') }}** (15 días hábiles).

**Proveedor:** {{ $proveedor['razon_social'] }} — RUC {{ $proveedor['ruc'] }}<br>
{{ $proveedor['direccion'] }}

## Tus datos
{{ $r->nombres }} {{ $r->apellidos }} — {{ $r->tipo_documento }} {{ $r->numero_documento }}
@if ($r->menor_de_edad)
<br>Apoderado: {{ $r->apoderado }}
@endif
@if ($r->direccion)
<br>{{ $r->direccion }}{{ $r->distrito ? ', '.$r->distrito : '' }}{{ $r->provincia ? ', '.$r->provincia : '' }}{{ $r->departamento ? ', '.$r->departamento : '' }}
@endif
@if ($r->telefono)
<br>Teléfono: {{ $r->telefono }}
@endif

## Bien contratado
{{ ucfirst($r->bien_tipo) }}@if ($r->monto) — Monto: S/ {{ number_format((float) $r->monto, 2) }}@endif<br>
{{ $r->descripcion_bien }}

## {{ $tipos[$r->tipo] }}
{{ $r->detalle }}

**Pedido:** {{ $r->pedido }}

<x-mail::panel>
La confirmación de envío de esta hoja expresa únicamente su recepción, no la aceptación de su contenido.
La formulación del reclamo no impide acudir a otras vías de solución de controversias ni es requisito previo para interponer una denuncia ante el INDECOPI.
</x-mail::panel>

{{ $proveedor['razon_social'] }}
</x-mail::message>
