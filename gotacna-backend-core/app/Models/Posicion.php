<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Posicion extends Model
{
    use HasFactory;

    protected $table = 'posiciones';

    protected $fillable = ['micro_id', 'latitud', 'longitud', 'velocidad', 'recorded_at'];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'velocidad' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function micro()
    {
        return $this->belongsTo(Micro::class);
    }
}