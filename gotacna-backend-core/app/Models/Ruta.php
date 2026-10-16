<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ruta extends Model
{
    use HasFactory;

    protected $fillable = ['empresa_id', 'nombre', 'descripcion', 'trazado'];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function paraderos()
    {
        return $this->belongsToMany(Paradero::class, 'paradero_ruta')
                    ->withPivot('orden')
                    ->orderBy('pivot_orden');
    }

    public function conductores()
    {
        return $this->hasMany(Conductor::class);
    }
}