<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Public\RutaController;
use App\Http\Controllers\Api\V1\Conductor\ConductorController;

Route::prefix('v1')->group(function () {

    // 1. Endpoints Públicos de Consulta
    Route::get('/rutas', [RutaController::class, 'index']);
    Route::get('/rutas/{id}', [RutaController::class, 'show']);
    Route::get('/micros/activos', [RutaController::class, 'microsActivos']);

    // 2. Autenticación
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:api')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // 3. Módulo Conductor (Protegido por Token JWT + Rol Conductor)
    Route::middleware(['auth:api', 'role:conductor'])->prefix('conductor')->group(function () {
        Route::get('/perfil', [ConductorController::class, 'perfil']);
        Route::post('/posicion', [ConductorController::class, 'reportarPosicion']);
    });

});