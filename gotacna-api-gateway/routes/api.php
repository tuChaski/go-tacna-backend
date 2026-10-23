<?php

use App\Http\Controllers\ProxyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Gateway
|--------------------------------------------------------------------------
| Todo lo que llegue a /api/* se reenvía al backend core conservando la
| ruta completa. El core expone la API versionada en /api/v1/*.
*/
Route::any('/{any?}', ProxyController::class)
    ->where('any', '.*')
    ->name('gateway.proxy');
