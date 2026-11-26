<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RutaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'nombre_ruta' => $this->nombre_ruta,
            'trazado_ruta' => $this->trazado_ruta_geojson,
            'activo' => (bool) $this->activo,
            'empresa' => $this->when(
                $this->relationLoaded('empresa'),
                fn () => new EmpresaResource($this->empresa)
            ),
            'micros' => $this->when(
                $this->relationLoaded('micros'),
                fn () => MicroResource::collection($this->micros)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
