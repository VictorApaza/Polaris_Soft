<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RolMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        ...$roles
    ): Response {

        $usuario = $request->user();

        if (!$usuario || !$usuario->rol) {
            return response()->json([
                'message' => 'No autorizado.'
            ], 403);
        }

        if (!in_array(
            $usuario->rol->nombre,
            $roles
        )) {
            return response()->json([
                'message' => 'No tiene permisos para acceder a este módulo.'
            ], 403);
        }

        return $next($request);
    }
}