<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Micro extends Model
{
    use HasFactory;

    protected $fillable = ['empresa_id', 'placa', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function conductor()
    {
        return $this->hasOne(Conductor::class);
    }

    public function posiciones()
    {
        return $this->hasMany(Posicion::class);
    }

    public function ultimaPosicion()
    {
        return $this->hasOne(Posicion::class)->latestOfMany('recorded_at');
    }
}