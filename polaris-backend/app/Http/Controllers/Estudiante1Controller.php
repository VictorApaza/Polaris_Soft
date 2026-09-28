<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class Estudiante1Controller extends Controller
{
    /**
     * Listar estudiantes
     */
    public function index(Request $request)
    {
        $estudiantes = Estudiante::buscar($request->buscar)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Estudiantes obtenidos correctamente.',
            'data' => $estudiantes
        ]);
    }

    /**
     * Registrar estudiante
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'codigo_universitario' => [
                'required',
                'string',
                'max:50',
                'unique:estudiante,codigo_universitario',
            ],

            'documento_identidad' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]+$/',
                'unique:estudiante,documento_identidad',
            ],

            'nombres' => [
                'required',
                'string',
                'max:100',
            ],

            'apellidos' => [
                'required',
                'string',
                'max:100',
            ],

            'carrera' => [
                'required',
                'string',
                'max:150',
            ],

            'correo_institucional' => [
                'required',
                'email',
                'max:150',
                'unique:estudiante,correo_institucional',
            ],

            'estado' => [
                'required',
                Rule::in(['activo', 'inactivo']),
            ],
        ]);

        $estudiante = Estudiante::create($datos);

        return response()->json([
            'success' => true,
            'message' => 'Estudiante registrado correctamente.',
            'data' => $estudiante
        ], 201);
    }

    /**
     * Mostrar un estudiante
     */
    public function show(string $id)
    {
        $estudiante = Estudiante::find($id);

        if (!$estudiante) {
            return response()->json([
                'success' => false,
                'message' => 'Estudiante no encontrado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Estudiante obtenido correctamente.',
            'data' => $estudiante
        ]);
    }

    /**
     * Actualizar estudiante
     */
    public function update(Request $request, string $id)
    {
        $estudiante = Estudiante::find($id);

        if (!$estudiante) {
            return response()->json([
                'success' => false,
                'message' => 'Estudiante no encontrado.'
            ], 404);
        }

        $datos = $request->validate([
            'codigo_universitario' => [
                'required',
                'string',
                'max:50',
                Rule::unique('estudiante', 'codigo_universitario')
                    ->ignore($estudiante->id_estudiante, 'id_estudiante'),
            ],

            'documento_identidad' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]+$/',
                Rule::unique('estudiante', 'documento_identidad')
                    ->ignore($estudiante->id_estudiante, 'id_estudiante'),
            ],

            'nombres' => [
                'required',
                'string',
                'max:100',
            ],

            'apellidos' => [
                'required',
                'string',
                'max:100',
            ],

            'carrera' => [
                'required',
                'string',
                'max:150',
            ],

            'correo_institucional' => [
                'required',
                'email',
                'max:150',
                Rule::unique('estudiante', 'correo_institucional')
                    ->ignore($estudiante->id_estudiante, 'id_estudiante'),
            ],

            'estado' => [
                'required',
                Rule::in(['activo', 'inactivo']),
            ],
        ]);

        $estudiante->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Estudiante actualizado correctamente.',
            'data' => $estudiante
        ]);
    }

    /**
     * Eliminar estudiante
     */
    public function destroy(string $id)
    {
        $estudiante = Estudiante::find($id);

        if (!$estudiante) {
            return response()->json([
                'success' => false,
                'message' => 'Estudiante no encontrado.'
            ], 404);
        }

        $estudiante->delete();

        return response()->json([
            'success' => true,
            'message' => 'Estudiante eliminado correctamente.'
        ]);
    }
}