<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Micro extends Model
{
    use HasFactory;

    protected $table = 'micros';

    protected $fillable = [
        'empresa_id',
        'ruta_id',
        'conductor_actual_id',
        'placa',
        'capacidad_pasajeros',
        'estado',
    ];

    protected $casts = [
        'capacidad_pasajeros' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class, 'ruta_id');
    }

    public function conductorActual(): BelongsTo
    {
        return $this->belongsTo(ConductorPerfil::class, 'conductor_actual_id');
    }

    public function posiciones(): HasMany
    {
        return $this->hasMany(PosicionGps::class, 'micro_id');
    }

    public function ultimaPosicion(): HasOne
    {
        return $this->hasOne(PosicionGps::class, 'micro_id')->latestOfMany('fecha_hora');
    }
}
