<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MicroPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'ruta_id' => $this->ruta_id,
            'placa' => $this->placa,
            'capacidad_pasajeros' => $this->capacidad_pasajeros,
            'estado' => $this->estado,
            'empresa' => $this->when($this->relationLoaded('empresa') && $this->empresa,
                fn () => [
                    'id' => $this->empresa->id,
                    'razon_social' => $this->empresa->razon_social,
                ]),
            'ruta' => $this->when($this->relationLoaded('ruta') && $this->ruta,
                fn () => [
                    'id' => $this->ruta->id,
                    'nombre_ruta' => $this->ruta->nombre_ruta,
                ]),
            'ultima_posicion' => $this->when($this->relationLoaded('ultimaPosicion') && $this->ultimaPosicion,
                fn () => [
                    'latitud' => (float) $this->ultimaPosicion->latitud,
                    'longitud' => (float) $this->ultimaPosicion->longitud,
                    'velocidad' => (float) $this->ultimaPosicion->velocidad,
                    'fecha_hora' => $this->ultimaPosicion->fecha_hora?->toISOString(),
                ]),
        ];
    }
}
