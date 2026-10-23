<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'correo' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
