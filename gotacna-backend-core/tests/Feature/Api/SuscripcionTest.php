<?php

namespace Tests\Feature\Api;

use App\Models\AdministradorEmpresaPerfil;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuscripcionTest extends TestCase
{
    use RefreshDatabase;

    private function crearPlan(): Plan
    {
        return Plan::factory()->create(['activo' => true, 'costo' => 10]);
    }

    public function test_pasajero_crea_y_consulta_su_suscripcion(): void
    {
        $pasajero = Usuario::factory()->pasajero()->create();
        $plan = $this->crearPlan();

        $this->withHeaders($this->headersPara($pasajero))
            ->postJson('/api/v1/mis-suscripciones', [
                'plan_id' => $plan->id,
                'fecha_inicio' => now()->addDay()->toDateString(),
                'fecha_vencimiento' => now()->addMonth()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.usuario_id', $pasajero->id)
            ->assertJsonPath('data.plan_id', $plan->id)
            ->assertJsonPath('data.estado', 'activa');

        $this->withHeaders($this->headersPara($pasajero))
            ->getJson('/api/v1/mis-suscripciones')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.plan_id', $plan->id);
    }

    public function test_pasajero_no_puede_cancelar_la_suscripcion_de_otro(): void
    {
        $dueno = Usuario::factory()->pasajero()->create();
        $otro = Usuario::factory()->pasajero()->create();
        $suscripcion = Suscripcion::factory()->create(['usuario_id' => $dueno->id]);

        $this->withHeaders($this->headersPara($otro))
            ->patchJson('/api/v1/mis-suscripciones/'.$suscripcion->id.'/cancelar')
            ->assertNotFound();
    }

    public function test_pasajero_cancela_su_suscripcion(): void
    {
        $pasajero = Usuario::factory()->pasajero()->create();
        $suscripcion = Suscripcion::factory()->create(['usuario_id' => $pasajero->id]);

        $this->withHeaders($this->headersPara($pasajero))
            ->patchJson('/api/v1/mis-suscripciones/'.$suscripcion->id.'/cancelar')
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada');

        $this->assertDatabaseHas('suscripciones', [
            'id' => $suscripcion->id,
            'estado' => 'cancelada',
        ]);
    }

    public function test_admin_general_crea_una_suscripcion_para_un_pasajero(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $pasajero = Usuario::factory()->pasajero()->create();
        $plan = $this->crearPlan();

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/suscripciones', [
                'usuario_id' => $pasajero->id,
                'plan_id' => $plan->id,
                'fecha_inicio' => now()->toDateString(),
                'fecha_vencimiento' => now()->addMonth()->toDateString(),
                'estado' => 'activa',
            ])
            ->assertCreated()
            ->assertJsonPath('data.usuario_id', $pasajero->id)
            ->assertJsonPath('data.plan_id', $plan->id);
    }

    public function test_admin_empresa_no_puede_gestionar_suscripciones(): void
    {
        $empresa = Empresa::factory()->create();
        $admin = Usuario::factory()->adminEmpresa()->create();
        AdministradorEmpresaPerfil::factory()->create([
            'usuario_id' => $admin->id,
            'empresa_id' => $empresa->id,
        ]);

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/suscripciones')
            ->assertForbidden();
    }

    public function test_admin_general_actualiza_el_estado_de_una_suscripcion(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $suscripcion = Suscripcion::factory()->create(['estado' => 'activa']);

        $this->withHeaders($this->headersPara($admin))
            ->patchJson('/api/v1/suscripciones/'.$suscripcion->id.'/estado', [
                'estado' => 'vencida',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'vencida');
    }
}
