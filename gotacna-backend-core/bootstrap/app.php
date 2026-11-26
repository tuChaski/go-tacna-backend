<?php

use App\Http\Middleware\CheckRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->is('api/*')
                ? '/api/v1/auth/login'
                : '/login'
        );

        $middleware->alias([
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $esApi = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($esApi);

        $exceptions->render(function (TokenExpiredException $e, Request $request) use ($esApi) {
            if ($esApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El token ha expirado. Inicia sesión nuevamente.',
                ], 401);
            }
        });

        $exceptions->render(function (JWTException $e, Request $request) use ($esApi) {
            if ($esApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido o ausente.',
                ], 401);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($esApi) {
            if ($esApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($esApi) {
            if ($esApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'No tienes permisos para realizar esta acción.',
                ], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($esApi) {
            if ($esApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recurso no encontrado.',
                ], 404);
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($esApi) {
            if (! $esApi($request)) {
                return null;
            }

            $mensaje = $e->getMessage();
            $esClaveForanea = str_contains($mensaje, 'violates foreign key constraint')
                || str_contains($mensaje, 'FOREIGN KEY constraint failed')
                || str_contains($mensaje, 'Cannot delete or update a parent row');

            if ($esClaveForanea) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el registro porque tiene datos relacionados.',
                ], 409);
            }

            return null;
        });
    })->create();
