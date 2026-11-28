<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConductorMicro extends Model
{
    use HasFactory;

    protected $table = 'conductores_micro';

    protected $fillable = [
        'conductor_perfil_id',
        'micro_id',
        'fecha_inicio',
        'fecha_inicio_estimada',
        'fecha_fin',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_inicio_estimada' => 'boolean',
        'fecha_fin' => 'datetime',
    ];

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(ConductorPerfil::class, 'conductor_perfil_id');
    }

    public function micro(): BelongsTo
    {
        return $this->belongsTo(Micro::class, 'micro_id');
    }
}
