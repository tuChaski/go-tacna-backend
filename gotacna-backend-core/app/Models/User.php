<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject; // 1. Importamos el contrato de JWT

class User extends Authenticatable implements JWTSubject // 2. Implementamos el contrato
{
    use HasFactory, Notifiable;

    /**
     * Atributos asignables de forma masiva.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'activo',
    ];

    /**
     * Atributos ocultos en serialización.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversión de tipos (Estructura moderna de Laravel 11/12/13).
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * 3. OBLIGATORIO JWT: Obtener el identificador del usuario para el token.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * 4. OBLIGATORIO JWT: Añadir la información requerida (claims) dentro del token.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'name'   => $this->name,
            'email'  => $this->email,
            'role'   => $this->role,
            'activo' => $this->activo,
        ];
    }
}