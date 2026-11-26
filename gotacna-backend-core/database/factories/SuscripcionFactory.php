<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suscripcion>
 */
class SuscripcionFactory extends Factory
{
    protected $model = Suscripcion::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory()->pasajero(),
            'plan_id' => Plan::factory(),
            'fecha_inicio' => now()->toDateString(),
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'estado' => 'activa',
        ];
    }

    public function cancelada(): static
    {
        return $this->state(fn () => ['estado' => 'cancelada']);
    }
}
