<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Empresa\EmpresaRequest;
use App\Http\Resources\EmpresaResource;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $this->usuarioActual();
        abort_unless($usuario && in_array($usuario->rol, ['admin_general', 'admin_empresa'], true), 403);

        $query = Empresa::query();
        $this->aplicaAlcanceEmpresa($query, 'id', $usuario);

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('search')) {
            $busqueda = mb_strtolower(trim((string) $request->input('search')));
            $query->where(function ($q) use ($busqueda) {
                $q->whereRaw('LOWER(razon_social) LIKE ?', ['%'.$busqueda.'%'])
                    ->orWhereRaw('LOWER(ruc) LIKE ?', ['%'.$busqueda.'%']);
            });
        }

        $empresas = $query->withCount(['rutas', 'micros', 'conductorPerfiles'])
            ->orderBy('razon_social')
            ->paginate($request->integer('per_page', 15));

        return EmpresaResource::collection($empresas)
            ->additional(['success' => true])
            ->response();
    }

    public function show(Empresa $empresa): JsonResponse
    {
        $this->verificaEmpresa((int) $empresa->id);

        $empresa->load([
            'rutas',
            'micros',
            'conductorPerfiles.usuario',
            'administradorEmpresaPerfiles.usuario',
        ]);

        return (new EmpresaResource($empresa))
            ->additional(['success' => true])
            ->response();
    }

    public function store(EmpresaRequest $request): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $empresa = Empresa::create($request->validated());

        return (new EmpresaResource($empresa))
            ->additional(['success' => true, 'message' => 'Empresa creada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(EmpresaRequest $request, Empresa $empresa): JsonResponse
    {
        $this->verificaEmpresa((int) $empresa->id);

        $empresa->update($request->validated());

        return (new EmpresaResource($empresa->refresh()))
            ->additional(['success' => true, 'message' => 'Empresa actualizada correctamente.'])
            ->response();
    }

    public function destroy(Empresa $empresa): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        if (
            $empresa->rutas()->exists()
            || $empresa->micros()->exists()
            || $empresa->conductorPerfiles()->exists()
            || $empresa->administradorEmpresaPerfiles()->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar una empresa que tiene rutas, micros, conductores o administradores vinculados.',
            ], 409);
        }

        $empresa->delete();

        return $this->respuesta(null, 'Empresa eliminada correctamente.');
    }

    public function estado(Request $request, Empresa $empresa): JsonResponse
    {
        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $this->verificaEmpresa((int) $empresa->id);
        $empresa->update(['activo' => (bool) $datos['activo']]);

        return (new EmpresaResource($empresa->refresh()))
            ->additional(['success' => true, 'message' => 'Estado de la empresa actualizado.'])
            ->response();
    }
}
