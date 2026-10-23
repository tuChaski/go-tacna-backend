<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConductorPerfil extends Model
{
    use HasFactory;

    protected $table = 'conductor_perfiles';

    protected $fillable = [
        'usuario_id',
        'empresa_id',
        'licencia_conducir',
        'estado_operativo',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function microActual(): HasOne
    {
        return $this->hasOne(Micro::class, 'conductor_actual_id');
    }

    public function microsOperados(): HasMany
    {
        return $this->hasMany(Micro::class, 'conductor_actual_id');
    }
}
