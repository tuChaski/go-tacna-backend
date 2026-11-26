<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GeoJsonLineString implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            if (! preg_match('/^LINESTRING\s*\(/i', trim($value))) {
                $fail('El trazado debe ser un GeoJSON LineString o un WKT LINESTRING válido.');
            }

            return;
        }

        if (! is_array($value)) {
            $fail('El trazado debe ser un GeoJSON LineString o un WKT LINESTRING válido.');

            return;
        }

        $coordinates = $value['coordinates'] ?? $value;

        if (isset($value['type']) && strtoupper((string) $value['type']) !== 'LINESTRING') {
            $fail('El tipo de geometría debe ser LineString.');

            return;
        }

        if (! is_array($coordinates) || count($coordinates) < 2) {
            $fail('El trazado debe contener al menos dos coordenadas.');

            return;
        }

        foreach ($coordinates as $punto) {
            if (! is_array($punto) || count($punto) < 2 || ! is_numeric($punto[0]) || ! is_numeric($punto[1])) {
                $fail('Cada coordenada debe tener longitud y latitud numéricas.');

                return;
            }

            [$longitud, $latitud] = [(float) $punto[0], (float) $punto[1]];

            if ($longitud < -180 || $longitud > 180 || $latitud < -90 || $latitud > 90) {
                $fail('Las coordenadas del trazado están fuera de rango.');

                return;
            }
        }
    }
}
