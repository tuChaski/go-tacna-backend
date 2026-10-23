<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ruta\RutaRequest;
use App\Http\Resources\RutaResource;
use App\Models\Ruta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RutaController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $this->usuarioActual();
        $esAdmin = $usuario && in_array($usuario->rol, ['admin_general', 'admin_empresa'], true);

        $query = $this->consultaConTrazado()->with('empresa');

        if ($esAdmin) {
            $this->aplicaAlcanceEmpresa($query, 'empresa_id', $usuario);

            if ($request->boolean('solo_activos')) {
                $query->where('activo', true);
            }
        } else {
            $query->where('activo', true);
        }

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->integer('empresa_id'));
        }

        if ($request->filled('search')) {
            $busqueda = mb_strtolower(trim((string) $request->input('search')));
            $query->whereRaw('LOWER(nombre_ruta) LIKE ?', ['%'.$busqueda.'%']);
        }

        $rutas = $query->orderBy('nombre_ruta')
            ->paginate($request->integer('per_page', 15));

        return RutaResource::collection($rutas)
            ->additional(['success' => true])
            ->response();
    }

    public function show(Request $request, Ruta $ruta): JsonResponse
    {
        $usuario = $this->usuarioActual();
        $esAdmin = $usuario && in_array($usuario->rol, ['admin_general', 'admin_empresa'], true);

        if ($esAdmin) {
            $this->verificaEmpresa((int) $ruta->empresa_id, $usuario);
        } elseif (! $ruta->activo) {
            abort(404, 'Ruta no encontrada.');
        }

        $ruta = $this->consultaConTrazado()
            ->with(['empresa', 'micros'])
            ->findOrFail($ruta->id);

        return (new RutaResource($ruta))
            ->additional(['success' => true])
            ->response();
    }

    public function store(RutaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $this->verificaEmpresa((int) $datos['empresa_id']);

        $trazado = $this->normalizarTrazado($datos['trazado_ruta']);

        $ruta = DB::transaction(function () use ($datos, $trazado) {
            $ruta = Ruta::create([
                'empresa_id' => $datos['empresa_id'],
                'nombre_ruta' => $datos['nombre_ruta'],
                'activo' => $datos['activo'] ?? true,
            ]);

            Ruta::guardarTrazado($ruta->id, $trazado);

            return $ruta;
        });

        $ruta->refresh()->load('empresa');

        return (new RutaResource($ruta))
            ->additional(['success' => true, 'message' => 'Ruta creada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(RutaRequest $request, Ruta $ruta): JsonResponse
    {
        $this->verificaEmpresa((int) $ruta->empresa_id);
        $datos = $request->validated();

        if (isset($datos['empresa_id']) && (int) $datos['empresa_id'] !== (int) $ruta->empresa_id) {
            $this->verificaEmpresa((int) $datos['empresa_id']);
        }

        $ruta->fill(Arr::only($datos, ['empresa_id', 'nombre_ruta', 'activo']))->save();

        if (array_key_exists('trazado_ruta', $datos)) {
            Ruta::guardarTrazado($ruta->id, $this->normalizarTrazado($datos['trazado_ruta']));
        }

        $ruta->refresh()->load('empresa');

        return (new RutaResource($ruta))
            ->additional(['success' => true, 'message' => 'Ruta actualizada correctamente.'])
            ->response();
    }

    public function destroy(Ruta $ruta): JsonResponse
    {
        $this->verificaEmpresa((int) $ruta->empresa_id);

        if ($ruta->micros()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar una ruta que tiene micros asignados.',
            ], 409);
        }

        $ruta->delete();

        return $this->respuesta(null, 'Ruta eliminada correctamente.');
    }

    public function estado(Request $request, Ruta $ruta): JsonResponse
    {
        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $this->verificaEmpresa((int) $ruta->empresa_id);
        $ruta->update(['activo' => (bool) $datos['activo']]);

        return (new RutaResource($ruta->refresh()->load('empresa')))
            ->additional(['success' => true, 'message' => 'Estado de la ruta actualizado.'])
            ->response();
    }

    private function consultaConTrazado(): Builder
    {
        $query = Ruta::query();

        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->selectRaw('rutas.*, ST_AsGeoJSON(rutas.trazado_ruta) AS trazado_ruta_geojson');
        }

        return $query;
    }

    /**
     * Normaliza GeoJSON o WKT a un GeoJSON LineString.
     */
    private function normalizarTrazado(mixed $trazado): array
    {
        if (is_string($trazado)) {
            preg_match('/LINESTRING\s*\((.*)\)/is', $trazado, $coincidencias);
            $puntos = [];

            foreach (explode(',', $coincidencias[1] ?? '') as $punto) {
                $partes = preg_split('/\s+/', trim($punto));

                if (count($partes) >= 2) {
                    $puntos[] = [(float) $partes[0], (float) $partes[1]];
                }
            }

            return ['type' => 'LineString', 'coordinates' => $puntos];
        }

        $coordenadas = $trazado['coordinates'] ?? $trazado;

        return [
            'type' => 'LineString',
            'coordinates' => array_map(
                fn ($punto) => [(float) $punto[0], (float) $punto[1]],
                $coordenadas
            ),
        ];
    }
}
