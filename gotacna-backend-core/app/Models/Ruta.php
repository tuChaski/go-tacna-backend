<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Ruta extends Model
{
    use HasFactory;

    protected $table = 'rutas';

    protected $fillable = [
        'empresa_id',
        'nombre_ruta',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function micros(): HasMany
    {
        return $this->hasMany(Micro::class, 'ruta_id');
    }

    /**
     * Devuelve el trazado como GeoJSON sin importar el motor de base de datos.
     */
    public function getTrazadoRutaGeojsonAttribute(): ?array
    {
        if (array_key_exists('trazado_ruta_geojson', $this->attributes)) {
            $value = $this->attributes['trazado_ruta_geojson'];

            return is_string($value) ? json_decode($value, true) : $value;
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $value = $this->attributes['trazado_ruta'] ?? null;

            if ($value === null) {
                return null;
            }

            return is_string($value) ? json_decode($value, true) : $value;
        }

        if (! $this->exists) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(trazado_ruta) AS geojson FROM rutas WHERE id = ?',
            [$this->getKey()]
        );

        return $row && $row->geojson ? json_decode($row->geojson, true) : null;
    }

    /**
     * Guarda GeoJSON como geometry(LineString, 4326) en PostgreSQL o como JSON en el resto.
     */
    public static function guardarTrazado(int $rutaId, ?array $geojson): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            if ($geojson === null) {
                DB::table('rutas')->where('id', $rutaId)->update([
                    'trazado_ruta' => null,
                    'updated_at' => now(),
                ]);

                return;
            }

            DB::statement(
                'UPDATE rutas SET trazado_ruta = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), updated_at = ? WHERE id = ?',
                [json_encode($geojson), now(), $rutaId]
            );

            return;
        }

        DB::table('rutas')->where('id', $rutaId)->update([
            'trazado_ruta' => $geojson === null ? null : json_encode($geojson),
            'updated_at' => now(),
        ]);
    }
}
