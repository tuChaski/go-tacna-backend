<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Permite el acceso cuando el usuario autenticado tiene uno de los roles indicados.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $usuario = auth('api')->user();

        if (! $usuario || ! $usuario->activo || ! in_array($usuario->rol, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para acceder a este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
