<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\UsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\AdministradorEmpresaPerfil;
use App\Models\ConductorPerfil;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($this->esAdminGeneral(), 403);

        $query = Usuario::query()->with([
            'conductorPerfil.empresa',
            'administradorEmpresaPerfiles.empresa',
        ]);

        if ($request->filled('rol')) {
            $query->where('rol', $request->string('rol'));
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('search')) {
            $busqueda = mb_strtolower(trim((string) $request->input('search')));
            $query->where(function ($q) use ($busqueda) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.$busqueda.'%'])
                    ->orWhereRaw('LOWER(apellido) LIKE ?', ['%'.$busqueda.'%'])
                    ->orWhereRaw('LOWER(correo) LIKE ?', ['%'.$busqueda.'%'])
                    ->orWhereRaw('LOWER(dni) LIKE ?', ['%'.$busqueda.'%']);
            });
        }

        $usuarios = $query->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate($request->integer('per_page', 15));

        return UsuarioResource::collection($usuarios)
            ->additional(['success' => true])
            ->response();
    }

    public function show(Usuario $usuario): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $usuario->load([
            'conductorPerfil.empresa',
            'conductorPerfil.microActual.ruta',
            'administradorEmpresaPerfiles.empresa',
            'suscripciones.plan',
        ]);

        return (new UsuarioResource($usuario))
            ->additional(['success' => true])
            ->response();
    }

    public function store(UsuarioRequest $request): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $datos = $request->validated();

        $usuario = DB::transaction(function () use ($datos) {
            $usuario = Usuario::create([
                'dni' => $datos['dni'],
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
                'correo' => $datos['correo'],
                'telefono' => $datos['telefono'],
                'password_hash' => $datos['password'],
                'rol' => $datos['rol'],
                'activo' => $datos['activo'] ?? true,
            ]);

            $this->sincronizarPerfil($usuario, $datos);

            return $usuario;
        });

        $usuario->load([
            'conductorPerfil.empresa',
            'administradorEmpresaPerfiles.empresa',
        ]);

        return (new UsuarioResource($usuario))
            ->additional(['success' => true, 'message' => 'Usuario creado correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $datos = $request->validated();

        DB::transaction(function () use ($usuario, $datos) {
            $usuario->fill([
                'dni' => $datos['dni'],
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
                'correo' => $datos['correo'],
                'telefono' => $datos['telefono'],
                'rol' => $datos['rol'],
                'activo' => $datos['activo'] ?? $usuario->activo,
            ]);

            if (! empty($datos['password'])) {
                $usuario->password_hash = $datos['password'];
            }

            $usuario->save();

            $this->sincronizarPerfil($usuario, $datos);
        });

        $usuario->refresh()->load([
            'conductorPerfil.empresa',
            'conductorPerfil.microActual.ruta',
            'administradorEmpresaPerfiles.empresa',
        ]);

        return (new UsuarioResource($usuario))
            ->additional(['success' => true, 'message' => 'Usuario actualizado correctamente.'])
            ->response();
    }

    public function destroy(Usuario $usuario): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        /** @var Usuario $actual */
        $actual = auth('api')->user();

        if ($actual->id === $usuario->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar tu propio usuario.',
            ], 409);
        }

        if (
            $usuario->conductorPerfil()->exists()
            || $usuario->administradorEmpresaPerfiles()->exists()
            || $usuario->suscripciones()->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar un usuario con perfiles o suscripciones. Desactívalo en su lugar.',
            ], 409);
        }

        $usuario->delete();

        return $this->respuesta(null, 'Usuario eliminado correctamente.');
    }

    public function estado(Request $request, Usuario $usuario): JsonResponse
    {
        abort_unless($this->esAdminGeneral(), 403);

        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        /** @var Usuario $actual */
        $actual = auth('api')->user();

        if ($actual->id === $usuario->id && ! $datos['activo']) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes desactivar tu propia cuenta.',
            ], 422);
        }

        $usuario->update(['activo' => (bool) $datos['activo']]);

        return (new UsuarioResource($usuario->refresh()))
            ->additional(['success' => true, 'message' => 'Estado del usuario actualizado.'])
            ->response();
    }

    /**
     * Crea o actualiza el perfil adicional del usuario según su rol.
     */
    private function sincronizarPerfil(Usuario $usuario, array $datos): void
    {
        if ($usuario->rol === 'conductor') {
            $perfil = $usuario->conductorPerfil()->first();
            $empresaId = $datos['empresa_id'] ?? $perfil?->empresa_id;
            $licencia = $datos['licencia_conducir'] ?? $perfil?->licencia_conducir;

            if (empty($empresaId) || empty($licencia)) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Para el rol conductor se requiere empresa_id y licencia_conducir.',
                ], 422));
            }

            if ($perfil && $perfil->microActual()->exists()
                && (int) $perfil->empresa_id !== (int) $empresaId) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'No puedes cambiar de empresa a un conductor que tiene un micro asignado.',
                ], 409));
            }

            ConductorPerfil::updateOrCreate(
                ['usuario_id' => $usuario->id],
                [
                    'empresa_id' => $empresaId,
                    'licencia_conducir' => $licencia,
                    'estado_operativo' => $datos['estado_operativo']
                        ?? $perfil?->estado_operativo
                        ?? 'sin_conexion',
                ]
            );

            return;
        }

        if ($usuario->conductorPerfil()->exists()) {
            $perfil = $usuario->conductorPerfil()->first();

            if ($perfil?->microActual()->exists()) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'No puedes quitar el rol conductor mientras tenga un micro asignado.',
                ], 409));
            }

            $perfil?->delete();
        }

        if ($usuario->rol === 'admin_empresa') {
            $empresaId = $datos['empresa_id']
                ?? $usuario->administradorEmpresaPerfiles()->value('empresa_id');

            if ($empresaId) {
                AdministradorEmpresaPerfil::updateOrCreate(
                    ['usuario_id' => $usuario->id, 'empresa_id' => $empresaId],
                    ['cargo' => $datos['cargo'] ?? null]
                );
            }

            return;
        }

        $usuario->administradorEmpresaPerfiles()->delete();
    }
}
