<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxyController extends Controller
{
    /**
     * Reenvía la petición al backend core conservando método, ruta, query,
     * cabeceras y cuerpo JSON.
     */
    public function __invoke(Request $request): Response
    {
        $contentType = (string) $request->header('Content-Type', '');

        if (str_starts_with($contentType, 'multipart/form-data')) {
            return response(
                json_encode([
                    'success' => false,
                    'message' => 'El gateway no admite cargas multipart. Envía la información en JSON.',
                ]),
                415
            )->header('Content-Type', 'application/json');
        }

        $url = $this->construirUrl($request);
        $metodo = $request->method();
        $cuerpo = $request->getContent();

        try {
            $cliente = Http::withHeaders($this->cabecerasEntrada($request))
                ->timeout((int) config('gateway.timeout', 60))
                ->connectTimeout(5)
                ->withOptions([
                    'timeout' => (int) config('gateway.timeout', 60),
                    'connect_timeout' => 5,
                    'http_errors' => false,
                    'verify' => (bool) config('gateway.verify_tls', true),
                    'allow_redirects' => false,
                ]);

            if (! in_array($metodo, ['GET', 'HEAD'], true) && $cuerpo !== '') {
                $cliente = $cliente->withBody(
                    $cuerpo,
                    $contentType !== '' ? $contentType : 'application/json'
                );
            }

            $respuesta = $cliente->send($metodo, $url);
        } catch (ConnectionException $e) {
            Log::warning('El gateway no pudo conectar con el core.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return response(
                json_encode([
                    'success' => false,
                    'message' => 'No se pudo conectar con el servicio principal. Intenta nuevamente.',
                ]),
                502
            )->header('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            Log::error('Error inesperado en el gateway.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return response(
                json_encode([
                    'success' => false,
                    'message' => 'Error interno en el gateway.',
                ]),
                500
            )->header('Content-Type', 'application/json');
        }

        return $this->respuestaProxy($respuesta);
    }

    private function construirUrl(Request $request): string
    {
        $base = rtrim((string) config('gateway.core_api_url'), '/');
        $path = ltrim($request->path(), '/');

        $url = $base.'/'.$path;
        $query = $request->getQueryString();

        if ($query !== null && $query !== '') {
            $url .= '?'.$query;
        }

        return $url;
    }

    /**
     * @return array<string, string>
     */
    private function cabecerasEntrada(Request $request): array
    {
        $excluir = [
            'host',
            'content-length',
            'connection',
            'accept-encoding',
            'cookie',
            'origin',
            'referer',
            'transfer-encoding',
        ];

        $cabeceras = [];

        foreach ($request->headers->all() as $nombre => $valores) {
            if (in_array(strtolower($nombre), $excluir, true)) {
                continue;
            }

            $cabeceras[$nombre] = implode(', ', $valores);
        }

        if (! isset($cabeceras['accept']) && ! isset($cabeceras['Accept'])) {
            $cabeceras['Accept'] = 'application/json';
        }

        $cabeceras['X-Forwarded-For'] = (string) $request->ip();
        $cabeceras['X-Forwarded-Host'] = $request->getHttpHost();
        $cabeceras['X-Forwarded-Port'] = (string) $request->getPort();
        $cabeceras['X-Forwarded-Proto'] = $request->getScheme();
        $cabeceras['X-Gateway'] = 'gotacna-api-gateway';

        return $cabeceras;
    }

    private function respuestaProxy(ClientResponse $respuesta): Response
    {
        $excluir = [
            'transfer-encoding',
            'connection',
            'content-length',
            'content-encoding',
        ];

        $cabeceras = [];

        foreach ($respuesta->headers() as $nombre => $valores) {
            $nombreMinusculas = strtolower($nombre);

            if (
                in_array($nombreMinusculas, $excluir, true)
                || str_starts_with($nombreMinusculas, 'access-control-')
            ) {
                continue;
            }

            $cabeceras[$nombre] = implode(', ', $valores);
        }

        if (! isset($cabeceras['Content-Type'])) {
            $cabeceras['Content-Type'] = 'application/json';
        }

        return response($respuesta->body(), $respuesta->status())
            ->withHeaders($cabeceras);
    }
}
