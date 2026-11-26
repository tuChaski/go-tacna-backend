<?php

namespace Tests\Feature\Api;

use App\Models\AdministradorEmpresaPerfil;
use App\Models\ConductorPerfil;
use App\Models\Empresa;
use App\Models\Micro;
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
