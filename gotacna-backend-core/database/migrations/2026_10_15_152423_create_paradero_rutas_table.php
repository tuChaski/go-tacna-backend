<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paradero_ruta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruta_id')->constrained('rutas')->onDelete('cascade');
            $table->foreignId('paradero_id')->constrained('paraderos')->onDelete('cascade');
            $table->integer('orden');
            $table->timestamps();

            // Garantizar que no se repita el paradero o la secuencia dentro de la misma ruta
            $table->unique(['ruta_id', 'paradero_id']);
            $table->unique(['ruta_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paradero_ruta');
    }
};