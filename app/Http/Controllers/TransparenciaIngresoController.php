<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\TransparenciaIngreso;
use App\Services\TransparenciaIngresoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * RQ8 · Transparencia de ingreso.
 *  - index():     vista de auditoría para el personal supervisor (solo lectura).
 *  - datos():     JSON que la vista consulta cada pocos segundos para actualizarse sin recargar.
 *  - registrar(): registra la autorización de ingreso (lo llama el botón "Autorizar ingreso" del control de ingreso).
 */
class TransparenciaIngresoController extends Controller
{
    /** Cantidad máxima de registros recientes que se muestran en pantalla. */
    private const LIMITE = 50;

    // ==================================================================
    //  Vista de auditoría
    // ==================================================================

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);

        return view('transparencia.index', [
            'filtros' => $filtros,
            'inicial' => $this->datosDeAuditoria($filtros),
            'metodos' => TransparenciaIngreso::METODOS,
            // Opciones de los filtros: solo lo que realmente aparece en la bitácora.
            'examenes' => TransparenciaIngreso::query()
                ->select('examen_id', 'examen_descripcion')->distinct()
                ->orderByDesc('examen_id')->get(),
            'ambientes' => TransparenciaIngreso::query()
                ->select('ambiente_nombre')->distinct()
                ->orderBy('ambiente_nombre')->pluck('ambiente_nombre'),
        ]);
    }

    /** GET /transparencia/datos — misma consulta de la vista, en JSON (para la actualización en tiempo real). */
    public function datos(Request $request): JsonResponse
    {
        return response()->json($this->datosDeAuditoria($this->filtros($request)));
    }

    // ==================================================================
    //  Registro de la autorización
    // ==================================================================

    /**
     * POST /transparencia/autorizar
     * Body: estudiante_id, examen_id, metodo (CODIGO_UNIV|CI|QR) y, opcional, ambiente_id.
     * La hora, el controlador y los metadatos NO se envían: los captura el servidor.
     */
    public function registrar(Request $request, TransparenciaIngresoService $servicio): JsonResponse
    {
        $data = $request->validate([
            'estudiante_id' => ['required', 'integer', Rule::exists('estudiante', 'id_estudiante')],
            'examen_id' => ['required', 'integer', 'exists:examenes,id'],
            'metodo' => ['required', Rule::in(array_keys(TransparenciaIngreso::METODOS))],
            'ambiente_id' => ['nullable', 'integer', 'exists:ambientes,id'],
        ], [
            'metodo.in' => 'El método de verificación debe ser CODIGO_UNIV, CI o QR.',
        ]);

        $estudiante = Estudiante::findOrFail($data['estudiante_id']);
        $examen = Examen::findOrFail($data['examen_id']);

        // Solo se autoriza (y registra) el ingreso de estudiantes habilitados para ese examen.
        $habilitacion = Habilitacion::with('motivo')
            ->where('estudiante_id', $estudiante->getKey())
            ->where('examen_id', $examen->id)
            ->first();

        if (! $habilitacion || $habilitacion->estado !== 'habilitado') {
            $detalle = $habilitacion?->motivo?->descripcion;

            return response()->json([
                'mensaje' => $habilitacion
                    ? 'El estudiante está inhabilitado para este examen'.($detalle ? ": {$detalle}." : '.')
                    : 'El estudiante no tiene habilitación registrada para este examen.',
            ], 422);
        }

        $registro = $servicio->registrar(
            $estudiante,
            $examen,
            $request->user(),
            $data['metodo'],
            $request,
            ! empty($data['ambiente_id']) ? Ambiente::find($data['ambiente_id']) : null
        );

        return response()->json([
            'mensaje' => 'Ingreso autorizado y registrado.',
            'registro' => $this->aArreglo($registro),
        ], 201);
    }

    // ==================================================================
    //  Auxiliares
    // ==================================================================

    /** Lee y limpia los filtros (examen_id, ambiente, metodo, estudiante) de la URL. */
    private function filtros(Request $request): array
    {
        $metodo = strtoupper(trim((string) $request->query('metodo', '')));

        return [
            'examen_id' => (int) $request->query('examen_id', 0) ?: null,
            'ambiente' => trim((string) $request->query('ambiente', '')) ?: null,
            'metodo' => array_key_exists($metodo, TransparenciaIngreso::METODOS) ? $metodo : null,
            'estudiante' => trim((string) $request->query('estudiante', '')) ?: null,
        ];
    }

    /** Los registros más recientes (con los filtros aplicados) + totales. */
    private function datosDeAuditoria(array $filtros): array
    {
        $consulta = TransparenciaIngreso::query()->filtrar($filtros);

        $total = (clone $consulta)->count();

        $registros = $consulta
            ->orderByDesc('fecha_hora_ingreso')->orderByDesc('id')
            ->limit(self::LIMITE)->get();

        return [
            'total' => $total,
            'mostrados' => $registros->count(),
            'generado_en' => now()->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s'),
            'registros' => $registros->map(fn (TransparenciaIngreso $r) => $this->aArreglo($r))->values()->all(),
        ];
    }

    private function aArreglo(TransparenciaIngreso $r): array
    {
        return [
            'id' => $r->id,
            'fecha_hora' => $r->fecha_hora_local,
            'estudiante_codigo' => $r->estudiante_codigo,
            'estudiante_nombre' => $r->estudiante_nombre,
            'examen' => $r->examen_descripcion,
            'ambiente' => $r->ambiente_nombre,
            'controlador' => $r->controlador_nombre,
            'metodo' => $r->metodo_verificacion,
            'metodo_etiqueta' => $r->metodo_etiqueta,
            'ip' => $r->ip_origen,
            'user_agent' => $r->user_agent,
        ];
    }
}
