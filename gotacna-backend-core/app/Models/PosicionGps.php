<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosicionGps extends Model
{
    use HasFactory;

    protected $table = 'posiciones_gps';

    protected $fillable = [
        'micro_id',
        'latitud',
        'longitud',
        'velocidad',
        'fecha_hora',
    ];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'velocidad' => 'float',
        'fecha_hora' => 'datetime',
    ];

    public function micro(): BelongsTo
    {
        return $this->belongsTo(Micro::class, 'micro_id');
    }
}
