<?php

namespace Database\Factories;

use App\Models\Favorito;
use App\Models\Ruta;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorito>
 */
class FavoritoFactory extends Factory
{
    protected $model = Favorito::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory()->pasajero(),
            'ruta_id' => Ruta::factory(),
            'micro_id' => null,
        ];
    }
}
