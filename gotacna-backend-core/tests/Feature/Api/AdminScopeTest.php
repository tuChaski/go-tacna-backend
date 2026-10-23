<?php

namespace Tests\Feature\Api;

use App\Models\AdministradorEmpresaPerfil;
use App\Models\ConductorPerfil;
use App\Models\Empresa;
use App\Models\Ruta;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScopeTest extends TestCase
{
    use RefreshDatabase;

    private function adminEmpresaDe(Empresa $empresa): Usuario
    {
        $admin = Usuario::factory()->adminEmpresa()->create();

        AdministradorEmpresaPerfil::factory()->create([
            'usuario_id' => $admin->id,
            'empresa_id' => $empresa->id,
        ]);

        return $admin;
    }

    public function test_admin_empresa_solo_ve_sus_empresas(): void
    {
        $propia = Empresa::factory()->create();
        $ajena = Empresa::factory()->create();
        $admin = $this->adminEmpresaDe($propia);

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/empresas')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $propia->id);

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/empresas/'.$ajena->id)
            ->assertForbidden();
    }

    public function test_admin_general_ve_todas_las_empresas(): void
    {
        Empresa::factory()->count(2)->create();
        $admin = Usuario::factory()->adminGeneral()->create();

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/empresas')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_empresa_no_puede_gestionar_usuarios_ni_planes(): void
    {
        $empresa = Empresa::factory()->create();
        $admin = $this->adminEmpresaDe($empresa);

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/usuarios')
            ->assertForbidden();

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/planes', [
                'nombre_plan' => 'Plan no autorizado',
                'costo' => 10,
            ])
            ->assertForbidden();
    }

    public function test_admin_general_puede_crear_usuarios(): void
    {
        $admin = Usuario::factory()->adminGeneral()->create();

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/usuarios', [
                'dni' => '72345678',
                'nombre' => 'Luis',
                'apellido' => 'Rojas',
                'correo' => 'luis@example.com',
                'telefono' => '988777666',
                'rol' => 'pasajero',
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.correo', 'luis@example.com')
            ->assertJsonPath('data.rol', 'pasajero');
    }

    public function test_admin_empresa_solo_ve_sus_rutas_incluidas_inactivas(): void
    {
        $propia = Empresa::factory()->create();
        $ajena = Empresa::factory()->create();
        $admin = $this->adminEmpresaDe($propia);

        Ruta::factory()->create(['empresa_id' => $propia->id, 'activo' => true]);
        Ruta::factory()->create(['empresa_id' => $propia->id, 'activo' => false]);
        Ruta::factory()->create(['empresa_id' => $ajena->id, 'activo' => true]);

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/rutas')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/rutas')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_empresa_solo_ve_sus_conductores(): void
    {
        $propia = Empresa::factory()->create();
        $ajena = Empresa::factory()->create();
        $admin = $this->adminEmpresaDe($propia);

        ConductorPerfil::factory()->create(['empresa_id' => $propia->id]);
        ConductorPerfil::factory()->create(['empresa_id' => $ajena->id]);

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/conductores')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.empresa_id', $propia->id);
    }

    public function test_admin_empresa_puede_actualizar_su_empresa(): void
    {
        $empresa = Empresa::factory()->create();
        $admin = $this->adminEmpresaDe($empresa);

        $this->withHeaders($this->headersPara($admin))
            ->putJson('/api/v1/empresas/'.$empresa->id, [
                'ruc' => $empresa->ruc,
                'razon_social' => 'Razón Social Actualizada',
                'telefono' => $empresa->telefono,
                'direccion' => $empresa->direccion,
                'activo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.razon_social', 'Razón Social Actualizada');
    }

    public function test_listado_administradores_empresa_incluye_relaciones_completas(): void
    {
        $empresa = Empresa::factory()->create(['activo' => true]);
        $adminGeneral = Usuario::factory()->adminGeneral()->create();
        $adminEmpresa = Usuario::factory()->adminEmpresa()->create(['activo' => true]);

        AdministradorEmpresaPerfil::factory()->create([
            'usuario_id' => $adminEmpresa->id,
            'empresa_id' => $empresa->id,
            'cargo' => 'Gerente',
        ]);

        $this->withHeaders($this->headersPara($adminGeneral))
            ->getJson('/api/v1/administradores-empresa')
            ->assertOk()
            ->assertJsonPath('data.0.usuario.correo', $adminEmpresa->correo)
            ->assertJsonPath('data.0.usuario.activo', true)
            ->assertJsonPath('data.0.empresa.razon_social', $empresa->razon_social)
            ->assertJsonPath('data.0.empresa.activo', true)
            ->assertJsonPath('meta.links.0.label', '&laquo; Anterior')
            ->assertJsonPath('meta.links.1.label', '1')
            ->assertJsonPath('meta.links.2.label', 'Siguiente &raquo;');
    }

    public function test_admin_empresa_solo_lee_administradores_de_sus_empresas(): void
    {
        $propia = Empresa::factory()->create();
        $ajena = Empresa::factory()->create();
        $admin = $this->adminEmpresaDe($propia);
        $otroAdmin = Usuario::factory()->adminEmpresa()->create();

        $perfilAjeno = AdministradorEmpresaPerfil::factory()->create([
            'usuario_id' => $otroAdmin->id,
            'empresa_id' => $ajena->id,
        ]);

        $perfilPropio = AdministradorEmpresaPerfil::where('usuario_id', $admin->id)
            ->where('empresa_id', $propia->id)
            ->firstOrFail();

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/administradores-empresa')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.empresa_id', $propia->id);

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/administradores-empresa/'.$perfilPropio->id)
            ->assertOk();

        $this->withHeaders($this->headersPara($admin))
            ->getJson('/api/v1/administradores-empresa/'.$perfilAjeno->id)
            ->assertForbidden();
    }

    public function test_admin_empresa_no_puede_escribir_administradores_de_empresa(): void
    {
        $propia = Empresa::factory()->create();
        $admin = $this->adminEmpresaDe($propia);
        $perfilPropio = AdministradorEmpresaPerfil::where('usuario_id', $admin->id)
            ->where('empresa_id', $propia->id)
            ->firstOrFail();

        $this->withHeaders($this->headersPara($admin))
            ->postJson('/api/v1/administradores-empresa', [
                'usuario_id' => $admin->id,
                'empresa_id' => $propia->id,
                'cargo' => 'Gerente',
            ])
            ->assertForbidden();

        $this->withHeaders($this->headersPara($admin))
            ->putJson('/api/v1/administradores-empresa/'.$perfilPropio->id, [
                'usuario_id' => $admin->id,
                'empresa_id' => $propia->id,
                'cargo' => 'Otro cargo',
            ])
            ->assertForbidden();

        $this->withHeaders($this->headersPara($admin))
            ->deleteJson('/api/v1/administradores-empresa/'.$perfilPropio->id)
            ->assertForbidden();
    }
}
