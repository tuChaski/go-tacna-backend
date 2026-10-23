<?php

namespace App\Http\Requests\AdministradorEmpresaPerfil;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdministradorEmpresaPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $perfil = $this->route('administradore_empresa') ?? $this->route('administrador_empresa');
        $id = $perfil?->id;

        return [
            'usuario_id' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id')->where('rol', 'admin_empresa'),
                Rule::unique('administrador_empresa_perfiles', 'usuario_id')
                    ->where('empresa_id', $this->input('empresa_id'))
                    ->ignore($id),
            ],
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'cargo' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario_id.exists' => 'El usuario no existe o no tiene el rol admin_empresa.',
            'usuario_id.unique' => 'El usuario ya está vinculado a esta empresa.',
        ];
    }
}
