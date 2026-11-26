<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Micro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Micro>
 */
class MicroFactory extends Factory
{
    protected $model = Micro::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'ruta_id' => null,
            'conductor_actual_id' => null,
            'placa' => fake()->unique()->bothify('???-###'),
            'capacidad_pasajeros' => 20,
            'estado' => 'disponible',
        ];
    }

    public function enRecorrido(): static
    {
        return $this->state(fn () => ['estado' => 'en_recorrido']);
    }

    public function mantenimiento(): static
    {
        return $this->state(fn () => ['estado' => 'mantenimiento']);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['estado' => 'inactivo']);
    }
}
