<?php

namespace App\Http\Controllers\Api\V1\Conductor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conductor\ReportarPosicionRequest;
use App\Http\Resources\PosicionGpsResource;
use App\Models\ConductorPerfil;
use App\Models\Micro;
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

        try {
            DB::transaction(function () use ($conductor, $usuario) {
                $conductor->microActual->update(['estado' => 'en_recorrido']);
                $conductor->update(['estado_operativo' => 'en_recorrido']);
                $this->sincronizarTiempoReal($usuario, 'start');
            });
        } catch (ConnectionException|\RuntimeException $e) {
            return $this->tiempoRealNoDisponible();
        }

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
        if ($request->routeIs('tracking.position')) {
            $datos = [
                'latitud' => $datos['lat'],
                'longitud' => $datos['lng'],
                'velocidad' => $datos['speed'],
            ];
        }

        try {
            $posicion = DB::transaction(function () use ($datos, $micro, $conductor, $usuario) {
                $microActual = Micro::query()->lockForUpdate()->findOrFail($micro->id);
                if ($microActual->estado !== 'en_recorrido'
                    || (int) $microActual->conductor_actual_id !== (int) $conductor->id) {
                    throw new \DomainException('El recorrido ya no está activo.');
                }

                $posicion = PosicionGps::create([
                    'micro_id' => $micro->id,
                    'latitud' => $datos['latitud'],
                    'longitud' => $datos['longitud'],
                    'velocidad' => $datos['velocidad'] ?? 0,
                    'fecha_hora' => $datos['fecha_hora'] ?? now(),
                ]);

                $this->sincronizarTiempoReal($usuario, 'position', [
                    'lat' => (float) $datos['latitud'],
                    'lng' => (float) $datos['longitud'],
                    'speed' => (float) ($datos['velocidad'] ?? 0),
                ]);

                return $posicion;
            });
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (ConnectionException|\RuntimeException $e) {
            return $this->tiempoRealNoDisponible();
        }

        $posicion->load('micro.ruta');

        if ($request->routeIs('tracking.position')) {
            return response()->json(['ok' => true], 202);
        }

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
            try {
                DB::transaction(function () use ($conductor, $usuario) {
                    $micro = Micro::query()->lockForUpdate()->findOrFail($conductor->microActual->id);
                    $this->sincronizarTiempoReal($usuario, 'stop');
                    $micro->update(['estado' => 'disponible']);
                    $conductor->update(['estado_operativo' => 'sin_conexion']);
                });
            } catch (ConnectionException|\RuntimeException $e) {
                return $this->tiempoRealNoDisponible();
            }
        }

        return $this->respuesta([
            'micro_id' => $conductor->microActual?->id,
            'estado_operativo' => 'sin_conexion',
        ], 'Recorrido finalizado. El micro quedó disponible.');
    }

    private function sincronizarTiempoReal(Usuario $usuario, string $accion, array $datos = []): void
    {
        $usuario = $usuario->fresh();
        $micro = $usuario->conductorPerfil?->microActual;
        $token = JWTAuth::claims([
            'realtime_bridge' => true,
            'realtime_action' => $accion,
            'realtime_bridge_at' => now()->timestamp,
            'empresa_id' => $micro?->empresa_id,
        ])->fromUser($usuario);
        JWTAuth::customClaims([]);
        $respuesta = Http::timeout(3)->withToken($token)->post(
            rtrim(config('services.realtime.url'), '/').'/tracking/'.$accion,
            $datos
        );

        if (! $respuesta->successful()) {
            throw new \RuntimeException('El servicio de tiempo real rechazó la solicitud.');
        }
    }

    private function tiempoRealNoDisponible(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No se pudo actualizar el recorrido en tiempo real. Intenta nuevamente.',
        ], 503);
    }
}
