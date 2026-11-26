<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dni' => $this->dni,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'correo' => $this->correo,
            'telefono' => $this->telefono,
            'rol' => $this->rol,
            'activo' => (bool) $this->activo,
            'conductor_perfil' => $this->when(
                $this->relationLoaded('conductorPerfil') && $this->conductorPerfil,
                fn () => new ConductorPerfilResource($this->conductorPerfil)
            ),
            'administrador_empresa_perfiles' => $this->when(
                $this->relationLoaded('administradorEmpresaPerfiles'),
                fn () => AdministradorEmpresaPerfilResource::collection($this->administradorEmpresaPerfiles)
            ),
            'empresas' => $this->when(
                $this->relationLoaded('empresasAdministradas'),
                fn () => EmpresaResource::collection($this->empresasAdministradas)
            ),
            'suscripciones' => $this->when(
                $this->relationLoaded('suscripciones'),
                fn () => SuscripcionResource::collection($this->suscripciones)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
