<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PosicionGps\PosicionGpsRequest;
use App\Http\Resources\PosicionGpsResource;
use App\Models\Micro;
use App\Models\PosicionGps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PosicionGpsController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $this->usuarioActual();
        abort_unless($usuario && in_array($usuario->rol, ['admin_general', 'admin_empresa'], true), 403);

        $query = PosicionGps::query()->with([
            'micro',
            'micro.empresa',
            'micro.ruta',
        ]);

        if ($usuario->rol === 'admin_empresa') {
            $query->whereHas('micro', function ($q) use ($usuario) {
                $this->aplicaAlcanceEmpresa($q, 'empresa_id', $usuario);
            });
        }

        if ($request->filled('micro_id')) {
            $query->where('micro_id', $request->integer('micro_id'));
        }

        if ($request->filled('desde')) {
            $query->where('fecha_hora', '>=', $request->date('desde'));
        }

        if ($request->filled('hasta')) {
            $query->where('fecha_hora', '<=', $request->date('hasta'));
        }

        $posiciones = $query->orderByDesc('fecha_hora')
            ->paginate($request->integer('per_page', 50));

        return PosicionGpsResource::collection($posiciones)
            ->additional(['success' => true])
            ->response();
    }

    public function show(PosicionGps $posicion): JsonResponse
    {
        $posicion->load('micro.empresa');
        $this->verificaEmpresa((int) $posicion->micro->empresa_id);

        $posicion->load(['micro.empresa', 'micro.ruta']);

        return (new PosicionGpsResource($posicion))
            ->additional(['success' => true])
            ->response();
    }

    public function store(PosicionGpsRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $micro = Micro::findOrFail($datos['micro_id']);
        $this->verificaEmpresa((int) $micro->empresa_id);

        $posicion = PosicionGps::create([
            'micro_id' => $micro->id,
            'latitud' => $datos['latitud'],
            'longitud' => $datos['longitud'],
            'velocidad' => $datos['velocidad'] ?? 0,
            'fecha_hora' => $datos['fecha_hora'] ?? now(),
        ]);

        $posicion->load('micro');

        return (new PosicionGpsResource($posicion))
            ->additional(['success' => true, 'message' => 'Posición registrada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(PosicionGpsRequest $request, PosicionGps $posicion): JsonResponse
    {
        $posicion->load('micro');
        $this->verificaEmpresa((int) $posicion->micro->empresa_id);

        $datos = $request->validated();

        if (isset($datos['micro_id']) && (int) $datos['micro_id'] !== (int) $posicion->micro_id) {
            $nuevoMicro = Micro::findOrFail($datos['micro_id']);
            $this->verificaEmpresa((int) $nuevoMicro->empresa_id);
        }

        $posicion->update(Arr::only($datos, [
            'micro_id',
            'latitud',
            'longitud',
            'velocidad',
            'fecha_hora',
        ]));

        $posicion->refresh()->load('micro');

        return (new PosicionGpsResource($posicion))
            ->additional(['success' => true, 'message' => 'Posición actualizada correctamente.'])
            ->response();
    }

    public function destroy(PosicionGps $posicion): JsonResponse
    {
        $posicion->load('micro');
        $this->verificaEmpresa((int) $posicion->micro->empresa_id);

        $posicion->delete();

        return $this->respuesta(null, 'Posición eliminada correctamente.');
    }
}
