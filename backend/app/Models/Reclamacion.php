<?php

namespace App\Models;

use App\Support\Fechas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Una hoja del Libro de Reclamaciones virtual. Se crea desde la tienda web
 * con su número correlativo del año (ver `registrar`) y se responde desde el
 * panel dentro del plazo legal.
 */
#[Fillable([
    'anio', 'numero', 'codigo', 'tipo',
    'nombres', 'apellidos', 'tipo_documento', 'numero_documento', 'menor_de_edad', 'apoderado',
    'direccion', 'distrito', 'provincia', 'departamento', 'email', 'telefono',
    'bien_tipo', 'canal', 'fecha_incidente', 'tienda', 'comprobante_tipo', 'comprobante_numero',
    'descripcion_bien', 'monto', 'detalle', 'pedido',
    'respuesta', 'respondido_at', 'respondido_por',
])]
class Reclamacion extends Model
{
    public const TIPOS = ['reclamo', 'queja'];

    public const DOCUMENTOS = ['DNI', 'CE', 'RUC', 'PASAPORTE'];

    public const BIENES = ['producto', 'servicio'];

    public const CANALES = ['virtual', 'presencial', 'telefonico', 'no_compre', 'otro'];

    public const COMPROBANTES = ['factura', 'boleta', 'pedido', 'orden_compra', 'transaccion', 'otro'];

    /** Días hábiles para responder (Reglamento del Libro de Reclamaciones; improrrogable). */
    public const DIAS_PARA_RESPONDER = 15;

    protected $table = 'reclamaciones';

    protected function casts(): array
    {
        return [
            'menor_de_edad' => 'boolean',
            'fecha_incidente' => 'date',
            'monto' => 'decimal:2',
            'respondido_at' => 'datetime',
        ];
    }

    /**
     * Guarda la hoja con el siguiente número del año. El bloqueo evita que dos
     * hojas enviadas al mismo tiempo tomen el mismo número.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function registrar(array $datos): self
    {
        return DB::transaction(function () use ($datos) {
            $anio = (int) now(Fechas::zona())->format('Y');
            $numero = (int) self::query()->where('anio', $anio)->lockForUpdate()->max('numero') + 1;

            return self::create([
                ...$datos,
                'anio' => $anio,
                'numero' => $numero,
                'codigo' => sprintf('%d-%06d', $anio, $numero),
            ]);
        });
    }

    /**
     * Último día para responder: 15 días hábiles (lunes a viernes) desde la
     * presentación. No descuenta feriados: con ellos el plazo real es mayor,
     * así que responder hasta esta fecha siempre está en plazo.
     */
    public function fechaLimite(): Carbon
    {
        return Carbon::parse($this->created_at)
            ->setTimezone(Fechas::zona())
            ->startOfDay()
            ->addWeekdays(self::DIAS_PARA_RESPONDER);
    }

    public function respondida(): bool
    {
        return $this->respondido_at !== null;
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondido_por');
    }
}
