<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Materia;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MateriaController extends Controller
{
    /** Letras, espacios, acentos y ñ (sin números ni símbolos). */
    private const REGEX_ALFABETICO = '/^[\pL\s]+$/u';

    public function index()
    {
        $materias = Materia::withCount('grupos')
            ->with(['grupos' => fn ($q) => $q->orderBy('nombre'), 'grupos.docente'])
            ->orderBy('nombre')->get();
        $docentes = Docente::orderBy('nombre')->orderBy('apellido')->get();
        $carreras = collect(Catalogo::FACULTADES)->flatten()
            ->merge(Estudiante::whereNotNull('carrera')->distinct()->pluck('carrera'))
            ->merge(Materia::whereNotNull('carrera')->distinct()->pluck('carrera'))
            ->filter()->unique()->sort()->values();

        $facultades = Catalogo::FACULTADES;

        return view('materias.index', compact('materias', 'carreras', 'facultades', 'docentes'));
    }

    public function store(Request $request)
    {
        Materia::create($this->validar($request));

        return back()->with('success', 'Materia registrada correctamente.');
    }

    public function update(Request $request, Materia $materia)
    {
        $materia->update($this->validar($request, $materia));

        return back()->with('success', 'Materia actualizada correctamente.');
    }

    public function destroy(Materia $materia)
    {
        $materia->delete();
        return back()->with('success', 'Materia eliminada.');
    }

    private function validar(Request $request, ?Materia $actual = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150', 'regex:'.self::REGEX_ALFABETICO],
            'sigla' => ['required', 'string', 'max:20', Rule::unique('materias', 'sigla')->ignore($actual?->id)],
            'facultad' => ['required', Rule::in(array_keys(Catalogo::FACULTADES))],
            'carrera' => ['required', 'string', 'max:120'],
        ], [
            'facultad.required' => 'Selecciona la facultad.',
            'nombre.regex' => 'El nombre de la materia solo puede contener letras.',
            'sigla.required' => 'La sigla es obligatoria.',
            'carrera.required' => 'Selecciona la carrera a la que pertenece la asignatura.',
        ]);

        if (! in_array($data['carrera'], Catalogo::carrerasDe($data['facultad']), true)) {
            throw ValidationException::withMessages([
                'carrera' => 'La carrera seleccionada no pertenece a la facultad elegida.',
            ]);
        }

        return $data;
    }
}
