<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\User;

class DashboardController extends Controller
{
    /** /dashboard: envía a cada rol a su panel. */
    public function redirigir()
    {
        return redirect(match (auth()->user()->rol) {
            'administrador' => '/admin/dashboard',
            'docente' => '/docente/dashboard',
            default => '/control/dashboard',
        });
    }

    public function admin()
    {
        $stats = [
            'estudiantes' => Estudiante::count(),
            'docentes' => Docente::count(),
            'usuarios' => User::count(),
            'examenes' => Examen::where('estado', '<>', 'finalizado')->count(),
        ];

        $proximos = Examen::with(['materia', 'asignatura'])
            ->where('estado', '<>', 'finalizado')
            ->whereDate('fecha', '>=', today())
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->limit(4)->get();

        $periodo = now()->year . '-' . (now()->month <= 6 ? 'I' : 'II');

        return view('admin.dashboard', compact('stats', 'proximos', 'periodo'));
    }
}
