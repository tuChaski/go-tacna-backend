<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoritoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'ruta_id' => $this->ruta_id,
            'micro_id' => $this->micro_id,
            'ruta' => $this->when(
                $this->relationLoaded('ruta') && $this->ruta,
                fn () => new RutaResource($this->ruta)
            ),
            'micro' => $this->when(
                $this->relationLoaded('micro') && $this->micro,
                fn () => new MicroResource($this->micro)
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
