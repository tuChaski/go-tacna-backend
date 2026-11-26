<?php

namespace Database\Factories;

use App\Models\ConductorPerfil;
use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConductorPerfil>
 */
class ConductorPerfilFactory extends Factory
{
    protected $model = ConductorPerfil::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory()->conductor(),
            'empresa_id' => Empresa::factory(),
            'licencia_conducir' => fake()->bothify('Q#########'),
            'estado_operativo' => 'sin_conexion',
        ];
    }

    public function disponible(): static
    {
        return $this->state(fn () => ['estado_operativo' => 'disponible']);
    }
}
