<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('micros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('ruta_id')->nullable()->constrained('rutas')->nullOnDelete();
            $table->foreignId('conductor_actual_id')
                ->nullable()
                ->constrained('conductor_perfiles')
                ->nullOnDelete();
            $table->string('placa', 20)->unique();
            $table->unsignedSmallInteger('capacidad_pasajeros')->default(20);
            $table->enum('estado', ['disponible', 'en_recorrido', 'mantenimiento', 'inactivo'])
                ->default('disponible');
            $table->timestamps();

            // Un conductor no puede estar asignado a dos micros a la vez.
            $table->unique('conductor_actual_id');
            $table->index(['empresa_id', 'estado']);
            $table->index(['ruta_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micros');
    }
};
