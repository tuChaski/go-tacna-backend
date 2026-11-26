<?php

namespace Tests;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    protected function tokenPara(Usuario $usuario): string
    {
        return JWTAuth::fromUser($usuario);
    }

    /**
     * @return array<string, string>
     */
    protected function headersPara(Usuario $usuario): array
    {
        return [
            'Authorization' => 'Bearer '.$this->tokenPara($usuario),
            'Accept' => 'application/json',
        ];
    }
}
