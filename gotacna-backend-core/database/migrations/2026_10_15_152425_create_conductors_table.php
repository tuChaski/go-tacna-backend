<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conductores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('micro_id')->nullable()->constrained('micros')->nullOnDelete();
            $table->foreignId('ruta_id')->nullable()->constrained('rutas')->nullOnDelete();
            $table->timestamps();

            // Un usuario solo puede tener un registro de conductor activo
            $table->unique('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conductores');
    }
};