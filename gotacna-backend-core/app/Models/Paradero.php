<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paradero extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'latitud', 'longitud'];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
    ];

    public function rutas()
    {
        return $this->belongsToMany(Ruta::class, 'paradero_ruta')
                    ->withPivot('orden');
    }
}