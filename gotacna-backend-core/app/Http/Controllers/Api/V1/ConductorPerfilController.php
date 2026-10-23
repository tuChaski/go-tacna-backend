<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConductorPerfil\ConductorPerfilRequest;
use App\Http\Resources\ConductorPerfilResource;
use App\Models\ConductorPerfil;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConductorPerfilController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $this->usuarioActual();
        abort_unless($usuario && in_array($usuario->rol, ['admin_general', 'admin_empresa'], true), 403);

        $query = ConductorPerfil::query()->with([
            'usuario',
            'empresa',
            'microActual',
        ]);

        $this->aplicaAlcanceEmpresa($query, 'empresa_id', $usuario);

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->integer('empresa_id'));
        }

        if ($request->filled('estado_operativo')) {
            $query->where('estado_operativo', $request->string('estado_operativo'));
        }

        if ($request->filled('search')) {
            $busqueda = mb_strtolower(trim((string) $request->input('search')));
            $query->whereHas('usuario', function ($q) use ($busqueda) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.$busqueda.'%'])
                    ->orWhereRaw('LOWER(apellido) LIKE ?', ['%'.$busqueda.'%'])
                    ->orWhereRaw('LOWER(correo) LIKE ?', ['%'.$busqueda.'%'])
                    ->orWhereRaw('LOWER(dni) LIKE ?', ['%'.$busqueda.'%']);
            });
        }

        $conductores = $query->orderBy('id')
            ->paginate($request->integer('per_page', 15));

        return ConductorPerfilResource::collection($conductores)
            ->additional(['success' => true])
            ->response();
    }

    public function show(ConductorPerfil $conductor): JsonResponse
    {
        $this->verificaEmpresa((int) $conductor->empresa_id);

        $conductor->load([
            'usuario',
            'empresa',
            'microActual.ruta',
            'microActual.ultimaPosicion',
        ]);

        return (new ConductorPerfilResource($conductor))
            ->additional(['success' => true])
            ->response();
    }

    public function store(ConductorPerfilRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $this->verificaEmpresa((int) $datos['empresa_id']);

        $conductor = DB::transaction(function () use ($datos) {
            if (! empty($datos['usuario_id'])) {
                $usuario = Usuario::findOrFail($datos['usuario_id']);
                $usuario->update(['rol' => 'conductor', 'activo' => true]);
            } else {
                $usuario = Usuario::create([
                    'dni' => $datos['dni'],
                    'nombre' => $datos['nombre'],
                    'apellido' => $datos['apellido'],
                    'correo' => $datos['correo'],
                    'telefono' => $datos['telefono'],
                    'password_hash' => $datos['password'],
                    'rol' => 'conductor',
                    'activo' => true,
                ]);
            }

            return ConductorPerfil::create([
                'usuario_id' => $usuario->id,
                'empresa_id' => $datos['empresa_id'],
                'licencia_conducir' => $datos['licencia_conducir'],
                'estado_operativo' => $datos['estado_operativo'] ?? 'sin_conexion',
            ]);
        });

        $conductor->load(['usuario', 'empresa']);

        return (new ConductorPerfilResource($conductor))
            ->additional(['success' => true, 'message' => 'Conductor creado correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(ConductorPerfilRequest $request, ConductorPerfil $conductor): JsonResponse
    {
        $this->verificaEmpresa((int) $conductor->empresa_id);

        $datos = $request->validated();

        if (isset($datos['empresa_id']) && (int) $datos['empresa_id'] !== (int) $conductor->empresa_id) {
            $this->verificaEmpresa((int) $datos['empresa_id']);

            if ($conductor->microActual()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes cambiar de empresa a un conductor que tiene un micro asignado.',
                ], 409);
            }
        }

        $conductor->update($datos);
        $conductor->refresh()->load(['usuario', 'empresa', 'microActual.ruta']);

        return (new ConductorPerfilResource($conductor))
            ->additional(['success' => true, 'message' => 'Conductor actualizado correctamente.'])
            ->response();
    }

    public function destroy(ConductorPerfil $conductor): JsonResponse
    {
        $this->verificaEmpresa((int) $conductor->empresa_id);

        if ($conductor->microActual()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar un conductor que tiene un micro asignado.',
            ], 409);
        }

        $conductor->delete();

        return $this->respuesta(null, 'Conductor eliminado correctamente.');
    }

    public function estado(Request $request, ConductorPerfil $conductor): JsonResponse
    {
        $datos = $request->validate([
            'estado_operativo' => [
                'required',
                Rule::in(['disponible', 'en_recorrido', 'sin_conexion']),
            ],
        ]);

        $this->verificaEmpresa((int) $conductor->empresa_id);
        $conductor->update(['estado_operativo' => $datos['estado_operativo']]);

        return (new ConductorPerfilResource($conductor->refresh()->load(['usuario', 'empresa'])))
            ->additional(['success' => true, 'message' => 'Estado operativo actualizado.'])
            ->response();
    }

    /**
     * Perfil del conductor autenticado.
     */
    public function perfil(): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        $conductor = ConductorPerfil::query()
            ->with([
                'usuario',
                'empresa',
                'microActual.ruta',
                'microActual.ultimaPosicion',
            ])
            ->where('usuario_id', $usuario->id)
            ->first();

        if (! $conductor) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un perfil de conductor asociado.',
            ], 404);
        }

        return (new ConductorPerfilResource($conductor))
            ->additional(['success' => true])
            ->response();
    }
}
