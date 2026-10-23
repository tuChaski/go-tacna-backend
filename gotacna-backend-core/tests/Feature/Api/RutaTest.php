<?php

namespace Tests\Feature\Api;

use App\Models\AdministradorEmpresaPerfil;
use App\Models\Empresa;
use App\Models\Micro;
use App\Models\Ruta;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RutaTest extends TestCase
{
    use RefreshDatabase;

    public function test_listado_publico_solo_muestra_rutas_activas(): void
    {
        $empresa = Empresa::factory()->create();

        Ruta::factory()->create([
            'empresa_id' => $empresa->id,
            'nombre_ruta' => 'Ruta Activa',
            'activo' => true,
        ]);

        Ruta::factory()->create([
            'empresa_id' => $empresa->id,
            'nombre_ruta' => 'Ruta Inactiva',
            'activo' => false,
        ]);

        $response = $this->getJson('/api/v1/rutas');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nombre_ruta', 'Ruta Activa')
            ->assertJsonPath('data.0.trazado_ruta.type', 'LineString');
    }

    public function test_detalle_publico_devuelve_geojson_y_oculta_inactivas(): void
    {
        $empresa = Empresa::factory()->create();
        $activa = Ruta::factory()->create(['empresa_id' => $empresa->id, 'activo' => true]);
        $inactiva = Ruta::factory()->create(['empresa_id' => $empresa->id, 'activo' => false]);

        $this->getJson('/api/v1/rutas/'.$activa->id)
            ->assertOk()
            ->assertJsonPath('data.id', $activa->id)
            ->assertJsonPath('data.trazado_ruta.type', 'LineString');

        $this->getJson('/api/v1/rutas/'.$inactiva->id)
            ->assertNotFound();
    }

    public function test_admin_general_puede_crear_ruta_con_trazado_geojson(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $empresa = Empresa::factory()->create();

        $response = $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/rutas', [
                'empresa_id' => $empresa->id,
                'nombre_ruta' => 'Ruta Nueva',
                'trazado_ruta' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [-70.250000, -18.010000],
                        [-70.240000, -18.020000],
                    ],
                ],
                'activo' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nombre_ruta', 'Ruta Nueva')
            ->assertJsonPath('data.trazado_ruta.type', 'LineString');

        $ruta = Ruta::where('nombre_ruta', 'Ruta Nueva')->firstOrFail();
        $this->assertSame('LineString', $ruta->trazado_ruta_geojson['type']);
        $this->assertCount(2, $ruta->trazado_ruta_geojson['coordinates']);
    }

    public function test_admin_empresa_solo_crea_rutas_de_sus_empresas(): void
    {
        $propia = Empresa::factory()->create();
        $ajena = Empresa::factory()->create();
        $admin = Usuario::factory()->adminEmpresa()->create();
        AdministradorEmpresaPerfil::factory()->create([
            'usuario_id' => $admin->id,
            'empresa_id' => $propia->id,
        ]);

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/rutas', [
                'empresa_id' => $propia->id,
                'nombre_ruta' => 'Ruta Propia',
                'trazado_ruta' => [
                    'type' => 'LineString',
                    'coordinates' => [[-70.25, -18.01], [-70.24, -18.02]],
                ],
            ])
            ->assertCreated();

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/rutas', [
                'empresa_id' => $ajena->id,
                'nombre_ruta' => 'Ruta Ajena',
                'trazado_ruta' => [
                    'type' => 'LineString',
                    'coordinates' => [[-70.25, -18.01], [-70.24, -18.02]],
                ],
            ])
            ->assertForbidden();
    }

    public function test_rechaza_un_trazado_invalido(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $empresa = Empresa::factory()->create();

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/rutas', [
                'empresa_id' => $empresa->id,
                'nombre_ruta' => 'Ruta Inválida',
                'trazado_ruta' => [
                    'type' => 'LineString',
                    'coordinates' => [[-70.25, -18.01]],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trazado_ruta');
    }

    public function test_actualiza_el_trazado_de_una_ruta(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $ruta = Ruta::factory()->create();

        $this->withHeaders($this->headersPara($admin))
            ->putJson('/api/v1/rutas/'.$ruta->id, [
                'trazado_ruta' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [-70.260000, -18.000000],
                        [-70.255000, -18.005000],
                        [-70.250000, -18.010000],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.trazado_ruta.type', 'LineString')
            ->assertJsonCount(3, 'data.trazado_ruta.coordinates');
    }

    public function test_no_elimina_una_ruta_con_micros_asignados(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $ruta = Ruta::factory()->create();
        Micro::factory()->create([
            'empresa_id' => $ruta->empresa_id,
            'ruta_id' => $ruta->id,
        ]);

        $this->withHeaders($this->headersPara($admin))
            ->deleteJson('/api/v1/rutas/'.$ruta->id)
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_elimina_una_ruta_sin_micros(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();
        $ruta = Ruta::factory()->create();

        $this->withHeaders($this->headersPara($admin))
            ->deleteJson('/api/v1/rutas/'.$ruta->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('rutas', ['id' => $ruta->id]);
    }
}
