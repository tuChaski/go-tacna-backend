<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('correo') && $this->has('email')) {
            $this->merge(['correo' => $this->input('email')]);
        }

        if (! $this->has('password') && $this->has('contrasena')) {
            $this->merge(['password' => $this->input('contrasena')]);
        }
    }

    public function rules(): array
    {
        return [
            'dni' => ['required', 'string', 'max:20', 'unique:usuarios,dni'],
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'email', 'max:150', 'unique:usuarios,correo'],
            'telefono' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
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
