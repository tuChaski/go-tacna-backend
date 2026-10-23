<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Ruta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ruta>
 */
class RutaFactory extends Factory
{
    protected $model = Ruta::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nombre_ruta' => fake()->unique()->bothify('Ruta #####'),
            'activo' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Ruta $ruta) {
            Ruta::guardarTrazado($ruta->id, [
                'type' => 'LineString',
                'coordinates' => [
                    [-70.246500, -18.014000],
                    [-70.241000, -18.019500],
                    [-70.235500, -18.025000],
                ],
            ]);
        });
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
