<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Http\Request;

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

        return view('admin.dashboard', [
            'stats' => $stats,
            'proximos' => $this->proximos(request()),
            'periodo' => $this->periodo(),
        ]);
    }

    /** Inicio del docente: sus materias, grupos y estudiantes, y sus próximos exámenes con acciones directas. */
    public function docente(Request $request)
    {
        $docente = Docente::paraUsuario($request->user());
        $grupos = $docente ? Grupo::where('docente_id', $docente->id)->get(['id', 'materia_id']) : collect();

        $stats = [
            'materias' => $grupos->pluck('materia_id')->unique()->count(),
            'grupos' => $grupos->count(),
            'estudiantes' => $docente ? count($docente->estudianteIds()) : 0,
            'examenes' => Examen::query()->deDocente($request->user())->where('estado', '<>', 'finalizado')->count(),
        ];

        return view('docente.dashboard', [
            'docente' => $docente,
            'stats' => $stats,
            'proximos' => $this->proximos($request),
            'periodo' => $this->periodo(),
        ]);
    }

    /** Inicio del personal de control: lo que necesita el día del examen, en un solo lugar. */
    public function control(Request $request)
    {
        $stats = [
            'examenes' => Examen::where('estado', '<>', 'finalizado')->count(),
            'hoy' => Examen::whereDate('fecha', today())->where('estado', '<>', 'finalizado')->count(),
        ];

        return view('control.dashboard', [
            'stats' => $stats,
            'proximos' => $this->proximos($request),
            'periodo' => $this->periodo(),
        ]);
    }

    /** Próximos exámenes (los más cercanos primero); el docente solo ve los suyos. */
    private function proximos(Request $request)
    {
        return Examen::with(['materia', 'asignatura'])
            ->deDocente($request->user())
            ->where('estado', '<>', 'finalizado')
            ->whereDate('fecha', '>=', today())
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->limit(5)->get();
    }

    private function periodo(): string
    {
        return now()->year.'-'.(now()->month <= 6 ? 'I' : 'II');
    }
}
