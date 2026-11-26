<?php

namespace Tests\Feature\Api;

use App\Models\AdministradorEmpresaPerfil;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_listado_publico_solo_muestra_planes_activos(): void
    {
        Plan::factory()->create(['nombre_plan' => 'Activo', 'activo' => true]);
        Plan::factory()->create(['nombre_plan' => 'Inactivo', 'activo' => false]);

        $this->getJson('/api/v1/planes')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nombre_plan', 'Activo');
    }

    public function test_admin_general_crea_un_plan(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/planes', [
                'nombre_plan' => 'Plan Anual',
                'costo' => 250.50,
                'activo' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre_plan', 'Plan Anual')
            ->assertJsonPath('data.costo', 250.5);
    }

    public function test_admin_empresa_no_puede_crear_planes(): void
    {
        $empresa = Empresa::factory()->create();
        $admin = Usuario::factory()->adminEmpresa()->create();
        AdministradorEmpresaPerfil::factory()->create([
            'usuario_id' => $admin->id,
            'empresa_id' => $empresa->id,
        ]);

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/planes', [
                'nombre_plan' => 'Plan Prohibido',
                'costo' => 10,
            ])
            ->assertForbidden();
    }

    public function test_no_elimina_un_plan_con_suscripciones(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $plan = Plan::factory()->create();
        Suscripcion::factory()->create(['plan_id' => $plan->id]);

        $this->withHeaders($this->headersPara($admin))
            ->deleteJson('/api/v1/planes/'.$plan->id)
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_actualiza_un_plan(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $plan = Plan::factory()->create();

        $this->withHeaders($this->headersPara($admin))
            ->putJson('/api/v1/planes/'.$plan->id, [
                'nombre_plan' => 'Plan Actualizado',
                'costo' => 99.99,
                'activo' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre_plan', 'Plan Actualizado')
            ->assertJsonPath('data.activo', false);
    }
}
