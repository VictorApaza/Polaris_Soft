<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use Illuminate\Http\Request;

class EstudianteController extends Controller
{
    public function index(Request $request)
    {
        $estudiantes = Estudiante::query()
            ->buscar($request->string('buscar')->toString())
            ->when($request->filled('carrera'), fn ($q) => $q->where('carrera', $request->carrera))
            ->when($request->filled('estado'),  fn ($q) => $q->where('estado', strtoupper($request->estado)))
            ->orderBy('apellidos')
            ->paginate(5)
            ->withQueryString();

        $stats = [
            'total'       => Estudiante::count(),
            'habilitados' => Estudiante::where('estado', 'ACTIVO')->count(),
            'inactivos'   => Estudiante::where('estado', '<>', 'ACTIVO')->count(),
        ];

        $carreras = Estudiante::whereNotNull('carrera')->distinct()->orderBy('carrera')->pluck('carrera');

        return view('estudiantes.index', compact('estudiantes', 'stats', 'carreras'));
    }

    public function destroy(Estudiante $estudiante)
    {
        $estudiante->delete();

        return redirect()->route('estudiantes.index')->with('status', 'Estudiante eliminado.');
    }
}