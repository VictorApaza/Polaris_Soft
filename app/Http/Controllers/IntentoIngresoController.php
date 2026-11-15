<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\TransparenciaIngreso;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** RQ12: consulta de los intentos de ingreso rechazados de un examen (insumo del reporte RQ19). */
class IntentoIngresoController extends Controller
{
    /**
     * GET /intentos-ingreso/examen/{examen}
     * Filtros opcionales: ?motivo=EXAMEN_NO_CORRESPONDE  ?desde=2026-11-05 08:00:00  ?limite=200
     */
    public function porExamen(Request $request, Examen $examen): JsonResponse
    {
        abort_unless($examen->permiteA($request->user()), 403, 'Ese examen no es de una de tus materias.');

        $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
            'desde' => ['nullable', 'date'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $base = DB::table('intento_ingreso as i')
            ->where('i.examen_id', $examen->id)
            ->when($request->filled('motivo'), fn ($q) => $q->where('i.motivo', $request->motivo))
            ->when($request->filled('desde'), fn ($q) => $q->where('i.fecha_hora', '>=', $request->desde));

        $porMotivo = (clone $base)->selectRaw('i.motivo, COUNT(*) as total')->groupBy('i.motivo')->pluck('total', 'motivo');

        $filas = (clone $base)
            ->leftJoin('estudiante as e', 'e.id_estudiante', '=', 'i.estudiante_id')
            ->leftJoin('ambientes as a', 'a.id', '=', 'i.ambiente_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.controlador_id')
            ->orderByDesc('i.fecha_hora')->orderByDesc('i.id')
            ->limit((int) $request->input('limite', 200))
            ->get([
                'i.id', 'i.estudiante_id', 'e.codigo_universitario', 'e.documento_identidad', 'e.nombres', 'e.apellidos',
                'i.tipo', 'i.dato_verificado', 'i.resultado', 'i.motivo', 'a.nombre as ambiente', 'u.name as controlador', 'i.fecha_hora',
            ])
            ->map(fn ($f) => [
                'id' => $f->id,
                'estudiante_id' => $f->estudiante_id,
                'codigo_universitario' => $f->codigo_universitario,
                'ci' => $f->documento_identidad,
                'nombre' => $f->estudiante_id ? trim($f->nombres.' '.$f->apellidos) : null,
                'metodo' => $f->tipo,
                'dato_verificado' => $f->dato_verificado,
                'resultado' => $f->resultado,
                'motivo' => $f->motivo,
                'ambiente' => $f->ambiente ?: (string) $examen->ambiente,
                'controlador' => $f->controlador,
                'fecha_hora' => $f->fecha_hora,
                'fecha_hora_local' => Carbon::parse($f->fecha_hora)->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s'),
            ])->values();

        return response()->json([
            'examen_id' => $examen->id,
            'total' => (int) $porMotivo->sum(),
            'por_motivo' => $porMotivo,
            'intentos' => $filas,
        ]);
    }
}
