<?php

namespace App\Http\Controllers\Api\V1\Conductor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conductor\ReportarPosicionRequest;
use App\Http\Resources\PosicionGpsResource;
use App\Models\ConductorPerfil;
use App\Models\PosicionGps;
use App\Models\Usuario;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tymon\JWTAuth\Facades\JWTAuth;

class PosicionController extends Controller
{
    public function iniciar(): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();
        $conductor = ConductorPerfil::query()->with('microActual.ruta')
            ->where('usuario_id', $usuario->id)->first();

        if (! $conductor || ! $conductor->microActual || ! $conductor->microActual->ruta?->activo) {
            return response()->json([
                'success' => false,
                'message' => 'Necesitas un micro asignado a una ruta activa para iniciar el recorrido.',
            ], 422);
        }

        DB::transaction(function () use ($conductor) {
            $conductor->microActual->update(['estado' => 'en_recorrido']);
            $conductor->update(['estado_operativo' => 'en_recorrido']);
        });

        return $this->respuesta([
            'micro_id' => $conductor->microActual->id,
            'estado_operativo' => 'en_recorrido',
        ], 'Recorrido iniciado.');
    }

    public function store(ReportarPosicionRequest $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        $conductor = ConductorPerfil::query()
            ->with('microActual')
            ->where('usuario_id', $usuario->id)
            ->first();

        if (! $conductor) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un perfil de conductor asociado.',
            ], 404);
        }

        $micro = $conductor->microActual;

        if (! $micro) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un micro asignado. Contacta al administrador.',
            ], 422);
        }

        if ($micro->estado !== 'en_recorrido' || $conductor->estado_operativo !== 'en_recorrido') {
            return response()->json([
                'success' => false,
                'message' => 'Inicia el recorrido antes de enviar posiciones GPS.',
            ], 409);
        }

        $datos = $request->validated();

        try {
            $posicion = DB::transaction(function () use ($datos, $micro, $usuario) {
                $posicion = PosicionGps::create([
                    'micro_id' => $micro->id,
                    'latitud' => $datos['latitud'],
                    'longitud' => $datos['longitud'],
                    'velocidad' => $datos['velocidad'] ?? 0,
                    'fecha_hora' => $datos['fecha_hora'] ?? now(),
                ]);

                $token = JWTAuth::fromUser($usuario->fresh());
                $respuesta = Http::timeout(3)->withToken($token)->post(
                    rtrim(config('services.realtime.url'), '/').'/tracking/position',
                    [
                        'lat' => (float) $datos['latitud'],
                        'lng' => (float) $datos['longitud'],
                        'speed' => (float) ($datos['velocidad'] ?? 0),
                    ]
                );

                if (! $respuesta->successful()) {
                    throw new \RuntimeException('El servicio de tiempo real rechazó la posición.');
                }

                return $posicion;
            });
        } catch (ConnectionException|\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar la posición en tiempo real. Intenta nuevamente.',
            ], 503);
        }

        $posicion->load('micro.ruta');

        return (new PosicionGpsResource($posicion))
            ->additional(['success' => true, 'message' => 'Posición reportada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function finalizar(): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        $conductor = ConductorPerfil::query()
            ->with('microActual')
            ->where('usuario_id', $usuario->id)
            ->first();

        if (! $conductor) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un perfil de conductor asociado.',
            ], 404);
        }

        if ($conductor->microActual) {
            DB::transaction(function () use ($conductor) {
                $conductor->microActual->update(['estado' => 'disponible']);
                $conductor->update(['estado_operativo' => 'sin_conexion']);
            });
        }

        return $this->respuesta([
            'micro_id' => $conductor->microActual?->id,
            'estado_operativo' => 'sin_conexion',
        ], 'Recorrido finalizado. El micro quedó disponible.');
    }
}
