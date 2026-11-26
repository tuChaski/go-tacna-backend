<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MicroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'ruta_id' => $this->ruta_id,
            'conductor_actual_id' => $this->conductor_actual_id,
            'placa' => $this->placa,
            'capacidad_pasajeros' => $this->capacidad_pasajeros,
            'estado' => $this->estado,
            'empresa' => $this->when(
                $this->relationLoaded('empresa'),
                fn () => new EmpresaResource($this->empresa)
            ),
            'ruta' => $this->when(
                $this->relationLoaded('ruta') && $this->ruta,
                fn () => new RutaResource($this->ruta)
            ),
            'conductor_actual' => $this->when(
                $this->relationLoaded('conductorActual') && $this->conductorActual,
                fn () => new ConductorPerfilResource($this->conductorActual)
            ),
            'ultima_posicion' => $this->when(
                $this->relationLoaded('ultimaPosicion') && $this->ultimaPosicion,
                fn () => new PosicionGpsResource($this->ultimaPosicion)
            ),
            'posiciones' => $this->when(
                $this->relationLoaded('posiciones'),
                fn () => PosicionGpsResource::collection($this->posiciones)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
