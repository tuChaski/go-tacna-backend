<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Micro\MicroRequest;
use App\Http\Resources\MicroResource;
use App\Models\Micro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class MicroController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $this->usuarioActual();
        abort_unless($usuario && in_array($usuario->rol, ['admin_general', 'admin_empresa'], true), 403);

        $query = Micro::query()->with([
            'empresa',
            'ruta',
            'conductorActual.usuario',
            'ultimaPosicion',
        ]);

        $this->aplicaAlcanceEmpresa($query, 'empresa_id', $usuario);

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->integer('empresa_id'));
        }

        if ($request->filled('ruta_id')) {
            $query->where('ruta_id', $request->integer('ruta_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('search')) {
            $busqueda = mb_strtolower(trim((string) $request->input('search')));
            $query->whereRaw('LOWER(placa) LIKE ?', ['%'.$busqueda.'%']);
        }

        $micros = $query->orderBy('placa')
            ->paginate($request->integer('per_page', 15));

        return MicroResource::collection($micros)
            ->additional(['success' => true])
            ->response();
    }

    public function activos(Request $request)
    {
        $query = Micro::query()
            ->with([
                'empresa',
                'ruta',
                'conductorActual.usuario',
                'ultimaPosicion',
            ])
            ->whereIn('estado', ['disponible', 'en_recorrido'])
            ->whereHas('empresa', fn ($q) => $q->where('activo', true))
            ->where(function ($q) {
                $q->whereNull('ruta_id')
                    ->orWhereHas('ruta', fn ($ruta) => $ruta->where('activo', true));
            });

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->integer('empresa_id'));
        }

        if ($request->filled('ruta_id')) {
            $query->where('ruta_id', $request->integer('ruta_id'));
        }

        if ($request->filled('search')) {
            $busqueda = mb_strtolower(trim((string) $request->input('search')));
            $query->whereRaw('LOWER(placa) LIKE ?', ['%'.$busqueda.'%']);
        }

        $micros = $query->orderBy('placa')
            ->paginate($request->integer('per_page', 50));

        return MicroResource::collection($micros)
            ->additional(['success' => true])
            ->response();
    }

    public function show(Request $request, Micro $micro): JsonResponse
    {
        $usuario = $this->usuarioActual();
        $esAdmin = $usuario && in_array($usuario->rol, ['admin_general', 'admin_empresa'], true);

        if ($esAdmin) {
            $this->verificaEmpresa((int) $micro->empresa_id, $usuario);
        } elseif (! in_array($micro->estado, ['disponible', 'en_recorrido'], true)) {
            abort(404, 'Micro no encontrado.');
        }

        $micro = Micro::query()
            ->with([
                'empresa',
                'ruta',
                'conductorActual.usuario',
                'ultimaPosicion',
                'posiciones' => fn ($q) => $q->latest('fecha_hora')->limit(20),
            ])
            ->findOrFail($micro->id);

        return (new MicroResource($micro))
            ->additional(['success' => true])
            ->response();
    }

    public function store(MicroRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $this->verificaEmpresa((int) $datos['empresa_id']);

        $micro = Micro::create([
            'empresa_id' => $datos['empresa_id'],
            'ruta_id' => $datos['ruta_id'] ?? null,
            'conductor_actual_id' => $datos['conductor_actual_id'] ?? null,
            'placa' => $datos['placa'],
            'capacidad_pasajeros' => $datos['capacidad_pasajeros'] ?? 20,
            'estado' => $datos['estado'] ?? 'disponible',
        ]);

        $micro->load(['empresa', 'ruta', 'conductorActual.usuario']);

        return (new MicroResource($micro))
            ->additional(['success' => true, 'message' => 'Micro creado correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(MicroRequest $request, Micro $micro): JsonResponse
    {
        $this->verificaEmpresa((int) $micro->empresa_id);
        $datos = $request->validated();

        if (isset($datos['empresa_id']) && (int) $datos['empresa_id'] !== (int) $micro->empresa_id) {
            $this->verificaEmpresa((int) $datos['empresa_id']);
        }

        $micro->fill(Arr::only($datos, [
            'empresa_id',
            'ruta_id',
            'conductor_actual_id',
            'placa',
            'capacidad_pasajeros',
            'estado',
        ]))->save();

        $micro->refresh()->load(['empresa', 'ruta', 'conductorActual.usuario', 'ultimaPosicion']);

        return (new MicroResource($micro))
            ->additional(['success' => true, 'message' => 'Micro actualizado correctamente.'])
            ->response();
    }

    public function destroy(Micro $micro): JsonResponse
    {
        $this->verificaEmpresa((int) $micro->empresa_id);

        if ($micro->estado === 'en_recorrido') {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar un micro que está en recorrido.',
            ], 409);
        }

        $micro->delete();

        return $this->respuesta(null, 'Micro eliminado correctamente.');
    }

    public function estado(Request $request, Micro $micro): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(['disponible', 'en_recorrido', 'mantenimiento', 'inactivo'])],
        ]);

        $this->verificaEmpresa((int) $micro->empresa_id);
        $micro->update(['estado' => $datos['estado']]);

        if ($micro->conductor_actual_id) {
            $micro->conductorActual?->update([
                'estado_operativo' => $datos['estado'] === 'en_recorrido' ? 'en_recorrido' : 'sin_conexion',
            ]);
        }

        $micro->refresh()->load(['empresa', 'ruta', 'conductorActual.usuario']);

        return (new MicroResource($micro))
            ->additional(['success' => true, 'message' => 'Estado del micro actualizado.'])
            ->response();
    }
}
