<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialRecorrido extends Model
{
    use HasFactory;

    protected $table = 'historial_recorridos';

    protected $fillable = [
        'usuario_id',
        'ruta_id',
        'micro_id',
        'origen_latitud',
        'origen_longitud',
        'destino_latitud',
        'destino_longitud',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'origen_latitud' => 'float',
        'origen_longitud' => 'float',
        'destino_latitud' => 'float',
        'destino_longitud' => 'float',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class, 'ruta_id');
    }

    public function micro(): BelongsTo
    {
        return $this->belongsTo(Micro::class, 'micro_id');
    }
}
