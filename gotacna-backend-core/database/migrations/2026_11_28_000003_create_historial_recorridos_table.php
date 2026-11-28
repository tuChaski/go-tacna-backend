<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_recorridos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('ruta_id')->constrained('rutas')->restrictOnDelete();
            $table->foreignId('micro_id')->nullable()->constrained('micros')->restrictOnDelete();
            $table->decimal('origen_latitud', 10, 8)->nullable();
            $table->decimal('origen_longitud', 11, 8)->nullable();
            $table->decimal('destino_latitud', 10, 8)->nullable();
            $table->decimal('destino_longitud', 11, 8)->nullable();
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'fecha_inicio']);
            $table->index(['ruta_id', 'fecha_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_recorridos');
    }
};
