<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suscripcion\SuscripcionRequest;
use App\Http\Resources\SuscripcionResource;
use App\Models\Suscripcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SuscripcionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($this->esAdminGeneral(), 403);

        $query = Suscripcion::query()->with([
            'usuario',
            'plan',
        ]);

        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->integer('usuario_id'));
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->integer('plan_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        $suscripciones = $query->orderByDesc('fecha_inicio')
            ->paginate($request->integer('per_page', 15));

        return SuscripcionResource::collection($suscripciones)
            ->additional(['success' => true])
            ->response();
    }

    public function show(Suscripcion $suscripcion): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $suscripcion->load(['usuario', 'plan']);

        return (new SuscripcionResource($suscripcion))
            ->additional(['success' => true])
            ->response();
    }

    public function store(SuscripcionRequest $request): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $datos = $request->validated();

        $suscripcion = Suscripcion::create([
            'usuario_id' => $datos['usuario_id'],
            'plan_id' => $datos['plan_id'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_vencimiento' => $datos['fecha_vencimiento'],
            'estado' => $datos['estado'] ?? 'activa',
        ]);

        $suscripcion->load(['usuario', 'plan']);

        return (new SuscripcionResource($suscripcion))
            ->additional(['success' => true, 'message' => 'Suscripción creada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SuscripcionRequest $request, Suscripcion $suscripcion): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $datos = $request->validated();

        $suscripcion->update(Arr::only($datos, [
            'usuario_id',
            'plan_id',
            'fecha_inicio',
            'fecha_vencimiento',
            'estado',
        ]));

        $suscripcion->refresh()->load(['usuario', 'plan']);

        return (new SuscripcionResource($suscripcion))
            ->additional(['success' => true, 'message' => 'Suscripción actualizada correctamente.'])
            ->response();
    }

    public function destroy(Suscripcion $suscripcion): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $suscripcion->delete();

        return $this->respuesta(null, 'Suscripción eliminada correctamente.');
    }

    public function estado(Request $request, Suscripcion $suscripcion): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $datos = $request->validate([
            'estado' => ['required', Rule::in(['activa', 'vencida', 'cancelada'])],
        ]);

        $suscripcion->update(['estado' => $datos['estado']]);

        return (new SuscripcionResource($suscripcion->refresh()->load(['usuario', 'plan'])))
            ->additional(['success' => true, 'message' => 'Estado de la suscripción actualizado.'])
            ->response();
    }
}
