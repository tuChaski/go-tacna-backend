<?php

namespace Tests\Feature\Api;

use App\Models\ConductorPerfil;
use App\Models\Micro;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConductorTest extends TestCase
{
    use RefreshDatabase;

    public function test_conductor_obtiene_su_perfil(): void
    {
        $conductor = ConductorPerfil::factory()->disponible()->create();
        $micro = Micro::factory()->create([
            'empresa_id' => $conductor->empresa_id,
            'conductor_actual_id' => $conductor->id,
        ]);

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->getJson('/api/v1/conductor/perfil')
            ->assertOk()
            ->assertJsonPath('data.id', $conductor->id)
            ->assertJsonPath('data.micro_actual.id', $micro->id);
    }

    public function test_conductor_reporta_posicion_y_actualiza_estados(): void
    {
        $conductor = ConductorPerfil::factory()->disponible()->create();
        $micro = Micro::factory()->create([
            'empresa_id' => $conductor->empresa_id,
            'conductor_actual_id' => $conductor->id,
            'estado' => 'disponible',
        ]);

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/conductor/posicion', [
                'latitud' => -18.0145,
                'longitud' => -70.2460,
                'velocidad' => 32.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.micro_id', $micro->id)
            ->assertJsonPath('data.latitud', -18.0145);

        $this->assertDatabaseHas('posiciones_gps', [
            'micro_id' => $micro->id,
            'latitud' => -18.0145,
        ]);
        $this->assertDatabaseHas('micros', [
            'id' => $micro->id,
            'estado' => 'en_recorrido',
        ]);
        $this->assertDatabaseHas('conductor_perfiles', [
            'id' => $conductor->id,
            'estado_operativo' => 'en_recorrido',
        ]);
    }

    public function test_conductor_finaliza_recorrido(): void
    {
        $conductor = ConductorPerfil::factory()->create([
            'estado_operativo' => 'en_recorrido',
        ]);
        $micro = Micro::factory()->enRecorrido()->create([
            'empresa_id' => $conductor->empresa_id,
            'conductor_actual_id' => $conductor->id,
        ]);

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/conductor/finalizar')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.micro_id', $micro->id)
            ->assertJsonPath('data.estado_operativo', 'sin_conexion');

        $this->assertDatabaseHas('micros', [
            'id' => $micro->id,
            'estado' => 'disponible',
        ]);
        $this->assertDatabaseHas('conductor_perfiles', [
            'id' => $conductor->id,
            'estado_operativo' => 'sin_conexion',
        ]);
    }

    public function test_conductor_sin_micro_no_puede_reportar_posicion(): void
    {
        $conductor = ConductorPerfil::factory()->create();

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/conductor/posicion', [
                'latitud' => -18.0145,
                'longitud' => -70.2460,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_pasajero_no_puede_acceder_al_modulo_conductor(): void
    {
        $pasajero = Usuario::factory()->pasajero()->create();

        $this->withHeaders($this->headersPara($pasajero))
            ->getJson('/api/v1/conductor/perfil')
            ->assertForbidden();
    }

    public function test_conductor_sin_perfil_no_puede_consultar_su_perfil(): void
    {
        $usuario = Usuario::factory()->conductor()->create();

        $this->withHeaders($this->headersPara($usuario))
            ->getJson('/api/v1/conductor/perfil')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_alias_de_tracking_reporta_posicion(): void
    {
        $conductor = ConductorPerfil::factory()->disponible()->create();
        Micro::factory()->create([
            'empresa_id' => $conductor->empresa_id,
            'conductor_actual_id' => $conductor->id,
        ]);

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/tracking/position', [
                'lat' => -18.0200,
                'lng' => -70.2500,
                'speed' => 12,
            ])
            ->assertCreated()
            ->assertJsonPath('data.latitud', -18.02);
    }
}
