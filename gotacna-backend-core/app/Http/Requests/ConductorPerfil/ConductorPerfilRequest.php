<?php

namespace App\Http\Requests\ConductorPerfil;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConductorPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $perfil = $this->route('conductore') ?? $this->route('conductor');
        $creando = $this->isMethod('post');

        if (! $creando) {
            return [
                'empresa_id' => ['sometimes', 'integer', 'exists:empresas,id'],
                'licencia_conducir' => ['sometimes', 'string', 'max:120'],
                'estado_operativo' => ['sometimes', Rule::in(['disponible', 'en_recorrido', 'sin_conexion'])],
            ];
        }

        return [
            'usuario_id' => [
                'nullable',
                'integer',
                Rule::exists('usuarios', 'id')->whereIn('rol', ['pasajero', 'conductor']),
                Rule::unique('conductor_perfiles', 'usuario_id')->ignore($perfil?->id),
            ],
            'nombre' => ['required_without:usuario_id', 'string', 'max:120'],
            'apellido' => ['required_without:usuario_id', 'string', 'max:120'],
            'dni' => ['required_without:usuario_id', 'string', 'max:20', 'unique:usuarios,dni'],
            'correo' => ['required_without:usuario_id', 'email', 'max:150', 'unique:usuarios,correo'],
            'telefono' => ['required_without:usuario_id', 'string', 'max:30'],
            'password' => ['required_without:usuario_id', 'string', 'min:8'],
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'licencia_conducir' => ['required', 'string', 'max:120'],
            'estado_operativo' => ['sometimes', Rule::in(['disponible', 'en_recorrido', 'sin_conexion'])],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario_id.exists' => 'El usuario no existe o no puede ser conductor.',
            'usuario_id.unique' => 'El usuario ya tiene un perfil de conductor.',
            'dni.unique' => 'El DNI ya está registrado.',
            'correo.unique' => 'El correo ya está registrado.',
        ];
    }
}
