<?php

namespace App\Http\Requests\Suscripcion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $suscripcion = $this->route('suscripcione') ?? $this->route('suscripcion');
        $creando = $this->isMethod('post');

        return [
            'usuario_id' => [
                $creando ? 'required' : 'sometimes',
                'integer',
                Rule::exists('usuarios', 'id')->where('rol', 'pasajero'),
            ],
            'plan_id' => [$creando ? 'required' : 'sometimes', 'integer', 'exists:planes,id'],
            'fecha_inicio' => [$creando ? 'required' : 'sometimes', 'date'],
            'fecha_vencimiento' => [$creando ? 'required' : 'sometimes', 'date', 'after_or_equal:fecha_inicio'],
            'estado' => ['sometimes', Rule::in(['activa', 'vencida', 'cancelada'])],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario_id.exists' => 'El usuario debe existir y tener rol pasajero.',
            'fecha_vencimiento.after_or_equal' => 'La fecha de vencimiento debe ser igual o posterior al inicio.',
        ];
    }
}
