<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $usuario = Usuario::create([
            'dni' => $datos['dni'],
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'correo' => $datos['correo'],
            'telefono' => $datos['telefono'],
            'password_hash' => $datos['password'],
            'rol' => 'pasajero',
            'activo' => true,
        ]);

        $token = auth('api')->login($usuario);

        return $this->respuestaLogin($usuario, $token, 'Usuario registrado correctamente.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $token = auth('api')->attempt([
            'correo' => $datos['correo'],
            'password' => $datos['password'],
        ]);

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        if (! $usuario->activo) {
            auth('api')->logout();

            return response()->json([
                'success' => false,
                'message' => 'La cuenta está desactivada. Contacta al administrador.',
            ], 403);
        }

        return $this->respuestaLogin($usuario, $token, 'Inicio de sesión correcto.');
    }

    public function me(): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        $usuario->load([
            'conductorPerfil.microActual.ruta',
            'administradorEmpresaPerfiles.empresa',
            'suscripciones.plan',
        ]);

        return (new UsuarioResource($usuario))
            ->additional(['success' => true])
            ->response();
    }

    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        return $this->respuesta([
            'token' => $token,
            'tipo' => 'bearer',
        ], 'Token renovado correctamente.');
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return $this->respuesta(null, 'Sesión cerrada correctamente.');
    }

    private function respuestaLogin(
        Usuario $usuario,
        string $token,
        string $message,
        int $status = 200
    ): JsonResponse {
        $usuario->load([
            'conductorPerfil.microActual.ruta',
            'administradorEmpresaPerfiles.empresa',
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'usuario' => (new UsuarioResource($usuario))->resolve(),
                'token' => $token,
                'tipo' => 'bearer',
            ],
        ], $status);
    }
}
