<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    protected function usuarioActual(): ?Usuario
    {
        try {
            /** @var Usuario|null $usuario */
            $usuario = Auth::guard('api')->user();

            return $usuario;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function esAdminGeneral(?Usuario $usuario = null): bool
    {
        $usuario ??= $this->usuarioActual();

        return $usuario?->rol === 'admin_general';
    }

    protected function esAdminEmpresa(?Usuario $usuario = null): bool
    {
        $usuario ??= $this->usuarioActual();

        return $usuario?->rol === 'admin_empresa';
    }

    /**
     * Empresas que puede administrar el usuario. Un admin general no se limita.
     *
     * @return array<int, int>
     */
    protected function empresaIdsPermitidas(?Usuario $usuario = null): array
    {
        $usuario ??= $this->usuarioActual();

        if (! $usuario || $usuario->rol === 'admin_general') {
            return [];
        }

        return $usuario->administradorEmpresaPerfiles()
            ->pluck('empresa_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function aplicaAlcanceEmpresa(
        Builder $query,
        string $columna = 'empresa_id',
        ?Usuario $usuario = null
    ): Builder {
        $usuario ??= $this->usuarioActual();

        if (! $usuario || $usuario->rol === 'admin_general') {
            return $query;
        }

        if ($usuario->rol !== 'admin_empresa') {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($columna, $this->empresaIdsPermitidas($usuario));
    }

    protected function verificaEmpresa(int $empresaId, ?Usuario $usuario = null): void
    {
        $usuario ??= $this->usuarioActual();

        if (! $usuario || $usuario->rol === 'admin_general') {
            return;
        }

        abort_unless(
            $usuario->rol === 'admin_empresa'
                && in_array($empresaId, $this->empresaIdsPermitidas($usuario), true),
            403,
            'No tienes acceso a esta empresa.'
        );
    }

    protected function respuesta(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        $payload = ['success' => true];

        if ($message !== '') {
            $payload['message'] = $message;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }
}
