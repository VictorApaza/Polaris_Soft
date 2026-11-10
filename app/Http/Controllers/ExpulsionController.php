<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Expulsion;
use App\Models\Ingreso;
use App\Models\TransparenciaIngreso;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RQ20: expulsión de estudiantes durante un examen.
 *
 *  POST /control-ingreso/expulsiones                      → registra la expulsión (motivo obligatorio)
 *  GET  /control-ingreso/examen/{examen}/expulsados       → lista de expulsados del examen (pestaña "Expulsados")
 *
 * La hora la pone el servidor (America/La_Paz) y el usuario que registra es el autenticado: nada de eso se recibe
 * desde el navegador. Para evitar el reingreso, VerificacionController consulta la tabla `expulsion`.
 */
class ExpulsionController extends Controller
{
    /** Tope de filas por respuesta (igual que Control en vivo). */
    private const LIMITE = 2000;

    /** POST /control-ingreso/expulsiones */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'examen_id' => ['required', 'integer', 'exists:examenes,id'],
            'estudiante_id' => ['required', 'integer', 'exists:estudiante,id_estudiante'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'examen_id.required' => 'Falta el examen.',
            'estudiante_id.required' => 'Falta el estudiante.',
            'motivo.required' => 'Escribe el motivo de la expulsión.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede pasar de 500 caracteres.',
        ]);

        $examen = Examen::findOrFail($data['examen_id']);
        if (! $examen->permiteA($request->user())) {
            return response()->json(['mensaje' => 'Ese examen no es de una de tus materias.'], 403);
        }

        $estudiante = Estudiante::findOrFail($data['estudiante_id']);

        // Solo se expulsa a quien ya está dentro del examen.
        $ingreso = Ingreso::where('estudiante_id', $estudiante->id_estudiante)->where('examen_id', $examen->id)->first();
        if (! $ingreso) {
            return response()->json([
                'tipo' => 'sin_ingreso',
                'mensaje' => 'El estudiante no tiene ingreso registrado en este examen, por eso no se lo puede expulsar.',
            ], 422);
        }

        if ($previa = Expulsion::de((int) $estudiante->id_estudiante, (int) $examen->id)) {
            return $this->yaExpulsado($previa);
        }

        try {
            $expulsion = Expulsion::create([
                'examen_id' => $examen->id,
                'estudiante_id' => $estudiante->id_estudiante,
                'ambiente_id' => $ingreso->ambiente_id,
                'motivo' => trim($data['motivo']),
                'registrado_por' => $request->user()->id,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Dos controladores expulsaron al mismo tiempo: la otra expulsión llegó primero.
            return $this->yaExpulsado(Expulsion::de((int) $estudiante->id_estudiante, (int) $examen->id));
        }

        $expulsion->refresh();

        return response()->json([
            'mensaje' => 'Expulsión registrada.',
            'expulsion' => [
                'id' => $expulsion->id,
                'estudiante_id' => $estudiante->id_estudiante,
                'codigo' => $estudiante->codigo_universitario,
                'ci' => $this->ci($estudiante),
                'nombre' => trim($estudiante->nombres.' '.$estudiante->apellidos),
                'ambiente' => $this->nombreAmbiente($ingreso->ambiente_id, $examen),
                'motivo' => $expulsion->motivo,
                'fecha_hora' => $expulsion->fecha_hora->format('Y-m-d H:i:s'),
                'fecha_hora_local' => $expulsion->fecha_hora_local,
                'registrado_por' => $this->nombreUsuario($request->user()),
            ],
        ], 201);
    }

    /** GET /control-ingreso/examen/{examen}/expulsados */
    public function porExamen(Request $request, Examen $examen): JsonResponse
    {
        abort_unless($examen->permiteA($request->user()), 403, 'Ese examen no es de una de tus materias.');

        $filas = DB::table('expulsion as x')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'x.estudiante_id')
            ->leftJoin('ambientes as a', 'a.id', '=', 'x.ambiente_id')
            ->leftJoin('users as u', 'u.id', '=', 'x.registrado_por')
            ->where('x.examen_id', $examen->id)
            ->orderByDesc('x.fecha_hora')->orderByDesc('x.id')
            ->limit(self::LIMITE)
            ->get([
                'x.id', 'x.estudiante_id', 'x.motivo', 'x.fecha_hora',
                'e.codigo_universitario', 'e.documento_identidad', 'e.ci_complemento', 'e.nombres', 'e.apellidos',
                'a.nombre as ambiente', 'u.name as u_name', 'u.nombre as u_nombre', 'u.apellido as u_apellido',
            ]);

        return response()->json([
            'examen_id' => $examen->id,
            'total' => $filas->count(),
            'hora_servidor' => now()->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s'),
            'expulsados' => $filas->map(fn ($f) => [
                'id' => $f->id,
                'estudiante_id' => $f->estudiante_id,
                'codigo' => $f->codigo_universitario,
                'ci' => $f->documento_identidad.($f->ci_complemento ? '-'.$f->ci_complemento : ''),
                'nombre' => trim($f->nombres.' '.$f->apellidos),
                'ambiente' => $f->ambiente ?: (string) $examen->ambiente,
                'motivo' => $f->motivo,
                'fecha_hora' => $f->fecha_hora,
                'fecha_hora_local' => Carbon::parse($f->fecha_hora)->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s'),
                'registrado_por' => trim(($f->u_nombre ?? '').' '.($f->u_apellido ?? '')) ?: (string) $f->u_name,
            ])->values(),
        ]);
    }

    private function yaExpulsado(?Expulsion $previa): JsonResponse
    {
        return response()->json([
            'tipo' => 'ya_expulsado',
            'mensaje' => 'El estudiante ya fue expulsado de este examen'.($previa ? ' el '.$previa->fecha_hora_local : '').'.',
        ], 422);
    }

    private function ci(Estudiante $e): string
    {
        return $e->documento_identidad.($e->ci_complemento ? '-'.$e->ci_complemento : '');
    }

    private function nombreAmbiente(?int $ambienteId, Examen $examen): string
    {
        $nombre = $ambienteId ? DB::table('ambientes')->where('id', $ambienteId)->value('nombre') : null;

        return (string) ($nombre ?: $examen->ambiente);
    }

    private function nombreUsuario($usuario): string
    {
        return trim(($usuario->nombre ?? '').' '.($usuario->apellido ?? '')) ?: (string) $usuario->name;
    }
}
