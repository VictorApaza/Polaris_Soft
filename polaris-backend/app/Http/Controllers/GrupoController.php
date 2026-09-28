<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    public function index()
    {
        $grupos = Grupo::with(['materia', 'docente'])->orderBy('nombre')->get();
        $materias = Materia::orderBy('nombre')->get();
        $docentes = Docente::orderBy('nombre')->get();

        return view('grupos.index', compact('grupos', 'materias', 'docentes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50'],
            'materia_id' => ['required', 'exists:materias,id'],
            'docente_id' => ['nullable', 'exists:docentes,id'],
        ]);

        Grupo::create($data);

        return back()->with('success', 'Grupo registrado correctamente.');
    }

    public function update(Request $request, Grupo $grupo)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50'],
            'materia_id' => ['required', 'exists:materias,id'],
            'docente_id' => ['nullable', 'exists:docentes,id'],
        ]);

        $grupo->update($data);

        return back()->with('success', 'Grupo actualizado correctamente.');
    }

    public function destroy(Grupo $grupo)
    {
        $grupo->delete();
        return back()->with('success', 'Grupo eliminado.');
    }
}
