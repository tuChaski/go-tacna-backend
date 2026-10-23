<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnauthenticatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_peticion_sin_token_devuelve_401_json_aunque_no_envie_accept_json(): void
    {
        $response = $this->get('/api/v1/empresas');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autenticado.');
    }

    public function test_auth_me_sin_token_devuelve_401_json(): void
    {
        $this->get('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
