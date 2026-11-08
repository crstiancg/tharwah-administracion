<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cómo se presenta un producto en la tienda web (usos, ventajas,
        // fichas…). Tabla aparte a propósito: `productos` no cambia y lo que
        // se vende (precio, stock, ofertas) sigue saliendo de sus tablas.
        Schema::create('productos_web', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->unique()->constrained('productos')->cascadeOnDelete();
            // La dirección publicada (/p/{slug}), copiada de `publicado` al
            // publicar: así la base garantiza que no se repita.
            $table->string('slug', 120)->nullable()->unique();
            $table->json('borrador')->nullable();
            $table->json('publicado')->nullable();
            $table->foreignId('editado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publicado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_web');
    }
};
