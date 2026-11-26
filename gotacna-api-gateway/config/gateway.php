<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backend core
    |--------------------------------------------------------------------------
    | URL base del backend Laravel que contiene la API /api/v1. En Docker se
    | configura como http://backend:8000 y en desarrollo local como
    | http://127.0.0.1:8000.
    */
    'core_api_url' => env('CORE_API_URL', 'http://127.0.0.1:8000'),

    /*
    |--------------------------------------------------------------------------
    | Tiempo de espera
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('CORE_API_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Verificación TLS
    |--------------------------------------------------------------------------
    | Desactivar únicamente en entornos locales con certificados autofirmados.
    */
    'verify_tls' => filter_var(
        env('CORE_API_VERIFY_TLS', true),
        FILTER_VALIDATE_BOOLEAN
    ),

];
