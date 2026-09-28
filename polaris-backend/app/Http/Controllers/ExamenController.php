<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Materia;
use Illuminate\Http\Request;

class ExamenController extends Controller
{
    public function index()
    {
        $examenes = Examen::with('materia')
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Exámenes obtenidos correctamente.',
            'data' => $examenes
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'materia_id' => [
                'required',
                'integer',
                'exists:materias,id'
            ],

            'fecha' => [
                'required',
                'date',
                'after_or_equal:today'
            ],

            'hora' => [
                'required',
                'date_format:H:i'
            ],

            'duracion' => [
                'required',
                'integer',
                'min:1'
            ],

            'ambiente' => [
                'required',
                'string',
                'max:100'
            ],

            'normas_generales' => [
                'nullable',
                'string'
            ],

            'normas_particulares' => [
                'nullable',
                'string'
            ],
        ]);

        $examen = Examen::create($datos);

        return response()->json([
            'success' => true,
            'message' => 'Examen registrado correctamente.',
            'data' => $examen->load('materia')
        ], 201);
    }

    public function show(string $id)
    {
        $examen = Examen::with('materia')->find($id);

        if (!$examen) {
            return response()->json([
                'success' => false,
                'message' => 'Examen no encontrado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Examen obtenido correctamente.',
            'data' => $examen
        ]);
    }

    public function update(Request $request, string $id)
    {
        $examen = Examen::find($id);

        if (!$examen) {
            return response()->json([
                'success' => false,
                'message' => 'Examen no encontrado.'
            ], 404);
        }

        $datos = $request->validate([
            'materia_id' => [
                'required',
                'integer',
                'exists:materias,id'
            ],

            'fecha' => [
                'required',
                'date',
                'after_or_equal:today'
            ],

            'hora' => [
                'required',
                'date_format:H:i'
            ],

            'duracion' => [
                'required',
                'integer',
                'min:1'
            ],

            'ambiente' => [
                'required',
                'string',
                'max:100'
            ],

            'normas_generales' => [
                'nullable',
                'string'
            ],

            'normas_particulares' => [
                'nullable',
                'string'
            ],
        ]);

        $examen->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Examen actualizado correctamente.',
            'data' => $examen->load('materia')
        ]);
    }

    public function destroy(string $id)
    {
        $examen = Examen::find($id);

        if (!$examen) {
            return response()->json([
                'success' => false,
                'message' => 'Examen no encontrado.'
            ], 404);
        }

        $examen->delete();

        return response()->json([
            'success' => true,
            'message' => 'Examen eliminado correctamente.'
        ]);
    }
}