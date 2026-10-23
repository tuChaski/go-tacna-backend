<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'gateway.core_api_url' => 'http://core.test',
            'gateway.timeout' => 5,
            'gateway.verify_tls' => false,
        ]);
    }

    public function test_reenvia_get_con_query_y_authorization(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'data' => []], 200, [
                'Content-Type' => 'application/json',
            ]),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token-de-prueba',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/rutas?estado=activo&empresa_id=3');

        $response->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function (Request $request) {
            $url = $request->url();

            return $request->method() === 'GET'
                && str_starts_with($url, 'http://core.test/api/v1/rutas?')
                && str_contains($url, 'estado=activo')
                && str_contains($url, 'empresa_id=3')
                && $request->hasHeader('Authorization', 'Bearer token-de-prueba')
                && $request->hasHeader('X-Gateway', 'gotacna-api-gateway');
        });
    }

    public function test_reenvia_post_con_cuerpo_json(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'data' => ['token' => 'abc']], 201, [
                'Content-Type' => 'application/json',
            ]),
        ]);

        $payload = [
            'correo' => 'admin@gotacna.pe',
            'password' => 'admin123',
        ];

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertCreated()
            ->assertJsonPath('data.token', 'abc');

        Http::assertSent(function (Request $request) use ($payload) {
            return $request->method() === 'POST'
                && $request->url() === 'http://core.test/api/v1/auth/login'
                && $request->body() === json_encode($payload)
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function test_conserva_el_estado_y_cuerpo_del_core(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401, ['Content-Type' => 'application/json']),
        ]);

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autorizado.');
    }

    public function test_devuelve_502_si_el_core_no_esta_disponible(): void
    {
        Http::fake([
            '*' => fn () => throw new ConnectionException('Core no disponible'),
        ]);

        $this->getJson('/api/v1/rutas')
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_rechaza_cargas_multipart(): void
    {
        Http::fake();

        $this->call(
            'POST',
            '/api/v1/archivos',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'multipart/form-data; boundary=----prueba'],
            'contenido'
        )->assertStatus(415)
            ->assertJsonPath('success', false);

        Http::assertNothingSent();
    }

    public function test_responde_al_preflight_cors(): void
    {
        config([
            'cors.allowed_origins' => ['http://localhost:5173'],
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization, Content-Type',
        ])->options('/api/v1/rutas');

        $response->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
