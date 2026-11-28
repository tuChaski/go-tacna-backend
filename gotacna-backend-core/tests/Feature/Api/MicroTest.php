<?php

namespace Tests\Feature\Api;

use App\Models\AdministradorEmpresaPerfil;
use App\Models\ConductorPerfil;
use App\Models\Empresa;
use App\Models\Micro;
use App\Models\PosicionGps;
use App\Models\Ruta;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicroTest extends TestCase
{
    use RefreshDatabase;

    public function test_consulta_publica_solo_micros_operativos(): void
    {
        $empresa = Empresa::factory()->create();
        $ruta = Ruta::factory()->create(['empresa_id' => $empresa->id, 'activo' => true]);

        Micro::factory()->create([
            'empresa_id' => $empresa->id,
            'ruta_id' => $ruta->id,
            'placa' => 'AAA-111',
            'estado' => 'disponible',
        ]);

        Micro::factory()->create([
            'empresa_id' => $empresa->id,
            'ruta_id' => $ruta->id,
            'placa' => 'BBB-222',
            'estado' => 'mantenimiento',
        ]);

        $this->getJson('/api/v1/micros/activos')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.placa', 'AAA-111');
    }

    public function test_consultas_publicas_no_exponen_conductor_ni_historial_y_aplican_los_mismos_filtros(): void
    {
        $empresa = Empresa::factory()->create();
        $ruta = Ruta::factory()->create(['empresa_id' => $empresa->id]);
        $conductor = ConductorPerfil::factory()->create(['empresa_id' => $empresa->id]);
        $micro = Micro::factory()->create([
            'empresa_id' => $empresa->id,
            'ruta_id' => $ruta->id,
            'conductor_actual_id' => $conductor->id,
        ]);
        PosicionGps::factory()->create(['micro_id' => $micro->id]);

        foreach (['/api/v1/micros/activos', '/api/v1/micros/'.$micro->id] as $url) {
            $respuesta = $this->getJson($url)->assertOk();
            $datos = $respuesta->json(str_ends_with($url, 'activos') ? 'data.0' : 'data');
            $this->assertSame($micro->placa, $datos['placa']);
            $this->assertArrayNotHasKey('conductor_actual', $datos);
            $this->assertArrayNotHasKey('conductor_actual_id', $datos);
            $this->assertArrayNotHasKey('posiciones', $datos);
            $this->assertArrayHasKey('fecha_hora', $datos['ultima_posicion']);
        }

        $ruta->update(['activo' => false]);
        $this->getJson('/api/v1/micros/activos')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/micros/'.$micro->id)->assertNotFound();

        $ruta->update(['activo' => true]);
        $empresa->update(['activo' => false]);
        $this->getJson('/api/v1/micros/activos')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/micros/'.$micro->id)->assertNotFound();

        $empresa->update(['activo' => true]);
        $pasajero = Usuario::factory()->pasajero()->create();
        $this->withHeaders($this->headersPara($pasajero))
            ->getJson('/api/v1/micros/'.$micro->id)
            ->assertOk()->assertJsonMissingPath('data.conductor_actual');

        app('tymon.jwt')->unsetToken();
        app('auth')->forgetGuards();
        $admin = Usuario::factory()->adminGeneral()->create();
        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/micros/'.$micro->id)
            ->assertOk()->assertJsonPath('data.conductor_actual.licencia_conducir', $conductor->licencia_conducir);

    }

    public function test_admin_general_crea_un_micro(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $empresa = Empresa::factory()->create();
        $ruta = Ruta::factory()->create(['empresa_id' => $empresa->id]);

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/micros', [
                'empresa_id' => $empresa->id,
                'ruta_id' => $ruta->id,
                'placa' => 'V3C-303',
                'capacidad_pasajeros' => 25,
                'estado' => 'disponible',
            ])
            ->assertCreated()
            ->assertJsonPath('data.placa', 'V3C-303')
            ->assertJsonPath('data.ruta_id', $ruta->id);

        $this->assertDatabaseHas('micros', ['placa' => 'V3C-303']);
    }

    public function test_valida_que_la_ruta_pertenezca_a_la_empresa(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $empresa = Empresa::factory()->create();
        $otraEmpresa = Empresa::factory()->create();
        $rutaAjena = Ruta::factory()->create(['empresa_id' => $otraEmpresa->id]);

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/micros', [
                'empresa_id' => $empresa->id,
                'ruta_id' => $rutaAjena->id,
                'placa' => 'V4D-404',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ruta_id');
    }

    public function test_no_asigna_el_mismo_conductor_a_dos_micros(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $empresa = Empresa::factory()->create();
        $conductor = ConductorPerfil::factory()->create(['empresa_id' => $empresa->id]);

        Micro::factory()->create([
            'empresa_id' => $empresa->id,
            'conductor_actual_id' => $conductor->id,
        ]);

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/micros', [
                'empresa_id' => $empresa->id,
                'conductor_actual_id' => $conductor->id,
                'placa' => 'V5E-505',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conductor_actual_id');
    }

    public function test_cambiar_conductor_conserva_las_fechas_de_asignacion(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $empresa = Empresa::factory()->create();
        $primero = ConductorPerfil::factory()->create(['empresa_id' => $empresa->id]);
        $segundo = ConductorPerfil::factory()->create(['empresa_id' => $empresa->id]);
        $micro = Micro::factory()->create([
            'empresa_id' => $empresa->id,
            'conductor_actual_id' => $primero->id,
        ]);

        $this->assertDatabaseHas('conductores_micro', [
            'micro_id' => $micro->id,
            'conductor_perfil_id' => $primero->id,
            'fecha_inicio_estimada' => false,
            'fecha_fin' => null,
        ]);

        $this->withHeaders($this->headersPara($admin))
            ->patchJson('/api/v1/micros/'.$micro->id, ['conductor_actual_id' => $segundo->id])
            ->assertOk();

        $this->assertDatabaseCount('conductores_micro', 2);
        $this->assertNotNull($micro->asignaciones()->where('conductor_perfil_id', $primero->id)->firstOrFail()->fecha_fin);
        $this->assertNotNull($micro->asignaciones()->where('conductor_perfil_id', $segundo->id)->firstOrFail()->fecha_inicio);

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/micros/'.$micro->id.'/asignaciones')
            ->assertOk()->assertJsonPath('meta.total', 2);

        $this->withHeaders($this->headersPara($admin))
            ->deleteJson('/api/v1/micros/'.$micro->id)
            ->assertStatus(409);

        app('tymon.jwt')->unsetToken();
        app('auth')->forgetGuards();
        $this->withHeaders($this->headersPara($segundo->usuario))
            ->getJson('/api/v1/conductor/asignaciones')
            ->assertOk()->assertJsonPath('meta.total', 1);

        app('tymon.jwt')->unsetToken();
        app('auth')->forgetGuards();
        $this->withHeaders($this->headersPara($admin))
            ->patchJson('/api/v1/micros/'.$micro->id, ['conductor_actual_id' => null])
            ->assertOk();

        $this->assertDatabaseCount('conductores_micro', 2);
        $this->assertSame(0, $micro->asignaciones()->whereNull('fecha_fin')->count());
    }

    public function test_admin_empresa_solo_gestiona_sus_micros(): void
    {
        $propia = Empresa::factory()->create();
        $ajena = Empresa::factory()->create();
        $admin = Usuario::factory()->adminEmpresa()->create();
        AdministradorEmpresaPerfil::factory()->create([
            'usuario_id' => $admin->id,
            'empresa_id' => $propia->id,
        ]);

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/micros', [
                'empresa_id' => $propia->id,
                'placa' => 'V6F-606',
            ])
            ->assertCreated();

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/micros', [
                'empresa_id' => $ajena->id,
                'placa' => 'V7G-707',
            ])
            ->assertForbidden();
    }

    public function test_actualiza_el_estado_de_un_micro(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $micro = Micro::factory()->create(['estado' => 'disponible']);

        $this->withHeaders($this->headersPara($admin))
            ->patchJson('/api/v1/micros/'.$micro->id.'/estado', [
                'estado' => 'en_recorrido',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_recorrido');

        $this->assertDatabaseHas('micros', [
            'id' => $micro->id,
            'estado' => 'en_recorrido',
        ]);
    }

    public function test_no_elimina_un_micro_en_recorrido(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $micro = Micro::factory()->enRecorrido()->create();

        $this->withHeaders($this->headersPara($admin))
            ->deleteJson('/api/v1/micros/'.$micro->id)
            ->assertStatus(409);
    }
}
