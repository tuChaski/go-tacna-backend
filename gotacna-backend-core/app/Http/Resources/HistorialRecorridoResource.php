<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistorialRecorridoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'ruta_id' => $this->ruta_id,
            'micro_id' => $this->micro_id,
            'origen_latitud' => $this->origen_latitud,
            'origen_longitud' => $this->origen_longitud,
            'destino_latitud' => $this->destino_latitud,
            'destino_longitud' => $this->destino_longitud,
            'fecha_inicio' => $this->fecha_inicio?->toISOString(),
            'fecha_fin' => $this->fecha_fin?->toISOString(),
            'ruta' => $this->when(
                $this->relationLoaded('ruta'),
                fn () => new RutaResource($this->ruta)
            ),
            'micro' => $this->when(
                $this->relationLoaded('micro') && $this->micro,
                fn () => new MicroResource($this->micro)
            ),
        ];
    }
}
