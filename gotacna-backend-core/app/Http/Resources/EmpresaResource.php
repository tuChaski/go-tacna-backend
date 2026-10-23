<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpresaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ruc' => $this->ruc,
            'razon_social' => $this->razon_social,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'activo' => (bool) $this->activo,
            'rutas' => $this->when(
                $this->relationLoaded('rutas'),
                fn () => RutaResource::collection($this->rutas)
            ),
            'micros' => $this->when(
                $this->relationLoaded('micros'),
                fn () => MicroResource::collection($this->micros)
            ),
            'conductores' => $this->when(
                $this->relationLoaded('conductorPerfiles'),
                fn () => ConductorPerfilResource::collection($this->conductorPerfiles)
            ),
            'administradores' => $this->when(
                $this->relationLoaded('administradorEmpresaPerfiles'),
                fn () => AdministradorEmpresaPerfilResource::collection($this->administradorEmpresaPerfiles)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
