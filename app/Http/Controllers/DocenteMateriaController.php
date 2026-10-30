<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Docente;
use App\Models\Grupo;
use Illuminate\Http\Request;

/** "Materias" del docente: solo las que dicta (donde es el docente de algún grupo). */
class DocenteMateriaController extends Controller
{
    public function index(Request $request)
    {
        $docente = Docente::paraUsuario($request->user());

        $grupos = $docente
            ? Grupo::with('materia')->where('docente_id', $docente->id)->orderBy('nombre')->get()
            : collect();

        $estudiantesPorGrupo = Asignacion::selectRaw('grupo_id, COUNT(DISTINCT estudiante_id) as total')
            ->whereIn('grupo_id', $grupos->pluck('id'))
            ->groupBy('grupo_id')->pluck('total', 'grupo_id');

        $materias = $grupos->filter(fn ($g) => $g->materia)
            ->groupBy('materia_id')
            ->map(fn ($delGrupo) => ['materia' => $delGrupo->first()->materia, 'grupos' => $delGrupo])
            ->sortBy(fn ($f) => $f['materia']->nombre)
            ->values();

        return view('docente.materias', [
            'docente' => $docente,
            'materias' => $materias,
            'estudiantesPorGrupo' => $estudiantesPorGrupo,
        ]);
    }
}
