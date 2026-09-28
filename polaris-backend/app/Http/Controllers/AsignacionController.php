<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Estudiante;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AsignacionController extends Controller
{
    public function index()
    {
        $asignaciones = Asignacion::with([
            'estudiante',
            'materia',
            'grupo',
            'docente'
        ])
        ->latest()
        ->get();

        return response()->json([
            'success' => true,
            'data' => $asignaciones
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'estudiante_id' => [
                'required',
                'integer',
                'exists:estudiante,id_estudiante'
            ],
            'materia_id' => [
                'required',
                'integer',
                'exists:materias,id'
            ],
            'grupo_id' => [
                'required',
                'integer',
                'exists:grupos,id'
            ],
            'docente_id' => [
                'required',
                'integer',
                'exists:docentes,id'
            ],
        ]);

        $grupo = Grupo::findOrFail($datos['grupo_id']);

        if ((int) $grupo->materia_id !== (int) $datos['materia_id']) {
            throw ValidationException::withMessages([
                'grupo_id' => 'El grupo seleccionado no pertenece a la materia seleccionada.'
            ]);
        }

        if ((int) $grupo->docente_id !== (int) $datos['docente_id']) {
            throw ValidationException::withMessages([
                'docente_id' => 'El docente seleccionado no corresponde al grupo seleccionado.'
            ]);
        }

        $existe = Asignacion::where('estudiante_id', $datos['estudiante_id'])
            ->where('materia_id', $datos['materia_id'])
            ->where('grupo_id', $datos['grupo_id'])
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'estudiante_id' => 'El estudiante ya tiene esta asignación.'
            ]);
        }

        $asignacion = Asignacion::create($datos);

        return response()->json([
            'success' => true,
            'message' => 'Asignación registrada correctamente.',
            'data' => $asignacion->load([
                'estudiante',
                'materia',
                'grupo',
                'docente'
            ])
        ], 201);
    }

    public function show(Asignacion $asignacion)
    {
        return response()->json([
            'success' => true,
            'data' => $asignacion->load([
                'estudiante',
                'materia',
                'grupo',
                'docente'
            ])
        ]);
    }

    public function update(Request $request, Asignacion $asignacion)
    {
        $datos = $request->validate([
            'estudiante_id' => [
                'required',
                'integer',
                'exists:estudiante,id_estudiante'
            ],
            'materia_id' => [
                'required',
                'integer',
                'exists:materias,id'
            ],
            'grupo_id' => [
                'required',
                'integer',
                'exists:grupos,id'
            ],
            'docente_id' => [
                'required',
                'integer',
                'exists:docentes,id'
            ],
        ]);

        $grupo = Grupo::findOrFail($datos['grupo_id']);

        if ((int) $grupo->materia_id !== (int) $datos['materia_id']) {
            throw ValidationException::withMessages([
                'grupo_id' => 'El grupo seleccionado no pertenece a la materia seleccionada.'
            ]);
        }

        if ((int) $grupo->docente_id !== (int) $datos['docente_id']) {
            throw ValidationException::withMessages([
                'docente_id' => 'El docente seleccionado no corresponde al grupo seleccionado.'
            ]);
        }

        $duplicada = Asignacion::where('estudiante_id', $datos['estudiante_id'])
            ->where('materia_id', $datos['materia_id'])
            ->where('grupo_id', $datos['grupo_id'])
            ->where('id', '!=', $asignacion->id)
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'estudiante_id' => 'El estudiante ya tiene esta asignación.'
            ]);
        }

        $asignacion->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Asignación actualizada correctamente.',
            'data' => $asignacion->load([
                'estudiante',
                'materia',
                'grupo',
                'docente'
            ])
        ]);
    }

    public function destroy(Asignacion $asignacion)
    {
        $asignacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asignación eliminada correctamente.'
        ]);
    }
}