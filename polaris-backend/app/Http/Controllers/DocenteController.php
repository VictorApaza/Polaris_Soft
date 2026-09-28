<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use Illuminate\Http\Request;

class DocenteController extends Controller
{
    public function index()
    {
        $docentes = Docente::orderBy('nombre')->get();
        return view('docentes.index', compact('docentes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'ci' => ['required', 'string', 'max:20', 'unique:docentes,ci'],
        ]);

        Docente::create($data);

        return back()->with('success', 'Docente registrado correctamente.');
    }

    public function update(Request $request, Docente $docente)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'ci' => ['required', 'string', 'max:20', 'unique:docentes,ci,' . $docente->id],
        ]);

        $docente->update($data);

        return back()->with('success', 'Docente actualizado correctamente.');
    }

    public function destroy(Docente $docente)
    {
        $docente->delete();
        return back()->with('success', 'Docente eliminado.');
    }
}
