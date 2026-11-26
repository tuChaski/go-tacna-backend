<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $id = $usuario?->id;
        $creando = $this->isMethod('post');

        return [
            'dni' => ['required', 'string', 'max:20', Rule::unique('usuarios', 'dni')->ignore($id)],
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'correo')->ignore($id)],
            'telefono' => ['required', 'string', 'max:30'],
            'rol' => ['required', Rule::in(['pasajero', 'conductor', 'admin_general', 'admin_empresa'])],
            'activo' => ['sometimes', 'boolean'],
            'password' => [$creando ? 'required' : 'nullable', 'string', 'min:8'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresas,id'],
            'cargo' => ['nullable', 'string', 'max:120'],
            'licencia_conducir' => ['nullable', 'string', 'max:120'],
            'estado_operativo' => ['nullable', Rule::in(['disponible', 'en_recorrido', 'sin_conexion'])],
        ];
    }

    public function messages(): array
    {
        return [
            'dni.unique' => 'El DNI ya está registrado.',
            'correo.unique' => 'El correo ya está registrado.',
        ];
    }
}
