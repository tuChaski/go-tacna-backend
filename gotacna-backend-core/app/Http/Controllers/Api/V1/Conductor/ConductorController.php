<?php

namespace App\Http\Controllers\Api\V1\Conductor;

use App\Http\Controllers\Controller;
use App\Models\Posicion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConductorController extends Controller
{
    /**
     * Obtener los datos del micro y la ruta asignada al conductor logueado.
     */
    public function perfil()
    {
        $user = auth('api')->user();
        
        $conductor = $user->conductor()
            ->with(['micro.empresa', 'ruta.paraderos'])
            ->first();

        if (! $conductor) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una unidad asignada como conductor'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $conductor
        ]);
    }

    /**
     * Registrar lectura de GPS del micro.
     */
    public function reportarPosicion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitud'   => 'required|numeric',
            'longitud'  => 'required|numeric',
            'velocidad' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = auth('api')->user();
        $conductor = $user->conductor;

        if (! $conductor || ! $conductor->micro_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene un micro asignado para emitir coordenadas'
            ], 400);
        }

        $posicion = Posicion::create([
            'micro_id'    => $conductor->micro_id,
            'latitud'     => $request->latitud,
            'longitud'    => $request->longitud,
            'velocidad'   => $request->input('velocidad', 0.0),
            'recorded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Posición registrada correctamente',
            'data'    => $posicion
        ], 201);
    }
}