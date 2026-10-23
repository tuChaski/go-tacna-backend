<?php

use App\Http\Controllers\Api\V1\AdministradorEmpresaPerfilController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Conductor\PosicionController as ConductorPosicionController;
use App\Http\Controllers\Api\V1\ConductorPerfilController;
use App\Http\Controllers\Api\V1\EmpresaController;
use App\Http\Controllers\Api\V1\MicroController;
use App\Http\Controllers\Api\V1\Pasajero\SuscripcionController as PasajeroSuscripcionController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PosicionGpsController;
use App\Http\Controllers\Api\V1\RutaController;
use App\Http\Controllers\Api\V1\SuscripcionController;
use App\Http\Controllers\Api\V1\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Autenticación
    |--------------------------------------------------------------------------
    */
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    /*
    |--------------------------------------------------------------------------
    | Consultas públicas
    |--------------------------------------------------------------------------
    | Los endpoints de rutas, micros y planes permiten un token opcional para
    | que un administrador vea también registros inactivos y limitados a sus
    | empresas.
    */
    Route::get('rutas', [RutaController::class, 'index']);
    Route::get('rutas/{ruta}', [RutaController::class, 'show']);
    Route::get('micros/activos', [MicroController::class, 'activos']);
    Route::get('micros/{micro}', [MicroController::class, 'show']);
    Route::get('planes', [PlanController::class, 'index']);
    Route::get('planes/{plan}', [PlanController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | Rutas autenticadas
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:api')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        /*
        |----------------------------------------------------------------------
        | Pasajero
        |----------------------------------------------------------------------
        */
        Route::middleware('role:pasajero,admin_general')->group(function () {
            Route::get('mis-suscripciones', [PasajeroSuscripcionController::class, 'index']);
            Route::post('mis-suscripciones', [PasajeroSuscripcionController::class, 'store']);
            Route::get('mis-suscripciones/{suscripcion}', [PasajeroSuscripcionController::class, 'show']);
            Route::patch(
                'mis-suscripciones/{suscripcion}/cancelar',
                [PasajeroSuscripcionController::class, 'cancelar']
            );
        });

        /*
        |----------------------------------------------------------------------
        | Conductor
        |----------------------------------------------------------------------
        */
        Route::middleware('role:conductor')->group(function () {
            Route::get('conductor/perfil', [ConductorPerfilController::class, 'perfil']);
            Route::post('conductor/posicion', [ConductorPosicionController::class, 'store']);
            Route::post('conductor/finalizar', [ConductorPosicionController::class, 'finalizar']);

            // Alias usados por la documentación de tracking.
            Route::post('tracking/position', [ConductorPosicionController::class, 'store']);
            Route::post('tracking/stop', [ConductorPosicionController::class, 'finalizar']);
        });

        /*
        |----------------------------------------------------------------------
        | Administración general y de empresa
        |----------------------------------------------------------------------
        */
        Route::middleware('role:admin_general,admin_empresa')->group(function () {
            // Empresas
            Route::apiResource('empresas', EmpresaController::class);
            Route::patch('empresas/{empresa}/estado', [EmpresaController::class, 'estado']);

            // Conductores
            Route::apiResource('conductores', ConductorPerfilController::class)
                ->parameters(['conductores' => 'conductor']);
            Route::patch('conductores/{conductor}/estado', [ConductorPerfilController::class, 'estado']);

            // Rutas: la lectura pública está declarada arriba.
            Route::post('rutas', [RutaController::class, 'store']);
            Route::match(['put', 'patch'], 'rutas/{ruta}', [RutaController::class, 'update']);
            Route::delete('rutas/{ruta}', [RutaController::class, 'destroy']);
            Route::patch('rutas/{ruta}/estado', [RutaController::class, 'estado']);

            // Micros
            Route::get('micros', [MicroController::class, 'index']);
            Route::post('micros', [MicroController::class, 'store']);
            Route::match(['put', 'patch'], 'micros/{micro}', [MicroController::class, 'update']);
            Route::delete('micros/{micro}', [MicroController::class, 'destroy']);
            Route::patch('micros/{micro}/estado', [MicroController::class, 'estado']);

            // Posiciones (lectura)
            Route::apiResource('posiciones', PosicionGpsController::class)
                ->only(['index', 'show'])
                ->parameters(['posiciones' => 'posicion']);

            // Administradores de empresa (lectura con alcance por empresa)
            Route::get('administradores-empresa', [AdministradorEmpresaPerfilController::class, 'index']);
            Route::get('administradores-empresa/{administrador_empresa}', [AdministradorEmpresaPerfilController::class, 'show']);
        });

        /*
        |----------------------------------------------------------------------
        | Solo administrador general
        |----------------------------------------------------------------------
        */
        Route::middleware('role:admin_general')->group(function () {
            // Usuarios
            Route::apiResource('usuarios', UsuarioController::class);
            Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'estado']);

            // Administradores de empresa (escritura)
            Route::post('administradores-empresa', [AdministradorEmpresaPerfilController::class, 'store']);
            Route::match(
                ['put', 'patch'],
                'administradores-empresa/{administrador_empresa}',
                [AdministradorEmpresaPerfilController::class, 'update']
            );
            Route::delete('administradores-empresa/{administrador_empresa}', [AdministradorEmpresaPerfilController::class, 'destroy']);

            // Planes: la lectura pública está declarada arriba.
            Route::post('planes', [PlanController::class, 'store']);
            Route::match(['put', 'patch'], 'planes/{plan}', [PlanController::class, 'update']);
            Route::delete('planes/{plan}', [PlanController::class, 'destroy']);
            Route::patch('planes/{plan}/estado', [PlanController::class, 'estado']);

            // Posiciones (escritura)
            Route::apiResource('posiciones', PosicionGpsController::class)
                ->only(['store', 'update', 'destroy'])
                ->parameters(['posiciones' => 'posicion']);

            // Suscripciones
            Route::apiResource('suscripciones', SuscripcionController::class)
                ->parameters(['suscripciones' => 'suscripcion']);
            Route::patch('suscripciones/{suscripcion}/estado', [SuscripcionController::class, 'estado']);
        });
    });
});
