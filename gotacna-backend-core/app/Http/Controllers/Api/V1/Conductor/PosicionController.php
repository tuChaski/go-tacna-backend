<?php

namespace App\Http\Controllers\Api\V1\Conductor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conductor\ReportarPosicionRequest;
use App\Http\Resources\PosicionGpsResource;
use App\Models\ConductorPerfil;
use App\Models\PosicionGps;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PosicionController extends Controller
{
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

        $datos = $request->validated();

        $posicion = DB::transaction(function () use ($datos, $micro, $conductor) {
            $posicion = PosicionGps::create([
                'micro_id' => $micro->id,
                'latitud' => $datos['latitud'],
                'longitud' => $datos['longitud'],
                'velocidad' => $datos['velocidad'] ?? 0,
                'fecha_hora' => $datos['fecha_hora'] ?? now(),
            ]);

            $micro->update(['estado' => 'en_recorrido']);
            $conductor->update(['estado_operativo' => 'en_recorrido']);

            return $posicion;
        });

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
