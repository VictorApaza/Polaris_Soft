<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Estudiante;
use App\Services\InscripcionAsignaturaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AsignacionController extends Controller
{
    public function create(Estudiante $estudiante): View
    {
        $asignaciones = Asignacion::with(['materia', 'grupo', 'docente'])
            ->where('estudiante_id', $estudiante->getKey())
            ->get();

        return view('asignaciones.create', compact('estudiante', 'asignaciones'));
    }

    public function store(Request $request, Estudiante $estudiante, InscripcionAsignaturaService $inscripciones)
    {
        $data = $request->validate([
            'materia_id' => ['required', 'exists:materias,id'],
            'grupo_id' => ['required', 'exists:grupos,id'],
            'docente_id' => ['nullable', 'exists:docentes,id'],
        ], [
            'materia_id.required' => 'Selecciona una materia.',
            'grupo_id.required' => 'Selecciona un grupo.',
        ]);

        $data['estudiante_id'] = $estudiante->getKey();

        // Evita duplicar la misma materia para el mismo estudiante
        $yaExiste = Asignacion::where('estudiante_id', $estudiante->getKey())
            ->where('materia_id', $data['materia_id'])
            ->exists();

        if ($yaExiste) {
            return back()->withErrors([
                'materia_id' => 'Este estudiante ya tiene una asignación registrada para esa materia.',
            ])->withInput();
        }

        Asignacion::create($data);
        // La materia también queda como inscripción: así aparece su carrera y asignatura al registrar exámenes.
        $inscripciones->inscribir((int) $estudiante->getKey(), (int) $data['materia_id']);

        return back()->with('success', 'Asignación registrada correctamente.');
    }

    public function destroy(Asignacion $asignacion, InscripcionAsignaturaService $inscripciones)
    {
        $estudianteId = $asignacion->estudiante_id;
        $materia = $asignacion->materia;
        $asignacion->delete();
        $inscripciones->desinscribir((int) $estudianteId, $materia);

        return redirect()
            ->route('asignaciones.create', $estudianteId)
            ->with('success', 'Asignación eliminada.');
    }
}
