<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\TransparenciaIngreso;
use Illuminate\Http\Request;

/** Buscador de estudiantes para docentes (y administrador): por nombre, código universitario o cédula. */
class BuscadorEstudiantesController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->string('q')->toString());

        $estudiantes = Estudiante::buscar($q)
            ->when($request->filled('carrera'), fn ($w) => $w->where('carrera', $request->carrera))
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate(10)->withQueryString();

        $carreras = Estudiante::whereNotNull('carrera')->distinct()->orderBy('carrera')->pluck('carrera');

        return view('docente.estudiantes', compact('estudiantes', 'carreras', 'q'));
    }

    public function show(Estudiante $estudiante)
    {
        $habilitaciones = Habilitacion::with(['examen.asignatura', 'examen.materia', 'motivo'])
            ->where('estudiante_id', $estudiante->getKey())
            ->get()
            ->sortByDesc(fn ($h) => optional($h->examen)->fecha)
            ->values();

        $ingresos = TransparenciaIngreso::where('estudiante_id', $estudiante->getKey())
            ->pluck('fecha_hora_ingreso', 'examen_id');

        $incidencias = Incidencia::with('examen.asignatura', 'examen.materia')
            ->where('estudiante_id', $estudiante->getKey())
            ->orderByDesc('fecha_hora')->get();

        return view('docente.estudiante', compact('estudiante', 'habilitaciones', 'ingresos', 'incidencias'));
    }
}
