<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use Illuminate\Http\Request;

class DocenteController extends Controller
{
    public function index()
    {
        $docentes = Docente::orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'data' => $docentes
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'ci' => ['required', 'string', 'max:50', 'unique:docentes,ci'],
        ]);

        $docente = Docente::create($datos);

        return response()->json([
            'success' => true,
            'message' => 'Docente registrado correctamente.',
            'data' => $docente
        ], 201);
    }

    public function show(Docente $docente)
    {
        return response()->json([
            'success' => true,
            'data' => $docente->load('grupos')
        ]);
    }

    public function update(Request $request, Docente $docente)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'ci' => [
                'required',
                'string',
                'max:50',
                'unique:docentes,ci,' . $docente->id
            ],
        ]);

        $docente->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Docente actualizado correctamente.',
            'data' => $docente
        ]);
    }

    public function destroy(Docente $docente)
    {
        $docente->delete();

        return response()->json([
            'success' => true,
            'message' => 'Docente eliminado correctamente.'
        ]);
    }
}