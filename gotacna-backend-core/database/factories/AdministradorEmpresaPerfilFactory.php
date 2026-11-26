<?php

namespace Database\Factories;

use App\Models\AdministradorEmpresaPerfil;
use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdministradorEmpresaPerfil>
 */
class AdministradorEmpresaPerfilFactory extends Factory
{
    protected $model = AdministradorEmpresaPerfil::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory()->adminEmpresa(),
            'empresa_id' => Empresa::factory(),
            'cargo' => fake()->jobTitle(),
        ];
    }
}
