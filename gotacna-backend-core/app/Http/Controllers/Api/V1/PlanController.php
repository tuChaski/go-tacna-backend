<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Plan\PlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $esAdminGeneral = $this->esAdminGeneral();

        $query = Plan::query();

        if (! $esAdminGeneral) {
            $query->where('activo', true);
        } elseif ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('search')) {
            $busqueda = mb_strtolower(trim((string) $request->input('search')));
            $query->whereRaw('LOWER(nombre_plan) LIKE ?', ['%'.$busqueda.'%']);
        }

        $planes = $query->orderBy('costo')
            ->paginate($request->integer('per_page', 15));

        return PlanResource::collection($planes)
            ->additional(['success' => true])
            ->response();
    }

    public function show(Plan $plan): JsonResponse
    {
        if (! $this->esAdminGeneral() && ! $plan->activo) {
            abort(404, 'Plan no encontrado.');
        }

        return (new PlanResource($plan))
            ->additional(['success' => true])
            ->response();
    }

    public function store(PlanRequest $request): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $plan = Plan::create($request->validated());

        return (new PlanResource($plan))
            ->additional(['success' => true, 'message' => 'Plan creado correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(PlanRequest $request, Plan $plan): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $plan->update($request->validated());

        return (new PlanResource($plan->refresh()))
            ->additional(['success' => true, 'message' => 'Plan actualizado correctamente.'])
            ->response();
    }

    public function destroy(Plan $plan): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        if ($plan->suscripciones()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar un plan que tiene suscripciones asociadas.',
            ], 409);
        }

        $plan->delete();

        return $this->respuesta(null, 'Plan eliminado correctamente.');
    }

    public function estado(Request $request, Plan $plan): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $plan->update(['activo' => (bool) $datos['activo']]);

        return (new PlanResource($plan->refresh()))
            ->additional(['success' => true, 'message' => 'Estado del plan actualizado.'])
            ->response();
    }
}
