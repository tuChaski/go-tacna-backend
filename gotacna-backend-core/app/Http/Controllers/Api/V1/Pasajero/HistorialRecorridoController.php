<?php

namespace App\Http\Controllers\Api\V1\Pasajero;

use App\Http\Controllers\Controller;
use App\Http\Resources\HistorialRecorridoResource;
use App\Models\HistorialRecorrido;
use App\Models\Micro;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistorialRecorridoController extends Controller
{
    public function index(Request $request)
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        $recorridos = $usuario->historialRecorridos()->with(['ruta', 'micro'])
            ->latest('fecha_inicio')->paginate($request->integer('per_page', 15));

        return HistorialRecorridoResource::collection($recorridos)
            ->additional(['success' => true])->response();
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'ruta_id' => ['required', 'integer', 'exists:rutas,id'],
            'micro_id' => ['nullable', 'integer', 'exists:micros,id'],
            'origen_latitud' => ['nullable', 'required_with:origen_longitud', 'numeric', 'between:-90,90'],
            'origen_longitud' => ['nullable', 'required_with:origen_latitud', 'numeric', 'between:-180,180'],
        ]);

        if (isset($datos['micro_id'])) {
            $micro = Micro::findOrFail($datos['micro_id']);
            if ((int) $micro->ruta_id !== (int) $datos['ruta_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'El micro no pertenece a la ruta seleccionada.',
                ], 422);
            }
        }

        /** @var Usuario $usuario */
        $usuario = auth('api')->user();
        $recorrido = $usuario->historialRecorridos()->create([
            ...$datos,
            'fecha_inicio' => now(),
        ]);

        return (new HistorialRecorridoResource($recorrido->load(['ruta', 'micro'])))
            ->additional(['success' => true])
            ->response()
            ->setStatusCode(201);
    }

    public function show(HistorialRecorrido $recorrido): JsonResponse
    {
        $this->verificarPropietario($recorrido);

        return (new HistorialRecorridoResource($recorrido->load(['ruta', 'micro'])))
            ->additional(['success' => true])->response();
    }

    public function finalizar(Request $request, HistorialRecorrido $recorrido): JsonResponse
    {
        $this->verificarPropietario($recorrido);

        if ($recorrido->fecha_fin !== null) {
            return response()->json([
                'success' => false,
                'message' => 'El recorrido ya fue finalizado.',
            ], 409);
        }

        $datos = $request->validate([
            'destino_latitud' => ['nullable', 'required_with:destino_longitud', 'numeric', 'between:-90,90'],
            'destino_longitud' => ['nullable', 'required_with:destino_latitud', 'numeric', 'between:-180,180'],
        ]);

        $recorrido->update([...$datos, 'fecha_fin' => now()]);

        return (new HistorialRecorridoResource($recorrido->load(['ruta', 'micro'])))
            ->additional(['success' => true])->response();
    }

    public function destroy(HistorialRecorrido $recorrido): JsonResponse
    {
        $this->verificarPropietario($recorrido);
        $recorrido->delete();

        return $this->respuesta(null, 'Recorrido eliminado del historial.');
    }

    private function verificarPropietario(HistorialRecorrido $recorrido): void
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();
        abort_unless((int) $recorrido->usuario_id === (int) $usuario->id, 404);
    }
}
