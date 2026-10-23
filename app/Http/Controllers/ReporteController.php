<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\MotivoInhabilitacion;
use App\Models\TransparenciaIngreso;
use Illuminate\Support\Facades\DB;

/** Reportes para el docente y el administrador: quiénes entraron, quiénes no, inhabilitados y por qué, incidencias. */
class ReporteController extends Controller
{
    public function index()
    {
        $examenes = Examen::with(['asignatura', 'materia'])
            ->orderByDesc('fecha')->orderByDesc('hora_inicio')
            ->paginate(10);

        $ids = $examenes->pluck('id');

        $porEstado = Habilitacion::selectRaw('examen_id, estado, COUNT(*) as total')
            ->whereIn('examen_id', $ids)->groupBy('examen_id', 'estado')->get()->groupBy('examen_id');

        $ingresaron = TransparenciaIngreso::selectRaw('examen_id, COUNT(DISTINCT estudiante_id) as total')
            ->whereIn('examen_id', $ids)->groupBy('examen_id')->pluck('total', 'examen_id');

        $noIngresaron = Habilitacion::selectRaw('examen_id, COUNT(*) as total')
            ->where('estado', 'habilitado')->whereIn('examen_id', $ids)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('transparencia_ingreso as t')
                    ->whereColumn('t.estudiante_id', 'habilitacion.estudiante_id')
                    ->whereColumn('t.examen_id', 'habilitacion.examen_id');
            })
            ->groupBy('examen_id')->pluck('total', 'examen_id');

        $incidencias = Incidencia::selectRaw('examen_id, COUNT(*) as total')
            ->where('estado', 'ABIERTA')->whereIn('examen_id', $ids)->groupBy('examen_id')->pluck('total', 'examen_id');

        $filas = $examenes->getCollection()->map(function ($x) use ($porEstado, $ingresaron, $noIngresaron, $incidencias) {
            $est = ($porEstado->get($x->id) ?? collect())->pluck('total', 'estado');

            return [
                'examen' => $x,
                'habilitados' => (int) $est->get('habilitado', 0),
                'inhabilitados' => (int) $est->get('inhabilitado', 0),
                'ingresaron' => (int) $ingresaron->get($x->id, 0),
                'no_ingresaron' => (int) $noIngresaron->get($x->id, 0),
                'incidencias' => (int) $incidencias->get($x->id, 0),
            ];
        });

        return view('reportes.index', ['examenes' => $examenes, 'filas' => $filas]);
    }

    public function show(Examen $examen)
    {
        $examen->loadMissing(['asignatura', 'materia']);

        $ingresos = TransparenciaIngreso::where('examen_id', $examen->id)
            ->orderBy('fecha_hora_ingreso')->get();
        $idsIngresaron = $ingresos->pluck('estudiante_id')->unique();

        $habilitaciones = Habilitacion::with(['estudiante', 'motivo'])
            ->where('examen_id', $examen->id)->get();

        $habilitados = $habilitaciones->where('estado', 'habilitado');
        $noIngresaron = $habilitados->reject(fn ($h) => $idsIngresaron->contains($h->estudiante_id))->values();
        $inhabilitados = $habilitaciones->where('estado', 'inhabilitado')->values();

        $porMotivo = $inhabilitados
            ->groupBy(fn ($h) => $h->motivo->descripcion ?? 'Sin motivo registrado')
            ->map->count()->sortDesc();

        $incidencias = Incidencia::with(['estudiante', 'controlador'])
            ->where('examen_id', $examen->id)
            ->orderByDesc('fecha_hora')->get();

        $candidatos = Habilitacion::candidatosIds($examen);
        $fotos = Estudiante::whereIn('id_estudiante', $candidatos)
            ->selectRaw('foto_estado, COUNT(*) as total')->groupBy('foto_estado')->pluck('total', 'foto_estado');

        return view('reportes.show', [
            'examen' => $examen,
            'totalCandidatos' => count($candidatos),
            'ingresos' => $ingresos,
            'habilitados' => $habilitados->count(),
            'noIngresaron' => $noIngresaron,
            'inhabilitados' => $inhabilitados,
            'porMotivo' => $porMotivo,
            'incidencias' => $incidencias,
            'fotos' => $fotos,
            'motivoTrampaId' => MotivoInhabilitacion::firstOrCreate(['descripcion' => 'Trampa o suplantación detectada'])->id,
        ]);
    }
}
