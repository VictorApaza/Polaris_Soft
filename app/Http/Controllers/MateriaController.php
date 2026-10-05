<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Materia;
use App\Support\Catalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MateriaController extends Controller
{
    /** Letras, espacios, acentos y ñ (sin números ni símbolos). */
    private const REGEX_ALFABETICO = '/^[\pL\s]+$/u';

    public function index()
    {
        $materias = Materia::withCount('grupos')->orderBy('nombre')->get();
        $carreras = collect(Catalogo::FACULTADES)->flatten()
            ->merge(Estudiante::whereNotNull('carrera')->distinct()->pluck('carrera'))
            ->merge(Materia::whereNotNull('carrera')->distinct()->pluck('carrera'))
            ->filter()->unique()->sort()->values();

        return view('materias.index', compact('materias', 'carreras'));
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
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150', 'regex:'.self::REGEX_ALFABETICO],
            'sigla' => ['required', 'string', 'max:20', Rule::unique('materias', 'sigla')->ignore($actual?->id)],
            'carrera' => ['required', 'string', 'max:120'],
        ], [
            'nombre.regex' => 'El nombre de la materia solo puede contener letras.',
            'sigla.required' => 'La sigla es obligatoria.',
            'carrera.required' => 'Selecciona la carrera a la que pertenece la asignatura.',
        ]);
    }
}
