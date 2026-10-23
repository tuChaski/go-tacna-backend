<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuscripcionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'plan_id' => $this->plan_id,
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'estado' => $this->estado,
            'usuario' => $this->when(
                $this->relationLoaded('usuario'),
                fn () => new UsuarioResource($this->usuario)
            ),
            'plan' => $this->when(
                $this->relationLoaded('plan'),
                fn () => new PlanResource($this->plan)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
