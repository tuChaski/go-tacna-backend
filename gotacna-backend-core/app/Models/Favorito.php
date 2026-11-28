<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorito extends Model
{
    use HasFactory;

    protected $table = 'favoritos';

    protected $fillable = [
        'usuario_id',
        'ruta_id',
        'micro_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (Favorito $favorito): void {
            if (($favorito->ruta_id === null) === ($favorito->micro_id === null)) {
                throw new \DomainException('Selecciona una ruta o un micro como favorito.');
            }
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class, 'ruta_id');
    }

    public function micro(): BelongsTo
    {
        return $this->belongsTo(Micro::class, 'micro_id');
    }
}
