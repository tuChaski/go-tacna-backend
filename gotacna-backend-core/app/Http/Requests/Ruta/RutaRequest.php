<?php

namespace App\Http\Requests\Ruta;

use App\Rules\GeoJsonLineString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RutaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ruta = $this->route('ruta');
        $creando = $this->isMethod('post');
        $empresaId = $this->input('empresa_id', $ruta?->empresa_id);

        return [
            'empresa_id' => [$creando ? 'required' : 'sometimes', 'integer', 'exists:empresas,id'],
            'nombre_ruta' => [
                $creando ? 'required' : 'sometimes',
                'string',
                'max:180',
                Rule::unique('rutas', 'nombre_ruta')
                    ->where('empresa_id', $empresaId)
                    ->ignore($ruta?->id),
            ],
            'trazado_ruta' => [$creando ? 'required' : 'sometimes', new GeoJsonLineString],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_ruta.unique' => 'La empresa ya tiene una ruta con ese nombre.',
        ];
    }
}
