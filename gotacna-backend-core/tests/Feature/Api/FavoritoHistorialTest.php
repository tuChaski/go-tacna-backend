<?php

namespace Tests\Feature\Api;

use App\Models\Empresa;
use App\Models\Micro;
use App\Models\Ruta;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoritoHistorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_pasajero_guarda_favoritos_sin_duplicarlos_y_otro_no_puede_borrarlos(): void
    {
        $pasajero = Usuario::factory()->pasajero()->create();
        $otro = Usuario::factory()->pasajero()->create();
        $ruta = Ruta::factory()->create();
        $micro = Micro::factory()->create();
        $headers = $this->headersPara($pasajero);

        $favoritoId = $this->withHeaders($headers)
            ->postJson('/api/v1/mis-favoritos', ['ruta_id' => $ruta->id])
            ->assertCreated()->json('data.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/mis-favoritos', ['ruta_id' => $ruta->id])
            ->assertOk()->assertJsonPath('data.id', $favoritoId);

        $this->withHeaders($headers)
            ->postJson('/api/v1/mis-favoritos', ['ruta_id' => $ruta->id, 'micro_id' => $micro->id])
            ->assertUnprocessable();

        $this->withHeaders($headers)->getJson('/api/v1/mis-favoritos')
            ->assertOk()->assertJsonPath('meta.total', 1);

        app('tymon.jwt')->unsetToken();
        app('auth')->forgetGuards();
        $this->withHeaders($this->headersPara($otro))
            ->deleteJson('/api/v1/mis-favoritos/'.$favoritoId)
            ->assertNotFound();

        app('tymon.jwt')->unsetToken();
        app('auth')->forgetGuards();
        $this->withHeaders($headers)
            ->deleteJson('/api/v1/mis-favoritos/'.$favoritoId)
            ->assertOk();
        $this->assertDatabaseCount('favoritos', 0);
    }

    public function test_pasajero_registra_y_finaliza_su_viaje_sin_exponerlo_a_otros(): void
    {
        $pasajero = Usuario::factory()->pasajero()->create();
        $otro = Usuario::factory()->pasajero()->create();
        $empresa = Empresa::factory()->create();
        $ruta = Ruta::factory()->create(['empresa_id' => $empresa->id]);
        $otraRuta = Ruta::factory()->create(['empresa_id' => $empresa->id]);
        $micro = Micro::factory()->create(['empresa_id' => $empresa->id, 'ruta_id' => $ruta->id]);
        $headers = $this->headersPara($pasajero);

        $this->withHeaders($headers)->postJson('/api/v1/mi-historial', [
            'ruta_id' => $otraRuta->id,
            'micro_id' => $micro->id,
        ])->assertUnprocessable();

        $recorridoId = $this->withHeaders($headers)->postJson('/api/v1/mi-historial', [
            'ruta_id' => $ruta->id,
            'micro_id' => $micro->id,
            'origen_latitud' => -18.0145,
            'origen_longitud' => -70.2460,
        ])->assertCreated()->json('data.id');

        app('tymon.jwt')->unsetToken();
        app('auth')->forgetGuards();
        $this->withHeaders($this->headersPara($otro))
            ->getJson('/api/v1/mi-historial/'.$recorridoId)->assertNotFound();
        $this->withHeaders($this->headersPara($otro))
            ->patchJson('/api/v1/mi-historial/'.$recorridoId.'/finalizar')->assertNotFound();

        app('tymon.jwt')->unsetToken();
        app('auth')->forgetGuards();
        $this->withHeaders($headers)->patchJson('/api/v1/mi-historial/'.$recorridoId.'/finalizar', [
            'destino_latitud' => -18.0200,
            'destino_longitud' => -70.2500,
        ])->assertOk()->assertJsonPath('data.destino_latitud', -18.02);

        $this->withHeaders($headers)->patchJson('/api/v1/mi-historial/'.$recorridoId.'/finalizar')
            ->assertStatus(409);
        $this->withHeaders($headers)->getJson('/api/v1/mi-historial')
            ->assertOk()->assertJsonPath('meta.total', 1);
    }
}
