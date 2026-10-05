<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Ambiente;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExamenController extends Controller
{
    /** Letras, espacios, acentos y ñ (sin números ni símbolos). */
    private const REGEX_ALFABETICO = '/^[\pL\s]+$/u';

    public function index(Request $request)
    {
        $examenes = Examen::with(['materia' => fn ($q) => $q->withCount('asignaciones')])
            ->buscar($request->string('buscar')->toString())
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('ambiente'), fn ($q) => $q->where('ambiente', $request->ambiente))
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->paginate(5)
            ->withQueryString();

        $stats = [
            'programados' => Examen::where('estado', '<>', 'finalizado')->count(),
            'ambientes' => Examen::where('estado', '<>', 'finalizado')->distinct('ambiente')->count('ambiente'),
        ];

        $asignaturas = $this->asignaturasDisponibles();
        $carreras = $asignaturas->pluck('carrera')->unique()->sort()->values();
        $ambientes = Ambiente::orderBy('nombre')->get();

        return view('examenes.index', compact('examenes', 'stats', 'asignaturas', 'carreras', 'ambientes'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);

        try {
            Examen::create($data);
        } catch (QueryException $e) {
            return $this->errorDuplicado();
        }

        return redirect()->route('examenes.index')->with('status', 'Examen registrado correctamente.');
    }

    public function update(Request $request, Examen $examen)
    {
        $data = $this->validar($request, $examen);

        try {
            $examen->update($data);
        } catch (QueryException $e) {
            return $this->errorDuplicado();
        }

        return redirect()->route('examenes.index')->with('status', 'Examen actualizado correctamente.');
    }

    public function destroy(Examen $examen)
    {
        $examen->delete();

        return redirect()->route('examenes.index')->with('status', 'Examen eliminado.');
    }

    private function validar(Request $request, ?Examen $actual = null): array
    {
        $data = $request->validate([
            'asignatura_id' => [
                'required',
                'exists:asignatura,id_asignatura',
                Rule::unique('examenes', 'asignatura_id')
                    ->where('carrera', $request->input('carrera'))
                    ->where('fecha', $request->input('fecha'))
                    ->where('hora_inicio', $request->input('hora_inicio'))
                    ->ignore($actual?->id),
            ],
            'carrera' => ['required', 'string', 'max:150', Rule::in($this->asignaturasDisponibles()->pluck('carrera')->unique()->all())],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracion_min' => ['required', 'integer', 'min:15', 'max:480'],
            'ambiente' => ['required', 'string', Rule::exists('ambientes', 'nombre')],
            'estado' => ['required', Rule::in(array_keys(Examen::ESTADOS))],
            'normas_admision' => ['nullable', 'string', 'max:1000'],
            'normas_salida' => ['nullable', 'string', 'max:1000'],
        ], [
            'asignatura_id.required' => 'Selecciona una asignatura.',
            'asignatura_id.exists' => 'La asignatura seleccionada no existe.',
            'asignatura_id.unique' => 'Ya existe un examen para esta asignatura, carrera, fecha y hora de inicio.',
            'carrera.required' => 'Selecciona una carrera.',
            'carrera.in' => 'Selecciona una carrera registrada.',
            'ambiente.exists' => 'Selecciona un ambiente registrado.',
            'fecha.after_or_equal' => 'La fecha del examen debe ser igual o posterior a hoy.',
            'hora_inicio.date_format' => 'La hora debe tener el formato HH:MM.',
        ]);

        $asignaturaPertenece = DB::table('estudiante_asignatura as ea')
            ->join('asignatura as a', 'a.id_asignatura', '=', 'ea.id_asignatura')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'ea.id_estudiante')
            ->where('a.id_asignatura', $data['asignatura_id'])
            ->where('e.carrera', $data['carrera'])
            ->exists();

        if (! $asignaturaPertenece) {
            throw ValidationException::withMessages([
                'asignatura_id' => 'La asignatura seleccionada no está asignada a estudiantes de la carrera indicada.',
            ]);
        }

        // Un ambiente no puede tener dos exámenes que se crucen en el mismo día y horario.
        $inicio = Carbon::createFromFormat('Y-m-d H:i', $data['fecha'].' '.$data['hora_inicio']);
        $fin = $inicio->copy()->addMinutes((int) $data['duracion_min']);

        $choque = Examen::with(['asignatura', 'materia'])
            ->where('ambiente', $data['ambiente'])
            ->whereDate('fecha', $data['fecha'])
            ->when($actual, fn ($q) => $q->where('id', '<>', $actual->id))
            ->get()
            ->first(function (Examen $otro) use ($inicio, $fin) {
                $ini = Carbon::parse($otro->fecha->format('Y-m-d').' '.$otro->hora);

                return $ini < $fin && $ini->copy()->addMinutes((int) $otro->duracion_min) > $inicio;
            });

        if ($choque) {
            $nombre = $choque->asignatura->nombre ?? $choque->materia->nombre ?? 'otra asignatura';
            throw ValidationException::withMessages([
                'ambiente' => "Ya existe un examen de {$nombre} programado en este ambiente en ese horario ({$choque->hora}).",
            ]);
        }

        $ambiente = Ambiente::where('nombre', $data['ambiente'])->firstOrFail();
        $data['capacidad'] = $ambiente->capacidad;

        return $data;
    }

    private function asignaturasDisponibles()
    {
        return DB::table('estudiante_asignatura as ea')
            ->join('asignatura as a', 'a.id_asignatura', '=', 'ea.id_asignatura')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'ea.id_estudiante')
            ->whereNotNull('e.carrera')
            ->where('e.carrera', '<>', '')
            ->select('a.id_asignatura as id', 'a.codigo', 'a.nombre', 'e.carrera')
            ->distinct()
            ->orderBy('e.carrera')
            ->orderBy('a.nombre')
            ->get();
    }

    /** Respaldo por si dos envíos llegan a la vez (doble clic): la restricción única de la BD lo rechaza. */
    private function errorDuplicado()
    {
        return back()->withInput()->withErrors([
            'asignatura_id' => 'Ya existe un examen para esta asignatura, carrera, fecha y hora de inicio.',
        ]);
    }
}
