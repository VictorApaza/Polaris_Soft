<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Docente;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    public function index(Request $request)
    {
        $query = Grupo::with(['materia', 'docente'])
            ->orderBy('nombre');

        if ($request->filled('materia_id')) {
            $query->where('materia_id', $request->materia_id);
        }

        $grupos = $query->get();

        return response()->json([
            'success' => true,
            'data' => $grupos
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'materia_id' => ['required', 'integer', 'exists:materias,id'],
            'docente_id' => ['required', 'integer', 'exists:docentes,id'],
        ]);

        $grupo = Grupo::create($datos);

        return response()->json([
            'success' => true,
            'message' => 'Grupo registrado correctamente.',
            'data' => $grupo->load(['materia', 'docente'])
        ], 201);
    }

    public function show(Grupo $grupo)
    {
        return response()->json([
            'success' => true,
            'data' => $grupo->load(['materia', 'docente'])
        ]);
    }

    public function update(Request $request, Grupo $grupo)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'materia_id' => ['required', 'integer', 'exists:materias,id'],
            'docente_id' => ['required', 'integer', 'exists:docentes,id'],
        ]);

        $grupo->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Grupo actualizado correctamente.',
            'data' => $grupo->load(['materia', 'docente'])
        ]);
    }

    public function destroy(Grupo $grupo)
    {
        $grupo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Grupo eliminado correctamente.'
        ]);
    }
}