<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\Materia;
use App\Models\TransparenciaIngreso;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Control en vivo de un examen.
 *
 *  RQ14: quiénes YA ingresaron (tabla `ingreso`), con ?desde para traer solo los nuevos.
 *  RQ15: quiénes están habilitados y AÚN NO se presentaron, con los totales habilitados / ingresaron / pendientes.
 *
 * Las pestañas "Expulsados" (RQ20) y "Situaciones" (RQ21) las completan sus responsables: si existe la tabla
 * `expulsion`, aquí ya se marca como expulsado a quien figure en ella y se cuentan en el resumen.
 */
class ControlEnVivoController extends Controller
{
    /** Tope de filas por respuesta: con 2.000 estudiantes por examen la consulta sigue siendo inmediata. */
    private const LIMITE = 2000;

    /** GET /control-ingreso/en-vivo  y  GET /control-ingreso/examen/{examen}/en-vivo */
    public function vista(Request $request, ?Examen $examen = null)
    {
        $examenes = Examen::with(['asignatura', 'materia'])
            ->deDocente($request->user())
            ->where('estado', '!=', 'finalizado')
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->get();

        if ($examen) {
            abort_unless($examen->permiteA($request->user()), 403, 'Ese examen no es de una de tus materias.');
            if (! $examenes->contains('id', $examen->id)) {
                $examenes->push($examen->loadMissing(['asignatura', 'materia'])); // examen ya finalizado: se puede consultar igual
            }
        } else {
            $examen = $examenes->first();
        }

        return view('control.en_vivo', [
            'examenes' => $examenes,
            'examen' => $examen,
        ]);
    }

    /**
     * GET /control-ingreso/examen/{examen}/ingresados[?desde=YYYY-MM-DD HH:MM:SS]
     * Sin "desde": todos los ingresos del examen. Con "desde": solo los de esa hora en adelante (el cliente
     * descarta los que ya tiene por su id). Usa el índice (examen_id, fecha_hora_ingreso).
     */
    public function ingresados(Request $request, Examen $examen): JsonResponse
    {
        $this->autorizar($request, $examen);

        $request->validate(['desde' => ['nullable', 'date_format:Y-m-d H:i:s']]);

        $consulta = DB::table('ingreso as i')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'i.estudiante_id')
            ->leftJoin('ambientes as a', 'a.id', '=', 'i.ambiente_id')
            ->where('i.examen_id', $examen->id);

        $columnaExpulsion = DB::raw('NULL as expulsion_id');
        if (Schema::hasTable('expulsion')) {
            $consulta->leftJoin('expulsion as x', function ($j) {
                $j->on('x.examen_id', '=', 'i.examen_id')->on('x.estudiante_id', '=', 'i.estudiante_id');
            });
            $columnaExpulsion = 'x.id as expulsion_id';
        }

        if ($request->filled('desde')) {
            $consulta->where('i.fecha_hora_ingreso', '>=', $request->desde);
        }

        $filas = $consulta
            ->orderByDesc('i.fecha_hora_ingreso')->orderByDesc('i.id')
            ->limit(self::LIMITE)
            ->get([
                'i.id', 'i.estudiante_id', 'i.fecha_hora_ingreso', 'i.metodo_verificacion',
                'e.codigo_universitario', 'e.documento_identidad', 'e.ci_complemento', 'e.nombres', 'e.apellidos',
                'a.nombre as ambiente', $columnaExpulsion,
            ]);

        $ultimo = $filas->max('fecha_hora_ingreso') ?: $request->input('desde');

        return response()->json([
            'examen_id' => $examen->id,
            'resumen' => $this->resumen($examen),
            'ultimo' => $ultimo,
            'hora_servidor' => $this->ahora(),
            'nuevos' => $filas->map(fn ($f) => [
                'id' => $f->id,
                'estudiante_id' => $f->estudiante_id,
                'codigo' => $f->codigo_universitario,
                'ci' => $this->ci($f),
                'nombre' => trim($f->nombres.' '.$f->apellidos),
                'ambiente' => $f->ambiente ?: (string) $examen->ambiente,
                'metodo' => $f->metodo_verificacion,
                'fecha_hora_ingreso' => $f->fecha_hora_ingreso,
                'fecha_hora_local' => Carbon::parse($f->fecha_hora_ingreso)->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s'),
                'expulsado' => $f->expulsion_id !== null,
            ])->values(),
        ]);
    }

    /** GET /control-ingreso/examen/{examen}/pendientes: habilitados que todavía no tienen ingreso. */
    public function pendientes(Request $request, Examen $examen): JsonResponse
    {
        $this->autorizar($request, $examen);

        $filas = $this->consultaPendientes($examen)
            ->orderBy('e.apellidos')->orderBy('e.nombres')
            ->limit(self::LIMITE)
            ->get(['e.id_estudiante', 'e.codigo_universitario', 'e.documento_identidad', 'e.ci_complemento', 'e.nombres', 'e.apellidos']);

        $grupos = $this->gruposPorEstudiante($examen, $filas->pluck('id_estudiante')->all());

        return response()->json([
            'examen_id' => $examen->id,
            'resumen' => $this->resumen($examen),
            'hora_servidor' => $this->ahora(),
            'pendientes' => $filas->map(fn ($f) => [
                'estudiante_id' => $f->id_estudiante,
                'codigo' => $f->codigo_universitario,
                'ci' => $this->ci($f),
                'nombre' => trim($f->nombres.' '.$f->apellidos),
                'grupo' => $grupos[$f->id_estudiante] ?? '',
            ])->values(),
        ]);
    }

    /** Totales del examen: habilitados, ingresaron, pendientes y (si ya existe la tabla de RQ20) expulsados. */
    private function resumen(Examen $examen): array
    {
        return [
            'habilitados' => Habilitacion::where('examen_id', $examen->id)->where('estado', 'habilitado')->count(),
            'ingresaron' => DB::table('ingreso')->where('examen_id', $examen->id)->count(),
            'pendientes' => $this->consultaPendientes($examen)->count(),
            'expulsados' => Schema::hasTable('expulsion') ? DB::table('expulsion')->where('examen_id', $examen->id)->count() : 0,
        ];
    }

    /** Habilitados del examen que no tienen fila en `ingreso`. */
    private function consultaPendientes(Examen $examen): Builder
    {
        return DB::table('habilitacion as h')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'h.estudiante_id')
            ->where('h.examen_id', $examen->id)
            ->where('h.estado', 'habilitado')
            ->whereNotExists(function ($sub) use ($examen) {
                $sub->select(DB::raw(1))->from('ingreso as i')
                    ->whereColumn('i.estudiante_id', 'h.estudiante_id')
                    ->where('i.examen_id', $examen->id);
            });
    }

    /** Grupo(s) de cada estudiante en la materia del examen: [id_estudiante => "Grupo A"]. */
    private function gruposPorEstudiante(Examen $examen, array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $materiaIds = $examen->materia_id
            ? [$examen->materia_id]
            : Materia::where('sigla', $examen->asignatura?->codigo)->pluck('id')->all();
        if (! $materiaIds) {
            return [];
        }

        return DB::table('asignaciones as a')
            ->join('grupos as g', 'g.id', '=', 'a.grupo_id')
            ->whereIn('a.estudiante_id', $ids)
            ->whereIn('a.materia_id', $materiaIds)
            ->get(['a.estudiante_id', 'g.nombre'])
            ->groupBy('estudiante_id')
            ->map(fn ($c) => $c->pluck('nombre')->unique()->implode(', '))
            ->all();
    }

    private function autorizar(Request $request, Examen $examen): void
    {
        abort_unless($examen->permiteA($request->user()), 403, 'Ese examen no es de una de tus materias.');
    }

    private function ci(object $f): string
    {
        return $f->documento_identidad.($f->ci_complemento ? '-'.$f->ci_complemento : '');
    }

    private function ahora(): string
    {
        return now()->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s');
    }
}
