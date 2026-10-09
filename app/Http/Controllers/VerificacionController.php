<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Examen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerificacionController extends Controller
{
    public function index()
    {
        $examenes = Examen::with(['materia', 'asignatura'])
            ->whereIn(DB::raw('LOWER(estado)'), ['programado', 'abierto'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        return view('verificacion.index', compact('examenes'));
    }

    public function verificar(Request $request)
    {
        $data = $request->validate([
            'id_examen' => ['required', 'integer', 'exists:examenes,id'],
            'codigo_universitario' => ['required', 'string', 'max:30'],
            'documento_identidad' => ['nullable', 'string', 'max:20'],
        ]);

        $examen = Examen::findOrFail($data['id_examen']);
        $codigo = trim((string) $data['codigo_universitario']);

        $estudiante = Estudiante::where('codigo_universitario', $codigo)->first();

        if (! $estudiante) {
            $this->registrarVerificacion(null, $examen->id, 'CODIGO_UNIVERSITARIO', $codigo, 'CODIGO_NO_REGISTRADO', $examen->ambiente);
            $this->registrarIntentoIngreso(null, $examen->id, 'Código universitario no registrado', $examen->ambiente, $codigo, 'CODIGO_NO_REGISTRADO');

            return response()->json([
                'tipo' => 'codigo_no_registrado',
                'mensaje' => 'Código universitario no registrado',
            ], 404);
        }

        if (! empty($data['documento_identidad']) && trim((string) $data['documento_identidad']) !== trim((string) $estudiante->documento_identidad)) {
            $this->registrarVerificacion($estudiante->id_estudiante, $examen->id, 'CODIGO_UNIVERSITARIO', $codigo, 'DOCUMENTO_NO_COINCIDE', $examen->ambiente);
            $this->registrarIntentoIngreso($estudiante->id_estudiante, $examen->id, 'Documento físico no coincide con la identidad', $examen->ambiente, $codigo, 'DOCUMENTO_NO_COINCIDE');

            return response()->json([
                'tipo' => 'documento_no_coincide',
                'mensaje' => 'El documento físico no coincide con la identidad del estudiante.',
                'estudiante' => $this->serializarEstudiante($estudiante),
            ], 422);
        }

        if (strtoupper((string) $estudiante->estado) !== 'ACTIVO') {
            $this->registrarVerificacion($estudiante->id_estudiante, $examen->id, 'CODIGO_UNIVERSITARIO', $codigo, 'ESTUDIANTE_INACTIVO', $examen->ambiente);
            $this->registrarIntentoIngreso($estudiante->id_estudiante, $examen->id, 'Estudiante no activo', $examen->ambiente, $codigo, 'ESTUDIANTE_INACTIVO');

            return response()->json([
                'tipo' => 'inactivo',
                'mensaje' => 'El estudiante tiene estado '.strtoupper((string) $estudiante->estado).' y no puede ingresar.',
                'estudiante' => $this->serializarEstudiante($estudiante),
            ]);
        }

        if ($this->yaRegistrado($examen, $estudiante)) {
            $fechaHora = $this->fechaHoraIngreso($examen, $estudiante);
            $this->registrarVerificacion($estudiante->id_estudiante, $examen->id, 'CODIGO_UNIVERSITARIO', $codigo, 'YA_INGRESO', $examen->ambiente);

            return response()->json([
                'tipo' => 'ya_ingreso',
                'mensaje' => 'El estudiante ya ingresó',
                'fecha_hora' => $fechaHora,
                'estudiante' => $this->serializarEstudiante($estudiante),
            ]);
        }

        $habilitacion = $this->obtenerHabilitacion($estudiante, $examen);

        if (! $habilitacion || ! (bool) $habilitacion->habilitado) {
            $motivo = $habilitacion?->motivo_inhabilitacion ?? 'No tiene habilitación para este examen.';
            $this->registrarVerificacion($estudiante->id_estudiante, $examen->id, 'CODIGO_UNIVERSITARIO', $codigo, 'NO_HABILITADO', $examen->ambiente);
            $this->registrarIntentoIngreso($estudiante->id_estudiante, $examen->id, $motivo, $examen->ambiente, $codigo, 'NO_HABILITADO');

            return response()->json([
                'tipo' => 'no_habilitado',
                'mensaje' => 'NO HABILITADO',
                'motivo_inhabilitacion' => $motivo,
                'estudiante' => $this->serializarEstudiante($estudiante),
            ]);
        }

        $this->registrarVerificacion($estudiante->id_estudiante, $examen->id, 'CODIGO_UNIVERSITARIO', $codigo, 'HABILITADO', $examen->ambiente);

        return response()->json([
            'tipo' => 'habilitado',
            'codigo' => $estudiante->codigo_universitario,
            'id_habilitacion' => $this->generarIdHabilitacion($examen, $estudiante),
            'mensaje' => 'HABILITADO',
            'estudiante' => $this->serializarEstudiante($estudiante),
        ]);
    }

    public function ingreso(Request $request)
    {
        $data = $request->validate([
            'id_estudiante' => ['required', 'integer'],
            'id_examen' => ['required', 'integer', 'exists:examenes,id'],
            'codigo_universitario' => ['required', 'string', 'max:30'],
        ]);

        $examen = Examen::findOrFail($data['id_examen']);
        $estudiante = Estudiante::findOrFail($data['id_estudiante']);

        if ($this->yaRegistrado($examen, $estudiante)) {
            $fechaHora = $this->fechaHoraIngreso($examen, $estudiante);

            return response()->json([
                'mensaje' => 'El estudiante ya ingresó el '.$fechaHora,
            ], 409);
        }

        $this->registrarVerificacion($estudiante->id_estudiante, $examen->id, 'CODIGO_UNIVERSITARIO', $estudiante->codigo_universitario, 'INGRESO_REGISTRADO', $examen->ambiente);

        return response()->json([
            'mensaje' => 'Ingreso registrado correctamente.',
            'fecha_hora' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function obtenerHabilitacion(Estudiante $estudiante, Examen $examen): ?object
    {
        if (Schema::hasTable('habilitacion')) {
            $habilitacion = DB::table('habilitacion')
                ->where('id_estudiante', $estudiante->id_estudiante)
                ->where('id_examen', $examen->id)
                ->first();

            if ($habilitacion) {
                return (object) $habilitacion;
            }
        }

        if ($examen->asignatura_id) {
            $habilitado = DB::table('estudiante_asignatura')
                ->where('id_estudiante', $estudiante->id_estudiante)
                ->where('id_asignatura', $examen->asignatura_id)
                ->exists();

            return (object) [
                'habilitado' => $habilitado,
                'motivo_inhabilitacion' => $habilitado ? null : 'No tiene habilitación para este examen.',
            ];
        }

        if ($examen->materia_id) {
            $habilitado = DB::table('asignaciones')
                ->where('estudiante_id', $estudiante->id_estudiante)
                ->where('materia_id', $examen->materia_id)
                ->exists();

            return (object) [
                'habilitado' => $habilitado,
                'motivo_inhabilitacion' => $habilitado ? null : 'No tiene habilitación para este examen.',
            ];
        }

        return (object) [
            'habilitado' => false,
            'motivo_inhabilitacion' => 'No tiene habilitación para este examen.',
        ];
    }

    private function yaRegistrado(Examen $examen, Estudiante $estudiante): bool
    {
        if (Schema::hasTable('verificacion')) {
            $registro = DB::table('verificacion')
                ->where('id_examen', $examen->id)
                ->where('id_estudiante', $estudiante->id_estudiante)
                ->where('resultado', 'INGRESO_REGISTRADO')
                ->first();

            if ($registro) {
                return true;
            }
        }

        if (! Schema::hasTable('ingresos_examen')) {
            return false;
        }

        return DB::table('ingresos_examen')
            ->where('id_examen', $examen->id)
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->exists();
    }

    private function fechaHoraIngreso(Examen $examen, Estudiante $estudiante): ?string
    {
        if (Schema::hasTable('verificacion')) {
            $registro = DB::table('verificacion')
                ->where('id_examen', $examen->id)
                ->where('id_estudiante', $estudiante->id_estudiante)
                ->where('resultado', 'INGRESO_REGISTRADO')
                ->orderByDesc('fecha_hora')
                ->first();

            if ($registro) {
                return $registro->fecha_hora;
            }
        }

        if (! Schema::hasTable('ingresos_examen')) {
            return now()->format('Y-m-d H:i:s');
        }

        return (string) DB::table('ingresos_examen')
            ->where('id_examen', $examen->id)
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->value('fecha_hora');
    }

    private function generarIdHabilitacion(Examen $examen, Estudiante $estudiante): int
    {
        return (int) (($examen->id * 100000) + $estudiante->id_estudiante);
    }

    private function serializarEstudiante(Estudiante $estudiante): array
    {
        return [
            'id' => $estudiante->id_estudiante,
            'codigo' => $estudiante->codigo_universitario,
            'nombres' => $estudiante->nombres,
            'apellidos' => $estudiante->apellidos,
            'documento_identidad' => $estudiante->documento_identidad,
            'carnet' => $estudiante->documento_identidad . ($estudiante->ci_complemento ? '-'.$estudiante->ci_complemento : ''),
            'carrera' => $estudiante->carrera ?? '—',
            'estado' => $estudiante->estado ?? 'ACTIVO',
            'foto' => null,
        ];
    }

    private function registrarVerificacion(?int $idEstudiante, int $idExamen, string $tipo, string $datoVerificado, string $resultado, ?string $ambiente = null): void
    {
        if (! Schema::hasTable('verificacion')) {
            return;
        }

        $registro = [
            'id_estudiante' => $idEstudiante,
            'id_examen' => $idExamen,
            'tipo' => $tipo,
            'dato_verificado' => $datoVerificado,
            'resultado' => $resultado,
            'fecha_hora' => now(),
        ];

        if (Schema::hasColumn('verificacion', 'ambiente')) {
            $registro['ambiente'] = $ambiente;
        }
        if (Schema::hasColumn('verificacion', 'created_at')) {
            $registro['created_at'] = now();
        }
        if (Schema::hasColumn('verificacion', 'updated_at')) {
            $registro['updated_at'] = now();
        }

        DB::table('verificacion')->insert($registro);
    }

    private function registrarIntentoIngreso(?int $idEstudiante, int $idExamen, string $motivo, ?string $ambiente, string $codigo, string $resultado): void
    {
        if (! Schema::hasTable('intento_ingreso')) {
            return;
        }

        $registro = [
            'id_estudiante' => $idEstudiante,
            'id_examen' => $idExamen,
            'motivo' => $motivo,
            'resultado' => $resultado,
            'fecha_hora' => now(),
        ];

        if (Schema::hasColumn('intento_ingreso', 'tipo')) {
            $registro['tipo'] = 'CODIGO_UNIVERSITARIO';
        }
        if (Schema::hasColumn('intento_ingreso', 'dato_verificado')) {
            $registro['dato_verificado'] = $codigo;
        }
        if (Schema::hasColumn('intento_ingreso', 'ambiente')) {
            $registro['ambiente'] = $ambiente;
        }
        if (Schema::hasColumn('intento_ingreso', 'created_at')) {
            $registro['created_at'] = now();
        }
        if (Schema::hasColumn('intento_ingreso', 'updated_at')) {
            $registro['updated_at'] = now();
        }

        DB::table('intento_ingreso')->insert($registro);
    }
}
