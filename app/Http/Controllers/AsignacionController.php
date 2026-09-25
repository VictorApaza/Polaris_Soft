<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Estudiante;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AsignacionController extends Controller
{
    public function create(Estudiante $estudiante): View
    {
        return view('asignaciones.create', ['estudiante' => $estudiante]);
    }

    public function store(Request $request, Estudiante $estudiante)
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

        $yaExiste = Asignacion::where('estudiante_id', $estudiante->getKey())
            ->where('materia_id', $data['materia_id'])
            ->exists();

        if ($yaExiste) {
            return back()->withErrors([
                'materia_id' => 'Este estudiante ya tiene una asignación registrada para esa materia.',
            ])->withInput();
        }

        Asignacion::create($data);

        return back()->with('success', 'Asignación registrada correctamente.');
    }

    public function destroy(Asignacion $asignacion)
    {
        $estudianteId = $asignacion->estudiante_id;
        $asignacion->delete();

        return redirect()
            ->route('asignaciones.create', $estudianteId)
            ->with('success', 'Asignación eliminada.');
    }
}
