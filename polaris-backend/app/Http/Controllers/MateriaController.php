<?php

namespace App\Http\Controllers;

use App\Models\Materia;
use Illuminate\Http\Request;

class MateriaController extends Controller
{
    public function index()
{
    $materias = Materia::orderBy('nombre')->get();

    return response()->json([
        'success' => true,
        'data' => $materias
    ]);
}
   /* public function index()
    {
        $materias = Materia::orderBy('nombre')->get();
        return view('materias.index', compact('materias'));
    }*/

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'sigla' => ['nullable', 'string', 'max:20'],
        ]);

        Materia::create($data);

        return back()->with('success', 'Materia registrada correctamente.');
    }

    public function update(Request $request, Materia $materia)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'sigla' => ['nullable', 'string', 'max:20'],
        ]);

        $materia->update($data);

        return back()->with('success', 'Materia actualizada correctamente.');
    }

    public function destroy(Materia $materia)
    {
        $materia->delete();
        return back()->with('success', 'Materia eliminada.');
    }
}
