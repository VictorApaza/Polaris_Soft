<?php
// SOLUCIÓN 2 (versión Laravel puro): se completó el controlador con los
// métodos que le faltaban para funcionar como un CRUD web normal
// (create, store, show, edit, update). El index y el destroy ya existían.

namespace App\Http\Controllers;

use App\Models\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstudianteController extends Controller
{
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
            'total'       => Estudiante::count(),
            'habilitados' => Estudiante::where('estado', 'ACTIVO')->count(),
            'inactivos'   => Estudiante::where('estado', '<>', 'ACTIVO')->count(),
        ];

        $carreras = Estudiante::whereNotNull('carrera')->distinct()->orderBy('carrera')->pluck('carrera');

        return view('estudiantes.index', compact('estudiantes', 'stats', 'carreras'));
    }

    // GET /estudiantes/create — muestra el formulario para registrar uno nuevo
    public function create()
    {
        return view('estudiantes.create');
    }

    // POST /estudiantes — guarda el estudiante nuevo
    public function store(Request $request)
    {
        $data = $this->validated($request);

        Estudiante::create($data);

        return redirect()->route('estudiantes.index')->with('status', 'Estudiante registrado correctamente.');
    }

    // GET /estudiantes/{estudiante} — ver el detalle de un estudiante
    public function show(Estudiante $estudiante)
    {
        return view('estudiantes.show', compact('estudiante'));
    }

    // GET /estudiantes/{estudiante}/edit — muestra el formulario ya lleno
    public function edit(Estudiante $estudiante)
    {
        return view('estudiantes.edit', compact('estudiante'));
    }

    // PUT/PATCH /estudiantes/{estudiante} — guarda los cambios
    public function update(Request $request, Estudiante $estudiante)
    {
        $data = $this->validated($request, $estudiante->id_estudiante);

        $estudiante->update($data);

        return redirect()->route('estudiantes.index')->with('status', 'Estudiante actualizado correctamente.');
    }

    // DELETE /estudiantes/{estudiante}
    public function destroy(Estudiante $estudiante)
    {
        $estudiante->delete();

        return redirect()->route('estudiantes.index')->with('status', 'Estudiante eliminado.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'codigo_universitario' => ['required', 'string', 'max:20', Rule::unique('estudiante', 'codigo_universitario')->ignore($ignoreId, 'id_estudiante')],
            'documento_identidad'  => ['required', 'string', 'max:20', Rule::unique('estudiante', 'documento_identidad')->ignore($ignoreId, 'id_estudiante')],
            'nombres'               => ['required', 'string', 'max:100'],
            'apellidos'             => ['required', 'string', 'max:100'],
            'carrera'               => ['nullable', 'string', 'max:100'],
            'correo_institucional'  => ['required', 'email', Rule::unique('estudiante', 'correo_institucional')->ignore($ignoreId, 'id_estudiante')],
            'estado'                => ['required', 'string', 'in:ACTIVO,OBSERVADO,INACTIVO'],
        ]);
    }
}
