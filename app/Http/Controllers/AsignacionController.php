<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AsignacionController extends Controller
{
    public function create(Estudiante $estudiante): View
    {
        $materias = Materia::orderBy('nombre')->get();
        $grupos = Grupo::with(['materia', 'docente'])->orderBy('nombre')->get();
        $asignaciones = Asignacion::with(['materia', 'grupo', 'docente'])
            ->where('estudiante_id', $estudiante->id)
            ->get();

        $catalogo = [
            'materias' => $materias->map(function (Materia $materia) {
                return [
                    'id' => $materia->id,
                    'nombre' => $materia->nombre,
                ];
            })->values(),
            'grupos' => $grupos->map(function (Grupo $grupo) {
                return [
                    'id' => $grupo->id,
                    'materia_id' => $grupo->materia_id,
                    'nombre' => $grupo->nombre,
                ];
            })->values(),
            'docentes' => $grupos->filter(function (Grupo $grupo) {
                return $grupo->docente_id !== null;
            })->map(function (Grupo $grupo) {
                return [
                    'id' => $grupo->docente_id,
                    'grupo_id' => $grupo->id,
                    'nombre' => $grupo->docente ? $grupo->docente->nombre : null,
                ];
            })->filter(function (array $docente) {
                return $docente['nombre'] !== null;
            })->values(),
        ];

        return view('asignaciones.create', compact('estudiante', 'catalogo', 'asignaciones'));
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

        $data['estudiante_id'] = $estudiante->id;

        $yaExiste = Asignacion::where('estudiante_id', $estudiante->id)
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
