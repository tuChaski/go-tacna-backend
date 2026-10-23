<?php

namespace App\Http\Controllers\Api\V1\Pasajero;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suscripcion\MiSuscripcionRequest;
use App\Http\Resources\SuscripcionResource;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuscripcionController extends Controller
{
    public function index(Request $request)
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        $suscripciones = Suscripcion::query()
            ->with('plan')
            ->where('usuario_id', $usuario->id)
            ->orderByDesc('fecha_inicio')
            ->paginate($request->integer('per_page', 15));

        return SuscripcionResource::collection($suscripciones)
            ->additional(['success' => true])
            ->response();
    }

    public function show(Suscripcion $suscripcion): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        if ((int) $suscripcion->usuario_id !== (int) $usuario->id) {
            abort(404, 'Suscripción no encontrada.');
        }

        $suscripcion->load('plan');

        return (new SuscripcionResource($suscripcion))
            ->additional(['success' => true])
            ->response();
    }

    public function store(MiSuscripcionRequest $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();
        $datos = $request->validated();

        $suscripcion = Suscripcion::create([
            'usuario_id' => $usuario->id,
            'plan_id' => $datos['plan_id'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_vencimiento' => $datos['fecha_vencimiento'],
            'estado' => 'activa',
        ]);

        $suscripcion->load('plan');

        return (new SuscripcionResource($suscripcion))
            ->additional(['success' => true, 'message' => 'Suscripción registrada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function cancelar(Suscripcion $suscripcion): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        if ((int) $suscripcion->usuario_id !== (int) $usuario->id) {
            abort(404, 'Suscripción no encontrada.');
        }

        if ($suscripcion->estado !== 'cancelada') {
            $suscripcion->update(['estado' => 'cancelada']);
        }

        $suscripcion->refresh()->load('plan');

        return (new SuscripcionResource($suscripcion))
            ->additional(['success' => true, 'message' => 'Suscripción cancelada correctamente.'])
            ->response();
    }
}
