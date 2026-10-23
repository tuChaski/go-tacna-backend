<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';

    protected $fillable = [
        'ruc',
        'razon_social',
        'telefono',
        'direccion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function rutas(): HasMany
    {
        return $this->hasMany(Ruta::class, 'empresa_id');
    }

    public function micros(): HasMany
    {
        return $this->hasMany(Micro::class, 'empresa_id');
    }

    public function conductorPerfiles(): HasMany
    {
        return $this->hasMany(ConductorPerfil::class, 'empresa_id');
    }

    public function administradorEmpresaPerfiles(): HasMany
    {
        return $this->hasMany(AdministradorEmpresaPerfil::class, 'empresa_id');
    }

    public function administradores(): BelongsToMany
    {
        return $this->belongsToMany(
            Usuario::class,
            'administrador_empresa_perfiles',
            'empresa_id',
            'usuario_id'
        )->withPivot('cargo')->withTimestamps();
    }
}
