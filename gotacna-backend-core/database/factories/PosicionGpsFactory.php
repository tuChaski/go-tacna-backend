<?php

namespace Database\Factories;

use App\Models\Micro;
use App\Models\PosicionGps;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosicionGps>
 */
class PosicionGpsFactory extends Factory
{
    protected $model = PosicionGps::class;

    public function definition(): array
    {
        return [
            'micro_id' => Micro::factory(),
            'latitud' => fake()->latitude(-18.05, -17.95),
            'longitud' => fake()->longitude(-70.30, -70.20),
            'velocidad' => fake()->randomFloat(2, 0, 60),
            'fecha_hora' => now(),
        ];
    }
}
