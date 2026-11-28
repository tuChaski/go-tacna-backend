<?php

namespace Tests\Feature\Api;

use App\Models\ConductorPerfil;
use App\Models\Micro;
use App\Models\Ruta;
use App\Models\Usuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

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

    public function test_asignacion_actual_rechaza_cuenta_inactiva_y_asignacion_retirada(): void
    {
        $conductor = ConductorPerfil::factory()->create();
        $ruta = Ruta::factory()->create(['empresa_id' => $conductor->empresa_id]);
        $micro = Micro::factory()->create([
            'empresa_id' => $conductor->empresa_id,
            'ruta_id' => $ruta->id,
            'conductor_actual_id' => $conductor->id,
        ]);
        $headers = $this->headersPara($conductor->usuario);

        $this->withHeaders($headers)->getJson('/api/v1/conductor/asignacion-actual')
            ->assertOk()->assertExactJson(['data' => [
                'micro_id' => $micro->id,
                'ruta_id' => $ruta->id,
                'empresa_id' => $conductor->empresa_id,
            ]]);

        $micro->update(['conductor_actual_id' => null]);
        $this->withHeaders($headers)->getJson('/api/v1/conductor/asignacion-actual')->assertForbidden();

        $conductor->usuario->update(['activo' => false]);
        app('tymon.jwt')->unsetToken();
        app('auth')->forgetGuards();
        $this->withHeaders($headers)->getJson('/api/v1/conductor/asignacion-actual')->assertForbidden();
    }

    public function test_conductor_reporta_posicion_y_actualiza_estados(): void
    {
        Http::fake([
            '*/tracking/start' => Http::response(['ok' => true]),
            '*/tracking/position' => Http::response(['ok' => true], 202),
        ]);
        $conductor = ConductorPerfil::factory()->disponible()->create();
        $ruta = Ruta::factory()->create(['empresa_id' => $conductor->empresa_id]);
        $micro = Micro::factory()->create([
            'empresa_id' => $conductor->empresa_id,
            'conductor_actual_id' => $conductor->id,
            'ruta_id' => $ruta->id,
            'estado' => 'disponible',
        ]);

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/conductor/posicion', [
                'latitud' => -18.0145,
                'longitud' => -70.2460,
            ])->assertStatus(409);

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/conductor/iniciar')
            ->assertOk()
            ->assertJsonPath('data.estado_operativo', 'en_recorrido');

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
        Http::assertSent(function ($request) use ($micro): bool {
            $header = $request->header('Authorization')[0] ?? '';
            if (! str_starts_with($header, 'Bearer ')) {
                return false;
            }

            $claims = JWTAuth::setToken(substr($header, 7))->getPayload();

            return $request->url() === config('services.realtime.url').'/tracking/position'
                && $request['lat'] === -18.0145
                && $request['lng'] === -70.2460
                && $request['speed'] === 32.5
                && $claims->get('realtime_bridge') === true
                && $claims->get('realtime_action') === 'position'
                && (int) $claims->get('empresa_id') === (int) $micro->empresa_id;
        });
        JWTAuth::unsetToken();
    }

    public function test_conductor_finaliza_recorrido(): void
    {
        Http::fake(['*/tracking/stop' => Http::response(['ok' => true])]);
        $conductor = ConductorPerfil::factory()->create([
            'estado_operativo' => 'en_recorrido',
        ]);
        $ruta = Ruta::factory()->create(['empresa_id' => $conductor->empresa_id]);
        $micro = Micro::factory()->enRecorrido()->create([
            'empresa_id' => $conductor->empresa_id,
            'conductor_actual_id' => $conductor->id,
            'ruta_id' => $ruta->id,
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
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/tracking/stop'));

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/tracking/position', [
                'lat' => -18.0145,
                'lng' => -70.2460,
                'speed' => 8.5,
            ])->assertStatus(409);
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
        Http::fake([
            '*/tracking/start' => Http::response(['ok' => true]),
            '*/tracking/position' => Http::response(['ok' => true], 202),
        ]);
        $conductor = ConductorPerfil::factory()->disponible()->create();
        $ruta = Ruta::factory()->create(['empresa_id' => $conductor->empresa_id]);
        Micro::factory()->create([
            'empresa_id' => $conductor->empresa_id,
            'conductor_actual_id' => $conductor->id,
            'ruta_id' => $ruta->id,
        ]);

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/conductor/iniciar')->assertOk();

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/tracking/position', [
                'lat' => -18.0200,
                'lng' => -70.2500,
                'speed' => 12,
            ])
            ->assertStatus(202)
            ->assertExactJson(['ok' => true]);

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/tracking/position', [
                'lat' => -18.0200,
                'lng' => -70.2500,
                'speed' => 12,
                'fecha_hora' => now()->toISOString(),
            ])
            ->assertStatus(422);
    }

    public function test_posicion_no_se_guarda_si_tiempo_real_no_responde(): void
    {
        Http::fake([
            '*/tracking/start' => Http::response(['ok' => true]),
            '*/tracking/position' => Http::response(['error' => 'Sin servicio'], 503),
        ]);
        $conductor = ConductorPerfil::factory()->disponible()->create();
        $ruta = Ruta::factory()->create(['empresa_id' => $conductor->empresa_id]);
        $micro = Micro::factory()->create([
            'empresa_id' => $conductor->empresa_id,
            'conductor_actual_id' => $conductor->id,
            'ruta_id' => $ruta->id,
        ]);

        $headers = $this->headersPara($conductor->usuario);
        $this->withHeaders($headers)->postJson('/api/v1/conductor/iniciar')->assertOk();
        $this->withHeaders($headers)->postJson('/api/v1/conductor/posicion', [
            'latitud' => -18.0145,
            'longitud' => -70.2460,
        ])->assertStatus(503);
        $this->assertDatabaseMissing('posiciones_gps', ['micro_id' => $micro->id]);
    }

    public function test_finalizacion_no_cambia_estados_si_tiempo_real_falla(): void
    {
        Http::fake(['*/tracking/stop' => Http::response(['error' => 'Sin servicio'], 503)]);
        $conductor = ConductorPerfil::factory()->create(['estado_operativo' => 'en_recorrido']);
        $ruta = Ruta::factory()->create(['empresa_id' => $conductor->empresa_id]);
        $micro = Micro::factory()->enRecorrido()->create([
            'empresa_id' => $conductor->empresa_id,
            'conductor_actual_id' => $conductor->id,
            'ruta_id' => $ruta->id,
        ]);

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/tracking/stop')->assertStatus(503);
        $this->assertDatabaseHas('micros', ['id' => $micro->id, 'estado' => 'en_recorrido']);
        $this->assertDatabaseHas('conductor_perfiles', [
            'id' => $conductor->id,
            'estado_operativo' => 'en_recorrido',
        ]);
    }

    public function test_inicio_no_cambia_estados_si_tiempo_real_falla(): void
    {
        Http::fake(['*/tracking/start' => Http::response(['error' => 'Sin servicio'], 503)]);
        $conductor = ConductorPerfil::factory()->disponible()->create();
        $ruta = Ruta::factory()->create(['empresa_id' => $conductor->empresa_id]);
        $micro = Micro::factory()->create([
            'empresa_id' => $conductor->empresa_id,
            'conductor_actual_id' => $conductor->id,
            'ruta_id' => $ruta->id,
            'estado' => 'disponible',
        ]);

        $this->withHeaders($this->headersPara($conductor->usuario))
            ->postJson('/api/v1/conductor/iniciar')->assertStatus(503);
        $this->assertDatabaseHas('micros', ['id' => $micro->id, 'estado' => 'disponible']);
        $this->assertDatabaseHas('conductor_perfiles', [
            'id' => $conductor->id,
            'estado_operativo' => 'disponible',
        ]);
    }

    public function test_api_y_tracking_limitan_peticiones(): void
    {
        $key = bin2hex(random_bytes(8));

        try {
            RateLimiter::for('api', fn () => Limit::perMinute(2)->by('api-test-'.$key));
            $this->getJson('/api/v1/rutas')->assertOk();
            $this->getJson('/api/v1/rutas')->assertOk();
            $this->getJson('/api/v1/rutas')->assertStatus(429);

            RateLimiter::for('api', fn () => Limit::perMinute(120)->by('api-test-sin-limite-'.$key));
            RateLimiter::for('tracking', fn () => Limit::perMinute(2)->by('tracking-test-'.$key));
            Http::fake(['*/tracking/start' => Http::response(['ok' => true])]);
            $conductor = ConductorPerfil::factory()->disponible()->create();
            $ruta = Ruta::factory()->create(['empresa_id' => $conductor->empresa_id]);
            Micro::factory()->create([
                'empresa_id' => $conductor->empresa_id,
                'conductor_actual_id' => $conductor->id,
                'ruta_id' => $ruta->id,
            ]);

            $headers = $this->headersPara($conductor->usuario);
            $this->withHeaders($headers)->postJson('/api/v1/conductor/iniciar')->assertOk();
            $this->withHeaders($headers)->postJson('/api/v1/conductor/iniciar')->assertOk();
            $this->withHeaders($headers)->postJson('/api/v1/conductor/iniciar')->assertStatus(429);
            Http::assertSentCount(2);
        } finally {
            RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
            RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(60)
                ->by((string) ($request->user('api')?->id ?? $request->ip())));
        }
    }
}
