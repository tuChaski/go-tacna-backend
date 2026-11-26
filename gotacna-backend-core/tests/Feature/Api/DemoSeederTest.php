<?php

namespace Tests\Feature\Api;

use App\Models\ConductorPerfil;
use App\Models\Empresa;
use App\Models\Micro;
use App\Models\Plan;
use App\Models\PosicionGps;
use App\Models\Ruta;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seeder_demo_es_idempotente(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(5, Usuario::count());
        $this->assertSame(2, Empresa::count());
        $this->assertSame(2, ConductorPerfil::count());
        $this->assertSame(2, Ruta::count());
        $this->assertSame(2, Micro::count());
        $this->assertSame(3, Plan::count());
        $this->assertSame(2, PosicionGps::count());
        $this->assertSame(1, Suscripcion::count());
    }

    public function test_las_credenciales_demo_permite_iniciar_sesion(): void
    {
        $this->seed();

        $this->postJson('/api/v1/auth/login', [
            'correo' => 'admin@gotacna.pe',
            'password' => 'admin123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.usuario.correo', 'admin@gotacna.pe')
            ->assertJsonPath('data.usuario.rol', 'admin_general');

        $this->postJson('/api/v1/auth/login', [
            'correo' => 'pasajero@gotacna.pe',
            'password' => 'pasajero123',
        ])
            ->assertOk()
            ->assertJsonPath('data.usuario.rol', 'pasajero');
    }
}
