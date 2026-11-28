<?php

namespace App\Http\Requests\Conductor;

use Illuminate\Foundation\Http\FormRequest;

class ReportarPosicionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('tracking.position')) {
            return;
        }

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
        if ($this->routeIs('tracking.position')) {
            $numeroJson = static function (string $campo, mixed $valor, \Closure $fallar): void {
                if (! is_int($valor) && ! is_float($valor)) {
                    $fallar("El campo {$campo} debe ser un número JSON.");
                }
            };

            return [
                'lat' => ['required', 'numeric', 'between:-90,90', $numeroJson],
                'lng' => ['required', 'numeric', 'between:-180,180', $numeroJson],
                'speed' => ['required', 'numeric', 'min:0', $numeroJson],
            ];
        }

        return [
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'velocidad' => ['sometimes', 'numeric', 'min:0'],
            'fecha_hora' => ['sometimes', 'date'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if (! $this->routeIs('tracking.position')) {
                return;
            }

            foreach (array_diff(array_keys($this->all()), ['lat', 'lng', 'speed']) as $campo) {
                $validator->errors()->add($campo, 'Campo no permitido en el contrato de tracking.');
            }
        }];
    }
}
