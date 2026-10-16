<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ParaderoRuta extends Pivot
{
    protected $table = 'paradero_ruta';

    protected $fillable = ['ruta_id', 'paradero_id', 'orden'];
}