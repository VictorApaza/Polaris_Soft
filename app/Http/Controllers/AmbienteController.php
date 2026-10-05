<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use App\Models\Examen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AmbienteController extends Controller
{
    public function index()
    {
        $ambientes = Ambiente::orderBy('nombre')->get();

        return view('ambientes.index', compact('ambientes'));
    }

    public function store(Request $request)
    {
        Ambiente::create($this->validar($request));

        return redirect()->route('ambientes.index')->with('status', 'Ambiente registrado correctamente.');
    }

    public function update(Request $request, Ambiente $ambiente)
    {
        $data = $this->validar($request, $ambiente);
        $nombreAnterior = $ambiente->nombre;

        DB::transaction(function () use ($ambiente, $data, $nombreAnterior) {
            $ambiente->update($data);

            if ($nombreAnterior !== $data['nombre']) {
                Examen::where('ambiente', $nombreAnterior)->update(['ambiente' => $data['nombre']]);
            }
        });

        return redirect()->route('ambientes.index')->with('status', 'Ambiente actualizado correctamente.');
    }

    public function destroy(Ambiente $ambiente)
    {
        if (Examen::where('ambiente', $ambiente->nombre)->exists()) {
            return back()->with('error', 'No se puede eliminar un ambiente que ya está asignado a exámenes.');
        }

        $ambiente->delete();

        return back()->with('status', 'Ambiente eliminado.');
    }

    private function validar(Request $request, ?Ambiente $actual = null): array
    {
        return $request->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('ambientes', 'nombre')->ignore($actual?->id),
            ],
            'capacidad' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);
    }
}
