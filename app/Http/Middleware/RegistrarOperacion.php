<?php

namespace App\Http\Middleware;

use App\Services\HistorialOperacionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RegistrarOperacion
{
    public function __construct(private readonly HistorialOperacionService $historial)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->registrar($request, null, $exception);
            throw $exception;
        }

        $this->registrar($request, $response);

        return $response;
    }

    private function registrar(Request $request, ?Response $response, ?Throwable $exception = null): void
    {
        $usuario = $request->user();
        $ruta = $request->route();

        if (! $usuario || ! $ruta) {
            return;
        }

        $tipoRespuesta = $response ? $this->tipoRespuestaDeIngreso($ruta, $response) : null;
        $rechazoEsperado = $tipoRespuesta !== null && $tipoRespuesta !== 'habilitado';
        $rechazo = $exception !== null
            || ($response && $response->getStatusCode() >= 400 && $tipoRespuesta === null)
            || $this->tieneErroresDeValidacion($request)
            || $this->tieneErrorEnSesion($request);

        $this->historial->registrar($request, $usuario, $ruta, $rechazo, $exception, $response?->getStatusCode(), $tipoRespuesta, $rechazoEsperado);
    }

    private function tipoRespuestaDeIngreso(Route $ruta, Response $response): ?string
    {
        $nombre = (string) $ruta->getName();
        if (! str_contains($nombre, 'verificar') && $nombre !== 'ingreso.ingreso') {
            return null;
        }

        $datos = json_decode($response->getContent(), true);
        $tipo = is_array($datos) ? ($datos['tipo'] ?? null) : null;

        return is_string($tipo) && $tipo !== 'error_guardado' ? $tipo : null;
    }

    private function tieneErroresDeValidacion(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $errores = $request->session()->get('errors');

        return is_object($errores) && method_exists($errores, 'any') && $errores->any();
    }

    private function tieneErrorEnSesion(Request $request): bool
    {
        return $request->hasSession() && filled($request->session()->get('error'));
    }
}
