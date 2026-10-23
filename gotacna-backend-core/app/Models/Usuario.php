<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'dni',
        'nombre',
        'apellido',
        'correo',
        'telefono',
        'password_hash',
        'rol',
        'activo',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'password_hash' => 'hashed',
        ];
    }

    /**
     * Laravel valida las credenciales contra esta columna en lugar de `password`.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        $microActual = $this->conductorPerfil?->microActual;

        return [
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'correo' => $this->correo,
            'rol' => $this->rol,
            'micro_id' => $microActual?->id,
            'ruta_id' => $microActual?->ruta_id,
            'empresa_ids' => $this->rol === 'admin_empresa'
                ? $this->administradorEmpresaPerfiles->pluck('empresa_id')->all()
                : [],
        ];
    }

    public function administradorEmpresaPerfiles(): HasMany
    {
        return $this->hasMany(AdministradorEmpresaPerfil::class, 'usuario_id');
    }

    public function empresasAdministradas(): BelongsToMany
    {
        return $this->belongsToMany(
            Empresa::class,
            'administrador_empresa_perfiles',
            'usuario_id',
            'empresa_id'
        )->withPivot('cargo')->withTimestamps();
    }

    public function conductorPerfil(): HasOne
    {
        return $this->hasOne(ConductorPerfil::class, 'usuario_id');
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class, 'usuario_id');
    }

    public function microsOperados(): HasManyThrough
    {
        return $this->hasManyThrough(
            Micro::class,
            ConductorPerfil::class,
            'usuario_id',
            'conductor_actual_id',
            'id',
            'id'
        );
    }
}
