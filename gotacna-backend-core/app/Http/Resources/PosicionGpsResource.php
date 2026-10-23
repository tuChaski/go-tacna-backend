<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosicionGpsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'micro_id' => $this->micro_id,
            'latitud' => (float) $this->latitud,
            'longitud' => (float) $this->longitud,
            'velocidad' => (float) $this->velocidad,
            'fecha_hora' => $this->fecha_hora?->toISOString(),
            'micro' => $this->when(
                $this->relationLoaded('micro'),
                fn () => new MicroResource($this->micro)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
