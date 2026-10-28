<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\Ingreso;
use App\Models\IntentoIngreso;
use App\Models\TransparenciaIngreso;
use App\Models\Verificacion;
use App\Services\ExamenNoCorrespondeService;
use App\Services\IngresoService;
use App\Services\QrEstudianteService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VerificacionController extends Controller
{
    /** RQ12: detecta y describe el ingreso a un examen que no le corresponde al estudiante. */
    public function __construct(private ExamenNoCorrespondeService $noCorresponde)
    {
    }

    public function index()
    {
        $examenes = Examen::with(['materia', 'asignatura'])
            ->where('estado', '!=', 'finalizado')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        return view('verificacion.index', compact('examenes'));
    }

    /** Atajos de las rutas del plan del sprint: POST /verificar/codigo, /verificar/ci y /verificar/qr. */
    public function verificarCodigo(Request $request, QrEstudianteService $qr): JsonResponse
    {
        $request->merge(['metodo_verificacion' => 'CODIGO_UNIV']);

        return $this->verificar($request, $qr);
    }

    public function verificarCi(Request $request, QrEstudianteService $qr): JsonResponse
    {
        $request->merge(['metodo_verificacion' => 'CI']);

        return $this->verificar($request, $qr);
    }

    public function verificarQr(Request $request, QrEstudianteService $qr): JsonResponse
    {
        $request->merge(['metodo_verificacion' => 'QR']);

        return $this->verificar($request, $qr);
    }

    /** Equivalencia entre el método de la pantalla y el tipo guardado en `verificacion` / `intento_ingreso`. */
    private const TIPO_BITACORA = [
        'CODIGO_UNIV' => 'CODIGO_UNIVERSITARIO',
        'CI' => 'DOCUMENTO_IDENTIDAD',
        'QR' => 'CODIGO_QR',
    ];

    public function verificar(Request $request, QrEstudianteService $qr): JsonResponse
    {
        $metodo = strtoupper(trim((string) ($request->input('metodo_verificacion') ?: 'CODIGO_UNIV')));

        $reglas = [
            'id_examen' => ['required', 'integer', 'exists:examenes,id'],
            'metodo_verificacion' => ['required', Rule::in(['CODIGO_UNIV', 'CI', 'QR'])],
        ];

        if ($metodo === 'CODIGO_UNIV') {
            $reglas['codigo_universitario'] = ['required', 'digits:9'];
        } elseif ($metodo === 'CI') {
            $reglas['documento_identidad'] = ['required', 'digits_between:5,10'];
            $reglas['ci_complemento'] = ['nullable', 'max:2'];
        } else {
            $reglas['token_qr'] = ['required', 'string', 'max:1000'];
        }

        $data = $request->validate($reglas, [
            'id_examen.required' => 'Selecciona un examen antes de verificar.',
            'id_examen.exists' => 'El examen seleccionado no existe.',
            'metodo_verificacion.required' => 'Selecciona un método de verificación.',
            'codigo_universitario.required' => 'Ingresa el código universitario.',
            'codigo_universitario.digits' => 'El código universitario debe tener 9 dígitos.',
            'documento_identidad.required' => 'Ingresa la cédula de identidad.',
            'documento_identidad.digits_between' => 'La cédula debe tener entre 5 y 10 dígitos.',
            'token_qr.required' => 'No se recibió el contenido del código QR.',
        ]);

        $examen = Examen::findOrFail($data['id_examen']);
        $tipoBitacora = self::TIPO_BITACORA[$metodo];

        // ---- 1) Identificar al estudiante según el método ----
        if ($metodo === 'CODIGO_UNIV') {
            $dato = trim((string) $data['codigo_universitario']);
            $estudiante = Estudiante::where('codigo_universitario', $dato)->first();
            $msgNoExiste = 'No existe un estudiante registrado con ese código universitario.';
        } elseif ($metodo === 'CI') {
            $dato = trim((string) $data['documento_identidad']);
            $complemento = strtoupper(trim((string) ($data['ci_complemento'] ?? '')));

            $query = Estudiante::where('documento_identidad', $dato);
            if ($complemento === '') {
                $query->where(function ($q) {
                    $q->whereNull('ci_complemento')->orWhere('ci_complemento', '');
                });
            } else {
                $query->where('ci_complemento', $complemento);
            }

            $estudiante = $query->first();
            $msgNoExiste = 'No existe un estudiante registrado con esa cédula de identidad.';
        } else {
            $dato = Str::limit(trim((string) $data['token_qr']), 60, '…');
            $estudiante = $qr->validarToken($data['token_qr']);
            $msgNoExiste = 'Código QR no válido o alterado.';
        }

        // Cierra la verificación: la deja en la bitácora (y como intento si fue negativa) y arma la respuesta JSON.
        $ambienteId = $this->ambienteIdDe($examen);

        $cerrar = function (string $resultado, string $tipoRespuesta, string $mensaje, int $http, ?Estudiante $est, array $extra = [], bool $esIntento = true) use ($request, $examen, $tipoBitacora, $dato, $ambienteId): JsonResponse {
            $comunes = [
                'estudiante_id' => $est?->getKey(),
                'examen_id' => $examen->id,
                'controlador_id' => $request->user()->id,
                'tipo' => $tipoBitacora,
                'dato_verificado' => $dato,
                'fecha_hora' => now(),
            ];

            try {
                Verificacion::create($comunes + ['resultado' => $resultado]);
                if ($esIntento) {
                    IntentoIngreso::create($comunes + [
                        'ambiente_id' => $ambienteId,
                        'resultado' => 'RECHAZADO',
                        'motivo' => Str::limit($mensaje, 255, ''),
                    ]);
                }
            } catch (\Throwable $e) {
                // La bitácora nunca debe impedir que el controlador vea el resultado en pantalla.
                report($e);
            }

            return response()->json(['tipo' => $tipoRespuesta, 'mensaje' => $mensaje] + $extra, $http);
        };

        if (! $estudiante) {
            return $cerrar($metodo === 'QR' ? 'QR_INVALIDO' : 'NO_REGISTRADO', 'codigo_no_registrado', $msgNoExiste, 404, null);
        }

        $resumen = ['estudiante' => $this->resumenEstudiante($estudiante)];

        // ---- 2) Estado del estudiante ----
        if (strtoupper((string) $estudiante->estado) !== 'ACTIVO') {
            $estadoActual = strtoupper((string) $estudiante->estado);

            return $cerrar('INACTIVO', 'inactivo', "El estudiante no se encuentra activo para rendir el examen (estado: {$estadoActual}).", 422, $estudiante,
                ['motivo' => "Estado del estudiante: {$estadoActual}"] + $resumen);
        }

        // ---- 3) ¿Ya ingresó a este examen? (RQ11) ----
        $ingresoPrevio = $this->ingresoPrevio((int) $estudiante->id_estudiante, (int) $examen->id);

        if ($ingresoPrevio) {
            // RQ11: el intento repetido queda en intento_ingreso con el motivo INGRESO_DUPLICADO.
            $this->registrarIntentoRechazado($request, $examen, $estudiante, $metodo, 'INGRESO_DUPLICADO');

            // Segundo intento con el mismo código / CI / QR: queda como INCIDENCIA con la hora exacta del intento
            // y la hora en que ingresó la primera vez, para que el docente la vea en el reporte.
            $incidenciaId = null;
            try {
                $incidenciaId = Incidencia::create([
                    'examen_id' => $examen->id,
                    'estudiante_id' => $estudiante->getKey(),
                    'tipo' => 'INTENTO_DUPLICADO',
                    'metodo' => $tipoBitacora,
                    'detalle' => 'Intentó ingresar otra vez al mismo examen (posible suplantación o uso compartido del código).',
                    'controlador_id' => $request->user()->id,
                    'ingreso_original_at' => $ingresoPrevio->fecha_hora_ingreso,
                    'fecha_hora' => now(),
                    'estado' => 'ABIERTA',
                ])->id;
            } catch (\Throwable $e) {
                report($e);
            }

            return $cerrar('YA_INGRESO', 'ya_ingreso', 'El estudiante ya registró ingreso para este examen.', 409, $estudiante, [
                'fecha_hora' => $ingresoPrevio->fecha_hora_ingreso->toIso8601String(),
                'fecha_hora_local' => $ingresoPrevio->fecha_hora_local,
                'ambiente' => $this->ambienteDelIngreso($ingresoPrevio, $examen),
                'hora_intento' => now()->toIso8601String(),
                'incidencia_id' => $incidenciaId,
                'alerta' => true,
            ] + $resumen, esIntento: false);
        }

        // ---- 4) Habilitación para este examen (RQ3 / RQ4 / RQ9) ----
        $habilitacion = Habilitacion::with('motivo')
            ->where('estudiante_id', $estudiante->id_estudiante)
            ->where('examen_id', $examen->id)
            ->first();

        if (! $habilitacion) {
            // RQ12: no está habilitado Y tampoco está inscrito en la materia del examen => el examen no le corresponde.
            // Queda en intento_ingreso (motivo EXAMEN_NO_CORRESPONDE) y la pantalla muestra una alerta inmediata.
            if ($this->noCorresponde->noLeCorresponde($examen, $estudiante)) {
                $this->registrarIntentoRechazado($request, $examen, $estudiante, $metodo, ExamenNoCorrespondeService::MOTIVO);

                return $cerrar(ExamenNoCorrespondeService::MOTIVO, 'no_corresponde', ExamenNoCorrespondeService::MENSAJE, 422, $estudiante, [
                    'motivo' => ExamenNoCorrespondeService::MENSAJE,
                    'ambiente' => $examen->ambiente,
                    'hora_intento' => now()->toIso8601String(),
                    'alerta' => true,
                ] + $this->noCorresponde->datosDeAlerta($examen, $estudiante) + $resumen, esIntento: false);
            }

            return $cerrar('NO_HABILITADO', 'no_habilitado', 'El estudiante no tiene habilitación registrada para este examen.', 422, $estudiante,
                ['motivo' => 'No tiene habilitación registrada para este examen.'] + $resumen);
        }

        if ($habilitacion->estado !== 'habilitado') {
            $motivo = $habilitacion->motivo?->descripcion;

            return $cerrar('NO_HABILITADO', 'no_habilitado', $motivo ?? 'El estudiante está inhabilitado para este examen.', 422, $estudiante,
                ['motivo_inhabilitacion' => $motivo, 'motivo' => $motivo ?? 'Inhabilitado para este examen.'] + $resumen);
        }

        return $cerrar('HABILITADO', 'habilitado', 'Estudiante habilitado para este examen.', 200, $estudiante,
            ['codigo' => $estudiante->codigo_universitario, 'id_habilitacion' => $habilitacion->id, 'ambiente' => $examen->ambiente] + $resumen, esIntento: false);
    }

    public function ingreso(Request $request, IngresoService $servicio): JsonResponse
    {
        $data = $request->validate([
            'id_estudiante' => ['required_without:estudiante_id', 'integer', 'exists:estudiante,id_estudiante'],
            'estudiante_id' => ['required_without:id_estudiante', 'integer', 'exists:estudiante,id_estudiante'],
            'id_examen' => ['required_without:examen_id', 'integer', 'exists:examenes,id'],
            'examen_id' => ['required_without:id_examen', 'integer', 'exists:examenes,id'],
            'codigo_universitario' => ['nullable', 'digits:9'],
            'metodo' => ['nullable', Rule::in(['CODIGO_UNIV', 'CI', 'QR'])],
        ], [
            'id_estudiante.required_without' => 'Falta el identificador del estudiante.',
            'id_examen.required_without' => 'Falta el identificador del examen.',
            'codigo_universitario.digits' => 'El código universitario debe tener 9 dígitos.',
        ]);

        $estudianteId = (int) ($data['id_estudiante'] ?? $data['estudiante_id']);
        $examenId = (int) ($data['id_examen'] ?? $data['examen_id']);
        $estudiante = Estudiante::findOrFail($estudianteId);
        $examen = Examen::findOrFail($examenId);

        if ($request->filled('codigo_universitario')
            && trim((string) $request->codigo_universitario) !== trim((string) $estudiante->codigo_universitario)) {
            return response()->json([
                'mensaje' => 'El código universitario no coincide con el estudiante.',
            ], 422);
        }

        $metodoIngreso = $data['metodo'] ?? 'CODIGO_UNIV';

        // RQ9: se repite la validación aunque el frontend no la haya impedido (HTTP 422 + intento registrado).
        if (strtoupper((string) $estudiante->estado) !== 'ACTIVO') {
            $estadoActual = strtoupper((string) $estudiante->estado);
            $mensaje = "El estudiante no se encuentra activo para rendir el examen (estado: {$estadoActual}).";
            $this->registrarIntentoRechazado($request, $examen, $estudiante, $metodoIngreso, $mensaje);

            return response()->json([
                'tipo' => 'inactivo',
                'mensaje' => $mensaje,
                'motivo' => "Estado del estudiante: {$estadoActual}",
            ], 422);
        }

        $habilitacion = Habilitacion::with('motivo')
            ->where('estudiante_id', $estudianteId)
            ->where('examen_id', $examenId)
            ->first();

        // RQ12: el examen no le corresponde (sin habilitación y fuera de la materia): 422 + intento registrado.
        if (! $habilitacion && $this->noCorresponde->noLeCorresponde($examen, $estudiante)) {
            $this->registrarIntentoRechazado($request, $examen, $estudiante, $metodoIngreso, ExamenNoCorrespondeService::MOTIVO);

            return response()->json([
                'tipo' => 'no_corresponde',
                'mensaje' => ExamenNoCorrespondeService::MENSAJE,
                'motivo' => ExamenNoCorrespondeService::MENSAJE,
            ] + $this->noCorresponde->datosDeAlerta($examen, $estudiante), 422);
        }

        if (! $habilitacion || $habilitacion->estado !== 'habilitado') {
            $detalle = $habilitacion?->motivo?->descripcion;
            $mensaje = $habilitacion
                ? 'El estudiante está inhabilitado para este examen'.($detalle ? ': '.$detalle.'.' : '.')
                : 'El estudiante no tiene habilitación registrada para este examen.';
            $this->registrarIntentoRechazado($request, $examen, $estudiante, $metodoIngreso, $mensaje);

            return response()->json([
                'tipo' => 'no_habilitado',
                'mensaje' => $mensaje,
                'motivo' => $detalle ?? ($habilitacion ? 'Inhabilitado para este examen.' : 'No tiene habilitación registrada para este examen.'),
            ], 422);
        }

        if ($previo = $this->ingresoPrevio($estudianteId, $examenId)) {
            $this->registrarIntentoRechazado($request, $examen, $estudiante, $metodoIngreso, 'INGRESO_DUPLICADO');

            return $this->respuestaYaRegistrado($previo, $estudiante, $examen);
        }

        // La fecha y la hora las pone el servidor (America/La_Paz); cualquier fecha enviada por el cliente se ignora.
        // `ingreso` y `transparencia_ingreso` se guardan juntas en una transacción: o quedan las dos o ninguna.
        try {
            $resultado = $servicio->registrar(
                $estudiante,
                $examen,
                $request->user(),
                $metodoIngreso,
                $request
            );
        } catch (UniqueConstraintViolationException $e) {
            // Índice único (estudiante, examen): otro clic (o un reintento tras perder la respuesta) llegó primero.
            $this->registrarIntentoRechazado($request, $examen, $estudiante, $metodoIngreso, 'INGRESO_DUPLICADO');
            $previo = $this->ingresoPrevio($estudianteId, $examenId);

            return $previo
                ? $this->respuestaYaRegistrado($previo, $estudiante, $examen)
                : response()->json(['mensaje' => 'El estudiante ya registró ingreso para este examen.'], 409);
        } catch (\Throwable $e) {
            // Cualquier otro fallo al guardar (base caída, tabla sin migrar…): no se guardó nada y se puede reintentar.
            report($e);

            return response()->json([
                'tipo' => 'error_guardado',
                'mensaje' => 'No se pudo guardar el ingreso. Revisa la conexión e inténtalo de nuevo.',
                'reintentar' => true,
            ], 500);
        }

        /** @var Ingreso $ingreso */
        $ingreso = $resultado['ingreso'];

        return response()->json([
            'mensaje' => 'Ingreso registrado correctamente.',
            'registro' => $this->datosDeRegistro($ingreso, $estudiante, $resultado['transparencia']->examen_descripcion, $resultado['transparencia']->ambiente_nombre),
        ], 201);
    }

    /** Ingreso ya guardado de ese estudiante en ese examen (tabla `ingreso`, o la bitácora RQ8 si es anterior a ella). */
    private function ingresoPrevio(int $estudianteId, int $examenId): Ingreso|TransparenciaIngreso|null
    {
        return Ingreso::where('estudiante_id', $estudianteId)->where('examen_id', $examenId)->first()
            ?? TransparenciaIngreso::where('estudiante_id', $estudianteId)->where('examen_id', $examenId)->first();
    }

    /** 409 que además devuelve la hora del ingreso ya registrado (así un reintento tras perder la respuesta se confirma solo). */
    private function respuestaYaRegistrado(Ingreso|TransparenciaIngreso $previo, Estudiante $estudiante, Examen $examen): JsonResponse
    {
        return response()->json([
            'tipo' => 'ya_registrado',
            'mensaje' => 'El estudiante ya registró ingreso para este examen.',
            'registro' => $this->datosDeRegistro($previo, $estudiante, null, $this->ambienteDelIngreso($previo, $examen)),
        ], 409);
    }

    /** Aula en la que se registró el ingreso anterior (si no se puede saber, la del examen). */
    private function ambienteDelIngreso(Ingreso|TransparenciaIngreso $previo, Examen $examen): string
    {
        if ($previo instanceof TransparenciaIngreso && filled($previo->ambiente_nombre)) {
            return (string) $previo->ambiente_nombre;
        }

        $nombre = $previo->ambiente_id ? Ambiente::find($previo->ambiente_id)?->nombre : null;

        return (string) ($nombre ?: $examen->ambiente);
    }

    /** Hora registrada: ISO 8601 con desfase (-04:00) y el texto listo para mostrar (dd/mm/aaaa HH:mm:ss). */
    private function datosDeRegistro(Ingreso|TransparenciaIngreso $registro, Estudiante $estudiante, ?string $examen = null, ?string $ambiente = null): array
    {
        return [
            'id' => $registro->id,
            'fecha_hora' => $registro->fecha_hora_ingreso->toIso8601String(),
            'fecha_hora_local' => $registro->fecha_hora_local,
            'estudiante' => $estudiante->nombre_completo,
            'examen' => $examen ?? $registro->getAttribute('examen_descripcion'),
            'ambiente' => $ambiente,
        ];
    }

    /** Aula (tabla ambientes) que tiene asignado el examen; null si no está registrada. */
    private function ambienteIdDe(Examen $examen): ?int
    {
        return Ambiente::where('nombre', (string) $examen->ambiente)->value('id');
    }

    /** Guarda en intento_ingreso un ingreso rechazado (estudiante no habilitado o no activo). Nunca rompe la respuesta. */
    private function registrarIntentoRechazado(Request $request, Examen $examen, Estudiante $estudiante, string $metodo, string $motivo): void
    {
        try {
            IntentoIngreso::create([
                'estudiante_id' => $estudiante->getKey(),
                'examen_id' => $examen->id,
                'ambiente_id' => $this->ambienteIdDe($examen),
                'controlador_id' => $request->user()->id,
                'tipo' => self::TIPO_BITACORA[$metodo] ?? 'CODIGO_UNIVERSITARIO',
                'dato_verificado' => (string) $estudiante->codigo_universitario,
                'resultado' => 'RECHAZADO',
                'motivo' => Str::limit($motivo, 255, ''),
                'fecha_hora' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function resumenEstudiante(Estudiante $estudiante): array
    {
        return [
            'id' => $estudiante->id_estudiante,
            'nombres' => $estudiante->nombres,
            'apellidos' => $estudiante->apellidos,
            'codigo' => $estudiante->codigo_universitario,
            'carnet' => $estudiante->carnet_completo,
            'carrera' => $estudiante->carrera,
            'documento_identidad' => $estudiante->documento_identidad,
            'ci_complemento' => $estudiante->ci_complemento,
            'estado' => $estudiante->estado,
            'foto_url' => $estudiante->foto_url,
            'foto_estado' => $estudiante->foto_estado ?: 'SIN_FOTO',
            'foto_estado_etiqueta' => $estudiante->foto_estado_etiqueta,
            'foto_observacion' => $estudiante->foto_observacion,
        ];
    }
}
