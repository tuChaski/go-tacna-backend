<?php

namespace App\Http\Requests\Micro;

use App\Models\ConductorPerfil;
use App\Models\Ruta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MicroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $micro = $this->route('micro');
        $creando = $this->isMethod('post');

        return [
            'empresa_id' => [$creando ? 'required' : 'sometimes', 'integer', 'exists:empresas,id'],
            'ruta_id' => ['nullable', 'integer', 'exists:rutas,id'],
            'conductor_actual_id' => array_merge(
                ['nullable', 'integer', 'exists:conductor_perfiles,id'],
                $this->filled('conductor_actual_id')
                    ? [Rule::unique('micros', 'conductor_actual_id')->ignore($micro?->id)]
                    : []
            ),
            'placa' => [
                $creando ? 'required' : 'sometimes',
                'string',
                'max:20',
                Rule::unique('micros', 'placa')->ignore($micro?->id),
            ],
            'capacidad_pasajeros' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'estado' => ['sometimes', Rule::in(['disponible', 'en_recorrido', 'mantenimiento', 'inactivo'])],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $micro = $this->route('micro');
                $empresaId = (int) $this->input('empresa_id', $micro?->empresa_id);

                if ($this->filled('ruta_id')) {
                    $ruta = Ruta::find($this->input('ruta_id'));

                    if ($ruta && (int) $ruta->empresa_id !== $empresaId) {
                        $validator->errors()->add('ruta_id', 'La ruta pertenece a otra empresa.');
                    }
                }

                if ($this->filled('conductor_actual_id')) {
                    $conductor = ConductorPerfil::find($this->input('conductor_actual_id'));

                    if ($conductor && (int) $conductor->empresa_id !== $empresaId) {
                        $validator->errors()->add('conductor_actual_id', 'El conductor pertenece a otra empresa.');
                    }
                }

                if ($micro && $micro->estado === 'en_recorrido') {
                    if ($this->has('ruta_id') && (int) $this->input('ruta_id') !== (int) $micro->ruta_id) {
                        $validator->errors()->add('ruta_id', 'No puedes cambiar la ruta de un micro en recorrido.');
                    }

                    if ($this->has('conductor_actual_id')
                        && (int) $this->input('conductor_actual_id') !== (int) $micro->conductor_actual_id) {
                        $validator->errors()->add(
                            'conductor_actual_id',
                            'No puedes cambiar el conductor de un micro en recorrido.'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'placa.unique' => 'La placa ya está registrada.',
            'conductor_actual_id.unique' => 'El conductor ya está asignado a otro micro.',
        ];
    }
}
