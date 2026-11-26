<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        return [
            'ruc' => fake()->unique()->numerify('###########'),
            'razon_social' => fake()->company(),
            'telefono' => fake()->numerify('9########'),
            'direccion' => fake()->address(),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
