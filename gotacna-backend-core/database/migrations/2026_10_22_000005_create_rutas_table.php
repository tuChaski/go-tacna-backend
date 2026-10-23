<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::create('rutas', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->string('nombre_ruta');

            // Fallback portable para pruebas (SQLite/MySQL). En PostgreSQL se
            // agrega la columna geometry real después de crear la tabla.
            if ($driver !== 'pgsql') {
                $table->json('trazado_ruta')->nullable();
            }

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre_ruta']);
        });

        if ($driver === 'pgsql') {
            $postgis = DB::selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'postgis'");

            if (! $postgis) {
                throw new RuntimeException(
                    'PostGIS no está habilitado en la base de datos. Ejecuta CREATE EXTENSION postgis antes de migrar.'
                );
            }

            DB::statement('ALTER TABLE rutas ADD COLUMN trazado_ruta geometry(LineString, 4326) NULL');
            DB::statement('CREATE INDEX rutas_trazado_ruta_gist ON rutas USING GIST (trazado_ruta)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rutas');
    }
};
