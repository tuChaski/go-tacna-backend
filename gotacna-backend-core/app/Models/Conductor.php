<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conductor extends Model
{
    use HasFactory;

    protected $table = 'conductores';

    protected $fillable = ['usuario_id', 'micro_id', 'ruta_id'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function micro()
    {
        return $this->belongsTo(Micro::class);
    }

    public function ruta()
    {
        return $this->belongsTo(Ruta::class);
    }
}