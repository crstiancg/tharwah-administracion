<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Libro de Reclamaciones virtual (Código de Protección y Defensa del
        // Consumidor, Ley 29571, y su reglamento): una fila por hoja de
        // reclamación presentada en la tienda web. Se conservan al menos dos
        // años; no se borran.
        Schema::create('reclamaciones', function (Blueprint $table) {
            $table->id();
            // Numeración correlativa por año: 2026-000001.
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('numero');
            $table->string('codigo', 20)->unique();
            $table->string('tipo', 10); // reclamo | queja

            // Consumidor. Como mínimo correo o domicilio (lo valida el FormRequest).
            $table->string('nombres', 120);
            $table->string('apellidos', 120);
            $table->string('tipo_documento', 12); // DNI | CE | RUC | PASAPORTE
            $table->string('numero_documento', 20);
            $table->boolean('menor_de_edad')->default(false);
            $table->string('apoderado', 200)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('distrito', 80)->nullable();
            $table->string('provincia', 80)->nullable();
            $table->string('departamento', 80)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('telefono', 30)->nullable();

            // Bien contratado y cómo se compró.
            $table->string('bien_tipo', 10); // producto | servicio
            $table->string('canal', 20)->nullable();
            $table->date('fecha_incidente')->nullable();
            $table->string('tienda', 80)->nullable();
            $table->string('comprobante_tipo', 20)->nullable();
            $table->string('comprobante_numero', 40)->nullable();
            $table->text('descripcion_bien');
            $table->decimal('monto', 12, 2)->nullable();

            // Lo que pasó y lo que pide.
            $table->text('detalle');
            $table->text('pedido');

            // Respuesta del proveedor (plazo: 15 días hábiles).
            $table->text('respuesta')->nullable();
            $table->timestamp('respondido_at')->nullable();
            $table->foreignId('respondido_por')->nullable()->constrained('users')->nullOnDelete();

            // created_at es la fecha y hora de presentación que va en la hoja.
            $table->timestamps();

            $table->unique(['anio', 'numero']);
            $table->index(['respondido_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamaciones');
    }
};
