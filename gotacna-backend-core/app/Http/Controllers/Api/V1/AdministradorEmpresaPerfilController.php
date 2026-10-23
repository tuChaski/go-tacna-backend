<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdministradorEmpresaPerfil\AdministradorEmpresaPerfilRequest;
use App\Http\Resources\AdministradorEmpresaPerfilResource;
use App\Models\AdministradorEmpresaPerfil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdministradorEmpresaPerfilController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $this->usuarioActual();
        abort_unless($usuario && in_array($usuario->rol, ['admin_general', 'admin_empresa'], true), 403);

        $query = AdministradorEmpresaPerfil::query()
            ->with(['usuario', 'empresa']);

        $this->aplicaAlcanceEmpresa($query, 'empresa_id', $usuario);

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->integer('empresa_id'));
        }

        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->integer('usuario_id'));
        }

        $perfiles = $query->orderBy('id')
            ->paginate($request->integer('per_page', 15));

        return AdministradorEmpresaPerfilResource::collection($perfiles)
            ->additional(['success' => true])
            ->response();
    }

    public function show(AdministradorEmpresaPerfil $administradorEmpresa): JsonResponse
    {
        $this->verificaEmpresa((int) $administradorEmpresa->empresa_id);

        $administradorEmpresa->load(['usuario', 'empresa']);

        return (new AdministradorEmpresaPerfilResource($administradorEmpresa))
            ->additional(['success' => true])
            ->response();
    }

    public function store(AdministradorEmpresaPerfilRequest $request): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $perfil = AdministradorEmpresaPerfil::create($request->validated());
        $perfil->load(['usuario', 'empresa']);

        return (new AdministradorEmpresaPerfilResource($perfil))
            ->additional(['success' => true, 'message' => 'Administrador de empresa creado correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        AdministradorEmpresaPerfilRequest $request,
        AdministradorEmpresaPerfil $administradorEmpresa
    ): JsonResponse {
        abort_unless($this->esAdminGeneral(), 403);

        $administradorEmpresa->update($request->validated());
        $administradorEmpresa->refresh()->load(['usuario', 'empresa']);

        return (new AdministradorEmpresaPerfilResource($administradorEmpresa))
            ->additional(['success' => true, 'message' => 'Administrador de empresa actualizado correctamente.'])
            ->response();
    }

    public function destroy(AdministradorEmpresaPerfil $administradorEmpresa): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $administradorEmpresa->delete();

        return $this->respuesta(null, 'Administrador de empresa eliminado correctamente.');
    }
}
