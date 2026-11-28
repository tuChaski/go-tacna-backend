<?php

namespace App\Http\Controllers\Api\V1\Pasajero;

use App\Http\Controllers\Controller;
use App\Http\Resources\FavoritoResource;
use App\Models\Favorito;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoritoController extends Controller
{
    public function index(Request $request)
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        $favoritos = $usuario->favoritos()->with(['ruta', 'micro'])
            ->latest('id')->paginate($request->integer('per_page', 15));

        return FavoritoResource::collection($favoritos)
            ->additional(['success' => true])->response();
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'ruta_id' => ['nullable', 'integer', 'exists:rutas,id'],
            'micro_id' => ['nullable', 'integer', 'exists:micros,id'],
        ]);

        if (isset($datos['ruta_id']) === isset($datos['micro_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Selecciona una ruta o un micro, pero no ambos.',
            ], 422);
        }

        /** @var Usuario $usuario */
        $usuario = auth('api')->user();
        $favorito = $usuario->favoritos()->firstOrCreate([
            'ruta_id' => $datos['ruta_id'] ?? null,
            'micro_id' => $datos['micro_id'] ?? null,
        ]);

        return (new FavoritoResource($favorito->load(['ruta', 'micro'])))
            ->additional(['success' => true])
            ->response()
            ->setStatusCode($favorito->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Favorito $favorito): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();
        abort_unless((int) $favorito->usuario_id === (int) $usuario->id, 404);

        $favorito->delete();

        return $this->respuesta(null, 'Favorito eliminado.');
    }
}
