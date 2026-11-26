<?php

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition(): array
    {
        return [
            'dni' => fake()->unique()->numerify('########'),
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'correo' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('9########'),
            'password_hash' => 'password',
            'rol' => 'pasajero',
            'activo' => true,
        ];
    }

    public function pasajero(): static
    {
        return $this->state(fn () => ['rol' => 'pasajero']);
    }

    public function conductor(): static
    {
        return $this->state(fn () => ['rol' => 'conductor']);
    }

    public function adminGeneral(): static
    {
        return $this->state(fn () => ['rol' => 'admin_general']);
    }

    public function adminEmpresa(): static
    {
        return $this->state(fn () => ['rol' => 'admin_empresa']);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
