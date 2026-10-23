<?php

namespace App\Http\Middleware;

use App\Models\Estudiante;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Portal del estudiante: exige que haya entrado con su código universitario y cédula. */
class EstudianteAutenticado
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get('estudiante_id');
        $estudiante = $id ? Estudiante::find($id) : null;

        if (! $estudiante || strtoupper((string) $estudiante->estado) === 'INACTIVO') {
            $request->session()->forget('estudiante_id');

            return redirect()->route('estudiante.login');
        }

        $request->attributes->set('estudiante', $estudiante);

        return $next($request);
    }
}
