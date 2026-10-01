<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsignacionApiController extends Controller
{
    public function materias(): JsonResponse
    {
        $materias = Materia::orderBy('nombre')->get(['id', 'nombre']);

        return response()->json(['data' => $materias]);
    }

    public function grupos(Materia $materia): JsonResponse
    {
        $grupos = Grupo::where('materia_id', $materia->id)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return response()->json(['data' => $grupos]);
    }

    public function docentes(Grupo $grupo): JsonResponse
    {
        $docentes = $grupo->docente_id === null
            ? collect()
            : collect([$grupo->docente()->first(['id', 'nombre'])])->filter();

        return response()->json(['data' => $docentes->values()]);
    }

    public function store(Request $request, Estudiante $estudiante): JsonResponse
    {
        $data = $request->validate([
            'materia_id' => ['required', 'integer', 'exists:materias,id'],
            'grupo_id' => ['required', 'integer', 'exists:grupos,id'],
            'docente_id' => ['required', 'integer', 'exists:docentes,id'],
        ]);

        $grupo = Grupo::findOrFail($data['grupo_id']);

        if ((int) $grupo->materia_id !== (int) $data['materia_id']) {
            return response()->json([
                'message' => 'El grupo seleccionado no corresponde a la materia.',
                'errors' => ['grupo_id' => ['Selecciona un grupo de la materia elegida.']],
            ], 422);
        }

        if ((int) $grupo->docente_id !== (int) $data['docente_id']) {
            return response()->json([
                'message' => 'El docente seleccionado no corresponde al grupo.',
                'errors' => ['docente_id' => ['Selecciona un docente asignado al grupo elegido.']],
            ], 422);
        }

        $estudianteId = $estudiante->getKey();
        $yaExiste = Asignacion::where('estudiante_id', $estudianteId)
            ->where('materia_id', $data['materia_id'])
            ->exists();

        if ($yaExiste) {
            return response()->json([
                'message' => 'Este estudiante ya tiene una asignación registrada para esa materia.',
                'errors' => ['materia_id' => ['Esta materia ya está asignada al estudiante.']],
            ], 422);
        }

        $asignacion = Asignacion::create([
            'estudiante_id' => $estudianteId,
            'materia_id' => $data['materia_id'],
            'grupo_id' => $data['grupo_id'],
            'docente_id' => $data['docente_id'],
        ]);

        return response()->json(['data' => $asignacion], 201);
    }
}
