<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Estudiante;
use App\Services\InscripcionAsignaturaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'docente_id' => ['required', 'exists:docentes,id'],
        ], [
            'materia_id.required' => 'Selecciona una materia.',
            'grupo_id.required' => 'Selecciona un grupo.',
        ]);

        $grupo = \App\Models\Grupo::findOrFail($data['grupo_id']);
        if ((int) $grupo->materia_id !== (int) $data['materia_id']) {
            throw ValidationException::withMessages(['grupo_id' => 'Selecciona un grupo de la materia elegida.']);
        }
        if ((int) $grupo->docente_id !== (int) $data['docente_id']) {
            throw ValidationException::withMessages(['docente_id' => 'Selecciona un docente asignado al grupo elegido.']);
        }

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

        DB::transaction(function () use ($data, $estudiante, $inscripciones) {
            Asignacion::create($data);
            $inscripciones->inscribir((int) $estudiante->getKey(), (int) $data['materia_id']);
        });

        return back()->with('success', 'Asignación registrada correctamente.');
    }

    public function destroy(Asignacion $asignacion, InscripcionAsignaturaService $inscripciones)
    {
        $estudianteId = $asignacion->estudiante_id;
        $materia = $asignacion->materia;
        DB::transaction(function () use ($asignacion, $estudianteId, $materia, $inscripciones) {
            $asignacion->delete();
            $inscripciones->desinscribir((int) $estudianteId, $materia);
        });

        return redirect()
            ->route('asignaciones.create', $estudianteId)
            ->with('success', 'Asignación eliminada.');
    }
}
