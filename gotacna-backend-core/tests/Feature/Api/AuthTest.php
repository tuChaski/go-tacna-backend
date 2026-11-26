<?php

namespace Tests\Feature\Api;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_publico_crea_pasajero_y_devuelve_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'dni' => '71234567',
            'nombre' => 'Ana',
            'apellido' => 'Torres',
            'correo' => 'ana@example.com',
            'telefono' => '999888777',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.usuario.correo', 'ana@example.com')
            ->assertJsonPath('data.usuario.rol', 'pasajero')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'usuario' => ['id', 'dni', 'nombre', 'apellido', 'correo', 'rol', 'activo'],
                    'token',
                    'tipo',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('usuarios', [
            'correo' => 'ana@example.com',
            'rol' => 'pasajero',
        ]);
    }

    public function test_login_con_correo_y_password(): void
    {
        $usuario = Usuario::factory()->create([
            'correo' => 'login@example.com',
            'password_hash' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'correo' => $usuario->correo,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.usuario.correo', $usuario->correo);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_acepta_alias_email_y_contrasena(): void
    {
        $usuario = Usuario::factory()->create([
            'correo' => 'alias@example.com',
            'password_hash' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $usuario->correo,
            'contrasena' => 'password123',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.usuario.correo', $usuario->correo);
    }

    public function test_validacion_devuelve_mensajes_en_espanol(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.correo.0', 'El campo correo es obligatorio.')
            ->assertJsonPath('errors.password.0', 'El campo contraseña es obligatorio.');
    }

    public function test_login_rechaza_credenciales_invalidas(): void
    {
        Usuario::factory()->create([
            'correo' => 'bad@example.com',
            'password_hash' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'correo' => 'bad@example.com',
            'password' => 'incorrecta',
        ])->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        Usuario::factory()->inactivo()->create([
            'correo' => 'inactivo@example.com',
            'password_hash' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'correo' => 'inactivo@example.com',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_me_requiere_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_me_devuelve_usuario_autenticado(): void
    {
        $usuario = Usuario::factory()->create(['correo' => 'me@example.com']);

        $this->withHeaders($this->headersPara($usuario))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.correo', 'me@example.com')
            ->assertJsonPath('data.rol', $usuario->rol);
    }

    public function test_logout_invalida_el_token(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $this->tokenPara($usuario);
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
