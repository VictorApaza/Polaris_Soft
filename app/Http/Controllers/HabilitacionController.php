<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\MotivoInhabilitacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HabilitacionController extends Controller
{
    /** Pantalla: estudiantes candidatos del examen (filtrables por grupo si el examen es por materia). */
    public function index(Request $request, Examen $examen)
    {
        // Los exámenes creados con el formulario nuevo se ligan a una asignatura (sin grupos);
        // los anteriores se ligan a una materia con grupos.
        $usaGrupos = ! $examen->asignatura_id;
        $grupos = $usaGrupos ? Grupo::where('materia_id', $examen->materia_id)->orderBy('nombre')->get() : collect();
        $grupoId = $usaGrupos ? ((int) $request->query('grupo_id') ?: null) : null;

        $ids = Habilitacion::candidatosIds($examen, $grupoId);
        $estudiantes = Estudiante::whereIn('id_estudiante', $ids)->orderBy('apellidos')->get();

        $gruposPorEstudiante = [];
        if ($usaGrupos && $ids) {
            $gruposPorEstudiante = Asignacion::with('grupo')
                ->where('materia_id', $examen->materia_id)
                ->whereIn('estudiante_id', $ids)
                ->get()
                ->mapWithKeys(fn ($a) => [$a->estudiante_id => $a->grupo?->nombre])
                ->all();
        }

        $habilitaciones = Habilitacion::where('examen_id', $examen->id)->get()->keyBy('estudiante_id');

        $resumen = ['habilitado' => 0, 'inhabilitado' => 0, 'sin_evaluar' => 0];
        foreach ($estudiantes as $est) {
            $h = $habilitaciones->get($est->id_estudiante);
            $resumen[$h ? $h->estado : 'sin_evaluar']++;
        }

        $motivos = MotivoInhabilitacion::orderBy('descripcion')->get();

        return view('examenes.habilitados', compact(
            'examen', 'usaGrupos', 'grupos', 'grupoId', 'estudiantes', 'gruposPorEstudiante',
            'habilitaciones', 'resumen', 'motivos'
        ));
    }

    /**
     * Habilita a varios estudiantes de un solo golpe:
     *  - los marcados con checkbox (estudiante_ids[]), o
     *  - "habilitar a todos" (todos=1) los de la materia, o solo los del grupo filtrado (grupo_id).
     */
    public function habilitarMasiva(Request $request, Examen $examen)
    {
        $request->validate([
            'estudiante_ids' => ['required_without:todos', 'array'],
            'estudiante_ids.*' => ['integer', Rule::exists('estudiante', 'id_estudiante')],
            'grupo_id' => ['nullable', 'integer'],
        ], [
            'estudiante_ids.required_without' => 'Selecciona al menos un estudiante.',
        ]);

        if ($request->boolean('todos')) {
            $grupoId = $request->filled('grupo_id') ? (int) $request->grupo_id : null;
            $ids = Habilitacion::idsDelGrupo($examen, $grupoId);
            $resultado = Habilitacion::habilitar($examen, $ids, respetarInhabilitados: true);
        } else {
            $resultado = Habilitacion::habilitar($examen, array_map('intval', $request->input('estudiante_ids', [])));
        }

        $n = count($resultado['habilitados']);
        $mensaje = "{$n} estudiante(s) habilitado(s) para el examen.";
        if ($resultado['respetados']) {
            $mensaje .= ' Se respetaron '.count($resultado['respetados']).' inhabilitado(s); para habilitarlos usa el botón "Habilitar" de cada uno.';
        }
        if ($resultado['ignorados']) {
            $mensaje .= ' '.count($resultado['ignorados']).' no estaban asignados a la materia y se ignoraron.';
        }

        return back()->with('status', $mensaje);
    }

    /** Registra el motivo y deja a un estudiante inhabilitado para este examen puntual. */
    public function inhabilitar(Request $request, Examen $examen, Estudiante $estudiante)
    {
        $data = $request->validate([
            'motivo_inhabilitacion_id' => ['required', Rule::exists('motivo_inhabilitacion', 'id')],
            'observacion' => ['nullable', 'string', 'max:500'],
        ], [
            'motivo_inhabilitacion_id.required' => 'Selecciona la causa de inhabilitación.',
        ]);

        if (! Habilitacion::idsAsignados($examen, [$estudiante->getKey()])) {
            throw ValidationException::withMessages([
                'motivo_inhabilitacion_id' => 'Ese estudiante no está asignado a la materia de este examen.',
            ]);
        }

        $motivo = MotivoInhabilitacion::find($data['motivo_inhabilitacion_id']);
        if ($motivo->descripcion === 'Otro' && blank($data['observacion'] ?? null)) {
            throw ValidationException::withMessages([
                'observacion' => 'Cuando la causa es "Otro" debes explicar el motivo en la observación.',
            ]);
        }

        Habilitacion::updateOrCreate(
            ['estudiante_id' => $estudiante->getKey(), 'examen_id' => $examen->id],
            [
                'estado' => 'inhabilitado',
                'motivo_inhabilitacion_id' => $data['motivo_inhabilitacion_id'],
                'observacion' => $data['observacion'] ?? null,
            ]
        );

        return back()->with('status', "{$estudiante->nombre_completo} quedó inhabilitado para este examen.");
    }

    /** Vuelve a dejar al estudiante "sin evaluar" para este examen (borra la decisión). */
    public function quitar(Examen $examen, Estudiante $estudiante)
    {
        Habilitacion::where('estudiante_id', $estudiante->getKey())
            ->where('examen_id', $examen->id)
            ->delete();

        return back()->with('status', 'Se quitó la decisión anterior; el estudiante vuelve a estar sin evaluar.');
    }
}
