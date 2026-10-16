<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function rutas()
    {
        return $this->hasMany(Ruta::class);
    }

    public function micros()
    {
        return $this->hasMany(Micro::class);
    }
}