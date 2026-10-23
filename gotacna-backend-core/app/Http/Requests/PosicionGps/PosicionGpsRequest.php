<?php

namespace App\Http\Requests\PosicionGps;

use Illuminate\Foundation\Http\FormRequest;

class PosicionGpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('latitud') && $this->has('lat')) {
            $this->merge(['latitud' => $this->input('lat')]);
        }

        if (! $this->has('longitud') && $this->has('lng')) {
            $this->merge(['longitud' => $this->input('lng')]);
        }

        if (! $this->has('velocidad') && $this->has('speed')) {
            $this->merge(['velocidad' => $this->input('speed')]);
        }
    }

    public function rules(): array
    {
        $creando = $this->isMethod('post');

        return [
            'micro_id' => [$creando ? 'required' : 'sometimes', 'integer', 'exists:micros,id'],
            'latitud' => [$creando ? 'required' : 'sometimes', 'numeric', 'between:-90,90'],
            'longitud' => [$creando ? 'required' : 'sometimes', 'numeric', 'between:-180,180'],
            'velocidad' => ['sometimes', 'numeric', 'min:0'],
            'fecha_hora' => ['sometimes', 'date'],
        ];
    }
}
