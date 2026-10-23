<?php

namespace App\Http\Requests\Suscripcion;

use Illuminate\Foundation\Http\FormRequest;

class MiSuscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:planes,id,activo,1'],
            'fecha_inicio' => ['required', 'date', 'after_or_equal:today'],
            'fecha_vencimiento' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.exists' => 'El plan no existe o no está activo.',
            'fecha_vencimiento.after_or_equal' => 'La fecha de vencimiento debe ser igual o posterior al inicio.',
        ];
    }
}
