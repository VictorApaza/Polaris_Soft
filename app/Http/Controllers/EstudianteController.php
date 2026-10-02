<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstudianteController extends Controller
{
    /** Carreras sugeridas en el formulario (se suman las que ya existan en la base). */
    private const CARRERAS = [
        'Ingeniería de Sistemas', 'Ingeniería Civil', 'Ingeniería Industrial',
        'Ingeniería Electrónica', 'Ingeniería Química',
    ];

    public function index(Request $request)
    {
        $estudiantes = Estudiante::query()
            ->buscar($request->string('buscar')->toString())
            ->when($request->filled('carrera'), fn ($q) => $q->where('carrera', $request->carrera))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', strtoupper($request->estado)))
            ->orderBy('apellidos')
            ->paginate(5)
            ->withQueryString();

        $stats = [
            'total' => Estudiante::count(),
            'habilitados' => Estudiante::where('estado', 'ACTIVO')->count(),
            'inactivos' => Estudiante::where('estado', '<>', 'ACTIVO')->count(),
        ];

        $carreras = collect(self::CARRERAS)
            ->merge(Estudiante::whereNotNull('carrera')->distinct()->pluck('carrera'))
            ->unique()->sort()->values();

        return view('estudiantes.index', compact('estudiantes', 'stats', 'carreras'));
    }

    public function store(Request $request)
    {
        Estudiante::create($this->validar($request));

        return redirect()->route('estudiantes.index')->with('status', 'Estudiante registrado correctamente.');
    }

    public function update(Request $request, Estudiante $estudiante)
    {
        $estudiante->update($this->validar($request, $estudiante));

        return redirect()->route('estudiantes.index')->with('status', 'Estudiante actualizado correctamente.');
    }

    public function destroy(Estudiante $estudiante)
    {
        $estudiante->delete();

        return redirect()->route('estudiantes.index')->with('status', 'Estudiante eliminado.');
    }

    private function validar(Request $request, ?Estudiante $actual = null): array
    {
        $id = $actual?->getKey();

        $data = $request->validate([
            'codigo_universitario' => ['required', 'string', 'max:10', 'regex:/^[0-9]{1,10}$/',
                Rule::unique('estudiante', 'codigo_universitario')->ignore($id, 'id_estudiante')],
            'documento_identidad' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/',
                Rule::unique('estudiante', 'documento_identidad')->ignore($id, 'id_estudiante')],
            'nombres' => ['required', 'string', 'max:100', 'regex:/^[\p{L} ]+$/u'],
            'apellidos' => ['required', 'string', 'max:100', 'regex:/^[\p{L} ]+$/u'],
            'carrera' => ['required', 'string', 'max:120'],
            'correo_institucional' => ['nullable', 'email', 'max:150', 'regex:/^[^@\s]+@est\.umss\.edu$/i'],
            'estado' => ['required', Rule::in(['ACTIVO', 'OBSERVADO', 'INACTIVO'])],
        ], [
            'codigo_universitario.regex' => 'El código universitario debe contener solo números y tener como máximo 10 dígitos.',
            'codigo_universitario.unique' => 'Ya existe un estudiante con ese código.',
            'documento_identidad.regex' => 'El CI / DNI debe contener solo números.',
            'documento_identidad.unique' => 'Ya existe un estudiante con ese CI / DNI.',
            'nombres.regex' => 'Los nombres solo pueden contener letras y espacios.',
            'apellidos.regex' => 'Los apellidos solo pueden contener letras y espacios.',
            'correo_institucional.regex' => 'El correo del estudiante debe terminar en @est.umss.edu.',
        ]);

        $data['estado'] = strtoupper($data['estado']);

        return $data;
    }
}
