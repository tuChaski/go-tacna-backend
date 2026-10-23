<?php

namespace App\Http\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'nombre_plan' => [
                'required',
                'string',
                'max:120',
                Rule::unique('planes', 'nombre_plan')->ignore($plan?->id),
            ],
            'costo' => ['required', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_plan.unique' => 'El nombre del plan ya existe.',
        ];
    }
}
