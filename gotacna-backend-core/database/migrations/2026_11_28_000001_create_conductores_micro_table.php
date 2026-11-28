<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conductores_micro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conductor_perfil_id')->constrained('conductor_perfiles')->restrictOnDelete();
            $table->foreignId('micro_id')->constrained('micros')->restrictOnDelete();
            $table->timestamp('fecha_inicio');
            $table->boolean('fecha_inicio_estimada')->default(false);
            $table->timestamp('fecha_fin')->nullable();
            $table->timestamps();

            $table->index(['conductor_perfil_id', 'fecha_inicio']);
            $table->index(['micro_id', 'fecha_inicio']);
        });

        DB::statement('CREATE UNIQUE INDEX conductores_micro_micro_activo_unique ON conductores_micro (micro_id) WHERE fecha_fin IS NULL');
        DB::statement('CREATE UNIQUE INDEX conductores_micro_conductor_activo_unique ON conductores_micro (conductor_perfil_id) WHERE fecha_fin IS NULL');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE conductores_micro ADD CONSTRAINT conductores_micro_periodo_check CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)');
        }

        $fechaRegistro = now();
        DB::table('micros')->whereNotNull('conductor_actual_id')->orderBy('id')->chunkById(100, function ($micros) use ($fechaRegistro) {
            foreach ($micros as $micro) {
                DB::table('conductores_micro')->insert([
                    'conductor_perfil_id' => $micro->conductor_actual_id,
                    'micro_id' => $micro->id,
                    'fecha_inicio' => $fechaRegistro,
                    'fecha_inicio_estimada' => true,
                    'created_at' => $fechaRegistro,
                    'updated_at' => $fechaRegistro,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conductores_micro');
    }
};
