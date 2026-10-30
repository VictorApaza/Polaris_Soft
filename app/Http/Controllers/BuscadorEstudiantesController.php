<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
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
            ->when($request->user()->rol === 'docente', fn ($w) => $w->whereIn('id_estudiante', $this->idsDelDocente($request)))
            ->when($request->filled('carrera'), fn ($w) => $w->where('carrera', $request->carrera))
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate(10)->withQueryString();

        $carreras = Estudiante::whereNotNull('carrera')
            ->when($request->user()->rol === 'docente', fn ($w) => $w->whereIn('id_estudiante', $this->idsDelDocente($request)))
            ->distinct()->orderBy('carrera')->pluck('carrera');

        return view('docente.estudiantes', compact('estudiantes', 'carreras', 'q'));
    }

    public function show(Request $request, Estudiante $estudiante)
    {
        abort_if($request->user()->rol === 'docente' && ! in_array($estudiante->getKey(), $this->idsDelDocente($request)), 403, 'Ese estudiante no es de ninguno de tus grupos.');

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

    /** Estudiantes asignados a los grupos que dicta el docente. */
    private function idsDelDocente(Request $request): array
    {
        $docente = Docente::paraUsuario($request->user());

        if (! $docente) {
            return [];
        }

        $grupoIds = Grupo::where('docente_id', $docente->id)->pluck('id');

        return Asignacion::where(fn ($w) => $w->whereIn('grupo_id', $grupoIds)->orWhere('docente_id', $docente->id))
            ->pluck('estudiante_id')->unique()->map(fn ($v) => (int) $v)->all();
    }
}
