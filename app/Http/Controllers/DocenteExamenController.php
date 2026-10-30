<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Habilitacion;
use Illuminate\Http\Request;

/**
 * Panel del docente: lista de exámenes desde donde puede habilitar / inhabilitar estudiantes.
 * (La pantalla de habilitación en sí es la misma que usa el administrador: HabilitacionController.)
 */
class DocenteExamenController extends Controller
{
    public function index(Request $request)
    {
        // El docente ve solo los exámenes de las materias donde dicta.
        $examenes = Examen::with(['asignatura', 'materia'])
            ->deDocente($request->user())
            ->buscar($request->string('buscar')->toString())
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->paginate(8)
            ->withQueryString();

        // Cuántos habilitados / inhabilitados tiene cada examen de la página (una sola consulta).
        $conteos = Habilitacion::query()
            ->selectRaw('examen_id, estado, COUNT(*) as total')
            ->whereIn('examen_id', $examenes->pluck('id'))
            ->groupBy('examen_id', 'estado')
            ->get()
            ->groupBy('examen_id')
            ->map(fn ($filas) => $filas->pluck('total', 'estado'));

        return view('docente.examenes', ['examenes' => $examenes, 'conteos' => $conteos]);
    }
}
