<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdministradorEmpresaPerfilResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'empresa_id' => $this->empresa_id,
            'cargo' => $this->cargo,
            'usuario' => $this->when(
                $this->relationLoaded('usuario'),
                fn () => new UsuarioResource($this->usuario)
            ),
            'empresa' => $this->when(
                $this->relationLoaded('empresa'),
                fn () => new EmpresaResource($this->empresa)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
