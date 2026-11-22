<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\TransparenciaIngreso;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5 · Reportes por examen (RQ16, RQ17, RQ18 y RQ19).
 *
 * Todos salen de las mismas tablas que ya alimentan el Control en vivo y el reporte general:
 *   - `ingreso`            → quién entró y a qué hora (RQ10/RQ11)
 *   - `habilitacion`       → quién estaba habilitado (RQ3/RQ4)
 *   - `intento_ingreso`    → intentos rechazados, entre ellos "examen que no le corresponde" (RQ9/RQ12)
 *   - `expulsion`          → expulsados (RQ20), solo para marcar al estudiante en la asistencia
 * Esta clase solo CONSULTA: no escribe en ninguna tabla.
 */
class ReporteExamenService
{
    public const FRANJAS_VALIDAS = [5, 10, 15, 30, 60];

    // ------------------------------------------------------------------
    //  RQ16 · Asistencia de un examen
    // ------------------------------------------------------------------

    /**
     * Estudiantes que ingresaron al examen (con la hora, el método, el aula y quién lo registró)
     * y el cuadre contra los habilitados: habilitados = presentes + ausentes.
     */
    public function asistencia(Examen $examen): array
    {
        $consulta = DB::table('ingreso as i')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'i.estudiante_id')
            ->leftJoin('ambientes as a', 'a.id', '=', 'i.ambiente_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.controlador_id')
            ->where('i.examen_id', $examen->id);

        $columnasExpulsion = [DB::raw('NULL as expulsion_motivo'), DB::raw('NULL as expulsion_fecha')];
        if (Schema::hasTable('expulsion')) {
            $consulta->leftJoin('expulsion as x', function ($j) {
                $j->on('x.examen_id', '=', 'i.examen_id')->on('x.estudiante_id', '=', 'i.estudiante_id');
            });
            $columnasExpulsion = ['x.motivo as expulsion_motivo', 'x.fecha_hora as expulsion_fecha'];
        }

        $ingresos = $consulta
            ->orderBy('i.fecha_hora_ingreso')->orderBy('i.id')
            ->get(array_merge([
                'i.id', 'i.estudiante_id', 'i.fecha_hora_ingreso', 'i.metodo_verificacion',
                'e.codigo_universitario', 'e.documento_identidad', 'e.ci_complemento', 'e.nombres', 'e.apellidos', 'e.carrera',
                'a.nombre as ambiente', 'u.name as controlador',
            ], $columnasExpulsion));

        $habilitados = $this->idsHabilitados($examen);
        $presentes = $ingresos->pluck('estudiante_id')->map(fn ($id) => (int) $id)->unique();
        $presentesHabilitados = $presentes->intersect($habilitados)->count();
        $ausentes = $habilitados->diff($presentes)->count();
        $expulsados = $ingresos->whereNotNull('expulsion_motivo')->count();

        $filas = $ingresos->values()->map(fn ($f, $n) => [
            'n' => $n + 1,
            'fecha_hora' => $this->local($f->fecha_hora_ingreso),
            'codigo' => $f->codigo_universitario,
            'ci' => $this->ci($f),
            'nombre' => trim($f->apellidos.', '.$f->nombres, ' ,'),
            'carrera' => $f->carrera ?: '—',
            'metodo' => TransparenciaIngreso::METODOS[$f->metodo_verificacion] ?? $f->metodo_verificacion,
            'ambiente' => $f->ambiente ?: (string) $examen->ambiente,
            'controlador' => $f->controlador ?: '—',
            'estado' => $f->expulsion_motivo !== null ? 'Expulsado' : 'Presente',
            'detalle' => $f->expulsion_motivo !== null
                ? 'Expulsado el '.$this->local($f->expulsion_fecha).': '.$f->expulsion_motivo
                : '',
        ])->all();

        return [
            'resumen' => [
                'habilitados' => $habilitados->count(),
                'ingresaron' => $presentes->count(),
                'ausentes' => $ausentes,
                'expulsados' => $expulsados,
                'porcentaje' => $habilitados->count() > 0 ? round($presentesHabilitados / $habilitados->count() * 100, 1) : 0.0,
                'presentes_habilitados' => $presentesHabilitados,
                // Los habilitados que no ingresaron + los que sí ingresaron deben dar el total de habilitados.
                'cuadra' => $presentesHabilitados + $ausentes === $habilitados->count(),
            ],
            'filas' => $filas,
        ];
    }

    // ------------------------------------------------------------------
    //  RQ17 · Estudiantes ausentes
    // ------------------------------------------------------------------

    /** Habilitados del examen que NO tienen ningún ingreso registrado (cruce habilitación × ingreso). */
    public function ausentes(Examen $examen): array
    {
        $habilitados = $this->idsHabilitados($examen);

        $filas = DB::table('habilitacion as h')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'h.estudiante_id')
            ->where('h.examen_id', $examen->id)
            ->where('h.estado', 'habilitado')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('ingreso as i')
                    ->whereColumn('i.estudiante_id', 'h.estudiante_id')
                    ->whereColumn('i.examen_id', 'h.examen_id');
            })
            ->orderBy('e.apellidos')->orderBy('e.nombres')
            ->get(['e.id_estudiante', 'e.codigo_universitario', 'e.documento_identidad', 'e.ci_complemento', 'e.nombres', 'e.apellidos', 'e.carrera', 'e.correo_institucional', 'e.foto_estado']);

        $inhabilitados = Habilitacion::where('examen_id', $examen->id)->where('estado', 'inhabilitado')->count();
        $ingresaron = (int) DB::table('ingreso')->where('examen_id', $examen->id)->distinct()->count('estudiante_id');

        return [
            'resumen' => [
                'habilitados' => $habilitados->count(),
                'ausentes' => $filas->count(),
                'presentes' => $habilitados->count() - $filas->count(),
                'ingresaron' => $ingresaron,
                'inhabilitados' => $inhabilitados,
                'porcentaje' => $habilitados->count() > 0 ? round($filas->count() / $habilitados->count() * 100, 1) : 0.0,
            ],
            'filas' => $filas->values()->map(fn ($f, $n) => [
                'n' => $n + 1,
                'codigo' => $f->codigo_universitario,
                'ci' => $this->ci($f),
                'nombre' => trim($f->apellidos.', '.$f->nombres, ' ,'),
                'carrera' => $f->carrera ?: '—',
                'correo' => $f->correo_institucional ?: '—',
                'foto' => Estudiante::FOTO_ESTADOS[$f->foto_estado ?: 'SIN_FOTO'] ?? 'Sin foto',
            ])->all(),
        ];
    }

    // ------------------------------------------------------------------
    //  RQ18 · Horarios de ingreso y cantidad de estudiantes registrados
    // ------------------------------------------------------------------

    /**
     * Ingresos agrupados por franjas del reloj (cada $franja minutos), para saber cuándo llegan los estudiantes
     * y cuántos se registran. Incluye una comparación con los demás exámenes que puede ver el usuario.
     *
     * @param  Collection<int,Examen>|null  $otrosExamenes  exámenes a comparar (si es null, no se arma el comparativo)
     */
    public function horarios(Examen $examen, int $franja = 15, ?Collection $otrosExamenes = null): array
    {
        $franja = in_array($franja, self::FRANJAS_VALIDAS, true) ? $franja : 15;
        $zona = TransparenciaIngreso::zonaHoraria();
        $inicio = $examen->inicio;

        $ingresos = DB::table('ingreso as i')
            ->leftJoin('ambientes as a', 'a.id', '=', 'i.ambiente_id')
            ->where('i.examen_id', $examen->id)
            ->orderBy('i.fecha_hora_ingreso')
            ->get(['i.fecha_hora_ingreso', 'i.metodo_verificacion', 'a.nombre as ambiente']);

        $momentos = $ingresos->map(fn ($f) => Carbon::parse($f->fecha_hora_ingreso)->timezone($zona));
        $total = $momentos->count();

        $franjas = [];
        if ($total > 0) {
            // Se muestran también las franjas vacías entre la primera y la última para ver los "huecos".
            $desde = $this->pisoFranja($momentos->first(), $franja);
            $hasta = $this->pisoFranja($momentos->last(), $franja);
            for ($t = $desde->copy(); $t->lte($hasta); $t->addMinutes($franja)) {
                $franjas[$t->format('Y-m-d H:i')] = 0;
            }
            foreach ($momentos as $m) {
                $franjas[$this->pisoFranja($m, $franja)->format('Y-m-d H:i')]++;
            }
        }

        $acumulado = 0;
        $filasFranjas = [];
        foreach ($franjas as $clave => $cantidad) {
            $acumulado += $cantidad;
            $ini = Carbon::createFromFormat('Y-m-d H:i', $clave, $zona);
            $filasFranjas[] = [
                'franja' => $ini->format('H:i').' – '.$ini->copy()->addMinutes($franja)->format('H:i'),
                'cantidad' => $cantidad,
                'acumulado' => $acumulado,
                'porcentaje_acumulado' => $total > 0 ? round($acumulado / $total * 100, 1) : 0.0,
                'minutos_inicio' => $inicio->diffInMinutes($ini, false),   // + después del inicio, − antes
            ];
        }

        $pico = $total > 0 ? collect($filasFranjas)->sortByDesc('cantidad')->first() : null;
        $minutosRespectoInicio = $momentos->map(fn ($m) => $inicio->diffInMinutes($m, false));   // − = llegó antes del inicio
        $habilitados = $this->idsHabilitados($examen)->count();

        $porAmbiente = $ingresos->groupBy(fn ($f) => $f->ambiente ?: (string) $examen->ambiente)
            ->map->count()->sortDesc()->map(fn ($c, $a) => ['ambiente' => $a, 'cantidad' => $c, 'porcentaje' => round($c / max(1, $total) * 100, 1)])->values()->all();

        return [
            'franja_min' => $franja,
            'resumen' => [
                'habilitados' => $habilitados,
                'registrados' => $total,
                'primer_ingreso' => $total ? $momentos->first()->format('d/m/Y H:i:s') : '—',
                'ultimo_ingreso' => $total ? $momentos->last()->format('d/m/Y H:i:s') : '—',
                'hora_pico' => $pico ? $pico['franja'].' ('.$pico['cantidad'].')' : '—',
                'antes_del_inicio' => $minutosRespectoInicio->filter(fn ($m) => $m <= 0)->count(),
                'despues_del_inicio' => $minutosRespectoInicio->filter(fn ($m) => $m > 0)->count(),
                'llegada_promedio' => $total ? $this->etiquetaMinutos((int) round($minutosRespectoInicio->avg())) : '—',
                'inicio_examen' => $inicio->format('d/m/Y H:i'),
            ],
            'franjas' => $filasFranjas,
            'por_ambiente' => $porAmbiente,
            'comparativo' => $this->comparativo($examen, $otrosExamenes ?? collect()),
        ];
    }

    /** Cantidad de registrados por examen (para planificar los próximos): habilitados, ingresaron, primer y último ingreso. */
    private function comparativo(Examen $actual, Collection $examenes): array
    {
        $ids = $examenes->pluck('id')->push($actual->id)->unique()->values();
        $zona = TransparenciaIngreso::zonaHoraria();

        $ingresos = DB::table('ingreso')->selectRaw('examen_id, COUNT(*) as total, MIN(fecha_hora_ingreso) as primero, MAX(fecha_hora_ingreso) as ultimo')
            ->whereIn('examen_id', $ids)->groupBy('examen_id')->get()->keyBy('examen_id');
        $habilitados = Habilitacion::selectRaw('examen_id, COUNT(*) as total')->where('estado', 'habilitado')
            ->whereIn('examen_id', $ids)->groupBy('examen_id')->pluck('total', 'examen_id');

        return $examenes->prepend($actual)->unique('id')->values()->map(function (Examen $x) use ($ingresos, $habilitados, $zona, $actual) {
            $i = $ingresos->get($x->id);
            $registrados = (int) ($i->total ?? 0);
            $hab = (int) ($habilitados[$x->id] ?? 0);

            return [
                'id' => $x->id,
                'actual' => $x->id === $actual->id,
                'examen' => $x->nombre,
                'fecha' => $x->fecha->format('d/m/Y').' '.$x->hora,
                'aula' => (string) $x->ambiente,
                'habilitados' => $hab,
                'registrados' => $registrados,
                'porcentaje' => $hab > 0 ? round($registrados / $hab * 100, 1) : 0.0,
                'primer_ingreso' => $i ? Carbon::parse($i->primero)->timezone($zona)->format('H:i') : '—',
                'ultimo_ingreso' => $i ? Carbon::parse($i->ultimo)->timezone($zona)->format('H:i') : '—',
            ];
        })->all();
    }

    // ------------------------------------------------------------------
    //  RQ19 · Intentos de ingreso a un examen que no le corresponde
    // ------------------------------------------------------------------

    /**
     * Intentos registrados por RQ12 (motivo EXAMEN_NO_CORRESPONDE) en este examen:
     * quién, cuándo (fecha y hora), con qué dato, en qué aula, quién controlaba y a qué examen sí le corresponde.
     */
    public function intentosIndebidos(Examen $examen, ?ExamenNoCorrespondeService $noCorresponde = null): array
    {
        $noCorresponde ??= new ExamenNoCorrespondeService();

        $registros = DB::table('intento_ingreso as i')
            ->leftJoin('estudiante as e', 'e.id_estudiante', '=', 'i.estudiante_id')
            ->leftJoin('ambientes as a', 'a.id', '=', 'i.ambiente_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.controlador_id')
            ->where('i.examen_id', $examen->id)
            ->where('i.motivo', ExamenNoCorrespondeService::MOTIVO)
            ->orderBy('i.fecha_hora')->orderBy('i.id')
            ->get([
                'i.id', 'i.estudiante_id', 'i.tipo', 'i.dato_verificado', 'i.fecha_hora',
                'e.codigo_universitario', 'e.documento_identidad', 'e.ci_complemento', 'e.nombres', 'e.apellidos', 'e.carrera',
                'a.nombre as ambiente', 'u.name as controlador',
            ]);

        $metodos = ['CODIGO_UNIV' => 'Código universitario', 'CODIGO' => 'Código universitario', 'CI' => 'Cédula de identidad', 'QR' => 'Código QR'];

        // Examen que sí le corresponde, una sola consulta por estudiante distinto.
        $correspondientes = [];
        foreach ($registros->pluck('estudiante_id')->filter()->unique() as $estudianteId) {
            $estudiante = Estudiante::find($estudianteId);
            $examenes = $estudiante ? $noCorresponde->examenesAsignados($estudiante, 1) : [];
            $correspondientes[$estudianteId] = $examenes
                ? $examenes[0]['nombre'].' · '.$examenes[0]['fecha_local'].' '.$examenes[0]['hora'].' · Aula '.$examenes[0]['ambiente']
                : 'Sin examen habilitado próximo';
        }

        $contador = [];
        $filas = $registros->values()->map(function ($f, $n) use (&$contador, $metodos, $correspondientes, $examen) {
            $contador[$f->estudiante_id] = ($contador[$f->estudiante_id] ?? 0) + 1;

            return [
                'n' => $n + 1,
                'fecha' => Carbon::parse($f->fecha_hora)->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y'),
                'hora' => Carbon::parse($f->fecha_hora)->timezone(TransparenciaIngreso::zonaHoraria())->format('H:i:s'),
                'codigo' => $f->codigo_universitario ?: '—',
                'ci' => $f->estudiante_id ? $this->ci($f) : '—',
                'nombre' => $f->estudiante_id ? trim($f->apellidos.', '.$f->nombres, ' ,') : 'Sin estudiante identificado',
                'carrera' => $f->carrera ?: '—',
                'metodo' => $metodos[$f->tipo] ?? $f->tipo,
                'dato' => $f->dato_verificado,
                'ambiente' => $f->ambiente ?: (string) $examen->ambiente,
                'controlador' => $f->controlador ?: '—',
                'intento' => $contador[$f->estudiante_id],
                'le_corresponde' => $f->estudiante_id ? ($correspondientes[$f->estudiante_id] ?? '—') : '—',
            ];
        })->all();

        $estudiantes = $registros->pluck('estudiante_id')->filter()->unique()->count();
        $reincidentes = collect($contador)->filter(fn ($c) => $c >= 2)->count();

        return [
            'resumen' => [
                'intentos' => $registros->count(),
                'estudiantes' => $estudiantes,
                'reincidentes' => $reincidentes,
                'primer_intento' => $registros->isNotEmpty() ? Carbon::parse($registros->first()->fecha_hora)->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s') : '—',
                'ultimo_intento' => $registros->isNotEmpty() ? Carbon::parse($registros->last()->fecha_hora)->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s') : '—',
            ],
            'filas' => $filas,
        ];
    }

    // ------------------------------------------------------------------
    //  Utilidades
    // ------------------------------------------------------------------

    /** IDs (int) de los estudiantes con estado "habilitado" en el examen. */
    private function idsHabilitados(Examen $examen): Collection
    {
        return Habilitacion::where('examen_id', $examen->id)->where('estado', 'habilitado')
            ->pluck('estudiante_id')->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function local($fecha): string
    {
        return $fecha ? Carbon::parse($fecha)->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s') : '—';
    }

    private function ci($f): string
    {
        $ci = (string) ($f->documento_identidad ?? '');

        return $ci === '' ? '—' : (($f->ci_complemento ?? null) ? $ci.'-'.$f->ci_complemento : $ci);
    }

    private function pisoFranja(Carbon $momento, int $franja): Carbon
    {
        $m = $momento->copy()->second(0);

        return $m->minute(intdiv($m->minute, $franja) * $franja);
    }

    /** "12 min antes del inicio" / "5 min después del inicio" / "justo al inicio". */
    private function etiquetaMinutos(int $minutos): string
    {
        if ($minutos === 0) {
            return 'justo al inicio';
        }

        return abs($minutos).' min '.($minutos < 0 ? 'antes' : 'después').' del inicio';
    }
}
