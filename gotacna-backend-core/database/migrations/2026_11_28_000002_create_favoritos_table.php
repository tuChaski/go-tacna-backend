<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favoritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('ruta_id')->nullable()->constrained('rutas')->cascadeOnDelete();
            $table->foreignId('micro_id')->nullable()->constrained('micros')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['usuario_id', 'ruta_id']);
            $table->unique(['usuario_id', 'micro_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE favoritos ADD CONSTRAINT favoritos_un_objetivo_check CHECK ((ruta_id IS NULL) <> (micro_id IS NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('favoritos');
    }
};
