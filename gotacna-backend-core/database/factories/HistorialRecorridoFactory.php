<?php

namespace Database\Factories;

use App\Models\HistorialRecorrido;
use App\Models\Ruta;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistorialRecorrido>
 */
class HistorialRecorridoFactory extends Factory
{
    protected $model = HistorialRecorrido::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory()->pasajero(),
            'ruta_id' => Ruta::factory(),
            'micro_id' => null,
            'fecha_inicio' => now(),
            'fecha_fin' => null,
        ];
    }
}
