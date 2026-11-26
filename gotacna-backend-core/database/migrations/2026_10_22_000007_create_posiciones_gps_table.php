<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posiciones_gps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('micro_id')->constrained('micros')->cascadeOnDelete();
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            $table->float('velocidad')->default(0);
            $table->timestamp('fecha_hora');
            $table->timestamps();

            $table->index(['micro_id', 'fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posiciones_gps');
    }
};
