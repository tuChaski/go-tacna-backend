<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConductorMicroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conductor_perfil_id' => $this->conductor_perfil_id,
            'micro_id' => $this->micro_id,
            'fecha_inicio' => $this->fecha_inicio?->toISOString(),
            'fecha_inicio_estimada' => $this->fecha_inicio_estimada,
            'fecha_fin' => $this->fecha_fin?->toISOString(),
            'conductor' => $this->when(
                $this->relationLoaded('conductor'),
                fn () => new ConductorPerfilResource($this->conductor)
            ),
            'micro' => $this->when(
                $this->relationLoaded('micro'),
                fn () => new MicroResource($this->micro)
            ),
        ];
    }
}
