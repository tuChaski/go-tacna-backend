<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_vencimiento');
            $table->enum('estado', ['activa', 'vencida', 'cancelada'])->default('activa');
            $table->timestamps();

            $table->index(['usuario_id', 'estado']);
            $table->index(['plan_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};
