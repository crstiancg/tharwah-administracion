<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contenido de la tienda web que no es de un producto: una fila por
        // sección (aviso, carrusel, preguntas…). La forma del JSON la fija
        // App\Http\Requests\GuardarContenidoWebRequest según la clave.
        Schema::create('contenido_web', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            // Lo que se está editando y lo que ve la web: la web sólo lee
            // `publicado`, así un cambio a medias nunca sale al público.
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
        Schema::dropIfExists('contenido_web');
    }
};
