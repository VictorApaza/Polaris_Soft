<?php

namespace App\Http\Controllers;

use App\Models\Materia;
use Illuminate\Http\Request;

class MateriaController extends Controller
{
    public function index()
    {
        $materias = Materia::with('grupos')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $materias
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'sigla' => ['required', 'string', 'max:50', 'unique:materias,sigla'],
        ]);

        $materia = Materia::create($datos);

        return response()->json([
            'success' => true,
            'message' => 'Materia registrada correctamente.',
            'data' => $materia
        ], 201);
    }

    public function show(Materia $materia)
    {
        return response()->json([
            'success' => true,
            'data' => $materia->load('grupos.docente')
        ]);
    }

    public function update(Request $request, Materia $materia)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'sigla' => [
                'required',
                'string',
                'max:50',
                'unique:materias,sigla,' . $materia->id
            ],
        ]);

        $materia->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Materia actualizada correctamente.',
            'data' => $materia
        ]);
    }

    public function destroy(Materia $materia)
    {
        $materia->delete();

        return response()->json([
            'success' => true,
            'message' => 'Materia eliminada correctamente.'
        ]);
    }
}