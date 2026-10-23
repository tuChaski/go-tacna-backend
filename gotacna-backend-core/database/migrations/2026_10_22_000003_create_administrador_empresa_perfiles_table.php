<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrador_empresa_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('cargo')->nullable();
            $table->timestamps();

            $table->unique(['usuario_id', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrador_empresa_perfiles');
    }
};
