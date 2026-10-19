<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\IntentoIngreso;
use App\Models\TransparenciaIngreso;
use App\Models\Verificacion;
use App\Services\QrEstudianteService;
use App\Services\TransparenciaIngresoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VerificacionController extends Controller
{
    public function index()
    {
        $examenes = Examen::with(['materia', 'asignatura'])
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
        $cerrar = function (string $resultado, string $tipoRespuesta, string $mensaje, int $http, ?Estudiante $est, array $extra = [], bool $esIntento = true) use ($request, $examen, $tipoBitacora, $dato): JsonResponse {
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
                    IntentoIngreso::create($comunes + ['motivo' => Str::limit($mensaje, 255, '')]);
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
            return $cerrar('INACTIVO', 'inactivo', 'El estudiante no se encuentra activo para rendir el examen.', 422, $estudiante, $resumen);
        }

        // ---- 3) ¿Ya ingresó a este examen? (RQ11) ----
        $ingresoPrevio = TransparenciaIngreso::where('estudiante_id', $estudiante->id_estudiante)
            ->where('examen_id', $examen->id)
            ->latest('fecha_hora_ingreso')
            ->first();

        if ($ingresoPrevio) {
            return $cerrar('YA_INGRESO', 'ya_ingreso', 'El estudiante ya registró ingreso para este examen.', 409, $estudiante,
                ['fecha_hora' => $ingresoPrevio->fecha_hora_ingreso] + $resumen, esIntento: false);
        }

        // ---- 4) Habilitación para este examen (RQ3 / RQ4 / RQ9) ----
        $habilitacion = Habilitacion::with('motivo')
            ->where('estudiante_id', $estudiante->id_estudiante)
            ->where('examen_id', $examen->id)
            ->first();

        if (! $habilitacion) {
            return $cerrar('NO_HABILITADO', 'no_habilitado', 'El estudiante no tiene habilitación registrada para este examen.', 422, $estudiante, $resumen);
        }

        if ($habilitacion->estado !== 'habilitado') {
            $motivo = $habilitacion->motivo?->descripcion;

            return $cerrar('NO_HABILITADO', 'no_habilitado', $motivo ?? 'El estudiante está inhabilitado para este examen.', 422, $estudiante,
                ['motivo_inhabilitacion' => $motivo] + $resumen);
        }

        return $cerrar('HABILITADO', 'habilitado', 'Estudiante habilitado para este examen.', 200, $estudiante,
            ['codigo' => $estudiante->codigo_universitario, 'id_habilitacion' => $habilitacion->id] + $resumen, esIntento: false);
    }

    public function ingreso(Request $request, TransparenciaIngresoService $servicio): JsonResponse
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

        $habilitacion = Habilitacion::where('estudiante_id', $estudianteId)
            ->where('examen_id', $examenId)
            ->first();

        if (! $habilitacion || $habilitacion->estado !== 'habilitado') {
            $detalle = $habilitacion?->motivo?->descripcion;

            return response()->json([
                'mensaje' => $habilitacion
                    ? 'El estudiante está inhabilitado para este examen'.($detalle ? ': '.$detalle.'.' : '.')
                    : 'El estudiante no tiene habilitación registrada para este examen.',
            ], 422);
        }

        if (TransparenciaIngreso::where('estudiante_id', $estudianteId)
            ->where('examen_id', $examenId)->exists()) {
            return response()->json([
                'mensaje' => 'El estudiante ya registró ingreso para este examen.',
            ], 409);
        }

        $registro = $servicio->registrar(
            $estudiante,
            $examen,
            $request->user(),
            $data['metodo'] ?? 'CODIGO_UNIV',
            $request
        );

        return response()->json([
            'mensaje' => 'Ingreso registrado correctamente.',
            'registro' => [
                'id' => $registro->id,
                'fecha_hora' => $registro->fecha_hora_ingreso,
                'estudiante' => $estudiante->nombre_completo,
                'examen' => $registro->examen_descripcion,
            ],
        ], 201);
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
        ];
    }
}
