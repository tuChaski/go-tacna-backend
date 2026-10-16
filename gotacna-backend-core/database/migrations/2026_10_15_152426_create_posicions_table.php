<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posiciones', function (Blueprint $table) {
            $table->id(); // Bigint autoincremental
            $table->foreignId('micro_id')->constrained('micros')->onDelete('cascade');
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            $table->float('velocidad')->default(0.0);
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Índice para acelerar consultas de telemetría por micro y fecha
            $table->index(['micro_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posiciones');
    }
};