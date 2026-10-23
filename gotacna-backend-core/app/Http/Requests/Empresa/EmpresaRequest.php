<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresa = $this->route('empresa');
        $id = $empresa?->id;

        return [
            'ruc' => ['required', 'string', 'size:11', Rule::unique('empresas', 'ruc')->ignore($id)],
            'razon_social' => ['required', 'string', 'max:180'],
            'telefono' => ['required', 'string', 'max:30'],
            'direccion' => ['required', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ruc.unique' => 'El RUC ya está registrado.',
        ];
    }
}
