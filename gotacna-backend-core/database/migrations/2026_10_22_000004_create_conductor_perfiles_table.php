<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conductor_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->string('licencia_conducir');
            $table->enum('estado_operativo', ['disponible', 'en_recorrido', 'sin_conexion'])
                ->default('sin_conexion');
            $table->timestamps();

            $table->unique('usuario_id');
            $table->index(['empresa_id', 'estado_operativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conductor_perfiles');
    }
};
