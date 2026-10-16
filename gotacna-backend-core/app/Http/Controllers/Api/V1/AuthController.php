<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Registro público de nuevos usuarios (pasajeros).
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'pasajero', // Por defecto los autoregistrados son pasajeros
            'activo'   => true,
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'data'    => [
                'user'  => $user,
                'token' => $token,
                'type'  => 'bearer',
            ]
        ], 201);
    }

    /**
     * Inicio de sesión multi-rol.
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        $user = auth('api')->user();

        // Verificar si la cuenta está activa
        if (! $user->activo) {
            auth('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Cuenta desactivada. Contacte al administrador.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'user'  => $user,
                'token' => $token,
                'type'  => 'bearer',
            ]
        ]);
    }

    /**
     * Obtener el perfil del usuario autenticado.
     */
    public function me()
    {
        return response()->json([
            'success' => true,
            'data'    => auth('api')->user()
        ]);
    }

    /**
     * Refrescar un token JWT por expirar.
     */
    public function refresh()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'token' => auth('api')->refresh(),
                'type'  => 'bearer',
            ]
        ]);
    }

    /**
     * Cierre de sesión (Invalida el token JWT actual).
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }
}