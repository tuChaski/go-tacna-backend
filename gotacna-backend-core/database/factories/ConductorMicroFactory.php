<?php

namespace Database\Factories;

use App\Models\ConductorMicro;
use App\Models\ConductorPerfil;
use App\Models\Micro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConductorMicro>
 */
class ConductorMicroFactory extends Factory
{
    protected $model = ConductorMicro::class;

    public function definition(): array
    {
        return [
            'conductor_perfil_id' => ConductorPerfil::factory(),
            'micro_id' => Micro::factory(),
            'fecha_inicio' => now(),
            'fecha_fin' => null,
        ];
    }
}
