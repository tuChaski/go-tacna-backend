<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Ruta;
use App\Models\Micro;
use Illuminate\Http\Request;

class RutaController extends Controller
{
    /**
     * Catálogo público de rutas activas.
     */
    public function index()
    {
        $rutas = Ruta::with('empresa:id,nombre')->get();

        return response()->json([
            'success' => true,
            'data'    => $rutas
        ]);
    }

    /**
     * Detalle de una ruta específica con sus paraderos ordenados.
     */
    public function show($id)
    {
        $ruta = Ruta::with(['empresa:id,nombre', 'paraderos'])->find($id);

        if (! $ruta) {
            return response()->json([
                'success' => false,
                'message' => 'Ruta no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $ruta
        ]);
    }

    /**
     * Consulta pública de micros en servicio con su última ubicación reportada.
     */
    public function microsActivos()
    {
        $micros = Micro::where('activo', true)
            ->with(['empresa:id,nombre', 'conductor.ruta:id,nombre', 'ultimaPosicion'])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $micros
        ]);
    }
}