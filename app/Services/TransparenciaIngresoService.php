<?php

namespace App\Services;

use App\Models\Ambiente;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\TransparenciaIngreso;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Servicio de transparencia de ingreso (RQ8).
 *
 * Se llama UNA vez, en el momento en que el controlador autoriza el ingreso de un estudiante
 * (después de verificarlo por código universitario, CI o QR). Captura automáticamente:
 *   - la marca de tiempo del SERVIDOR (nunca la del navegador),
 *   - los metadatos de acceso (IP y navegador),
 *   - y una instantánea legible del estudiante, examen, ambiente y controlador.
 *
 * Uso desde cualquier controlador:
 *
 *   app(TransparenciaIngresoService::class)->registrar($estudiante, $examen, $request->user(), 'QR', $request);
 */
class TransparenciaIngresoService
{
    /**
     * @param  string  $metodo    CODIGO_UNIV | CI | QR
     * @param  Request|null  $request   Para capturar IP y navegador (opcional).
     * @param  Ambiente|null  $ambiente  Aula donde se controla el ingreso; si no se indica, se usa la del examen.
     */
    public function registrar(
        Estudiante $estudiante,
        Examen $examen,
        User $controlador,
        string $metodo,
        Request $request = null,
        Ambiente $ambiente = null
    ): TransparenciaIngreso {
        $metodo = strtoupper(trim($metodo));

        if (! array_key_exists($metodo, TransparenciaIngreso::METODOS)) {
            throw new InvalidArgumentException(
                'Método de verificación no válido: '.$metodo.' (use CODIGO_UNIV, CI o QR).'
            );
        }

        return DB::transaction(function () use ($estudiante, $examen, $controlador, $metodo, $request, $ambiente) {
            // Todos los accesos de ingreso toman el mismo bloqueo antes de comprobar y registrar.
            $estudiante = Estudiante::whereKey($estudiante->getKey())->lockForUpdate()->firstOrFail();
            if (strtoupper((string) $estudiante->estado) !== 'ACTIVO') {
                throw ValidationException::withMessages([
                    'estudiante_id' => 'El estudiante no se encuentra activo para rendir el examen.',
                ]);
            }
            $habilitacion = Habilitacion::where('estudiante_id', $estudiante->getKey())
                ->where('examen_id', $examen->id)->lockForUpdate()->first();
            if (! $habilitacion || $habilitacion->estado !== 'habilitado') {
                throw ValidationException::withMessages([
                    'estudiante_id' => 'El estudiante no está habilitado para este examen.',
                ]);
            }
            if (TransparenciaIngreso::where('estudiante_id', $estudiante->getKey())
                ->where('examen_id', $examen->id)->exists()) {
                throw new HttpResponseException(response()->json([
                    'mensaje' => 'El estudiante ya registró ingreso para este examen.',
                ], 409));
            }

            $ambienteNombre = $ambiente?->nombre ?? (string) $examen->ambiente;
            $ambienteId = $ambiente?->id ?? Ambiente::where('nombre', $ambienteNombre)->value('id');

            return TransparenciaIngreso::create([
                'estudiante_id' => $estudiante->getKey(),
                'examen_id' => $examen->getKey(),
                'ambiente_id' => $ambienteId,
                'controlador_id' => $controlador->getKey(),
                'metodo_verificacion' => $metodo,

                // Hora oficial: reloj del servidor.
                'fecha_hora_ingreso' => now(),

                // Instantánea legible.
                'estudiante_codigo' => Str::limit((string) $estudiante->codigo_universitario, 20, ''),
                'estudiante_nombre' => Str::limit($estudiante->nombre_completo, 200, ''),
                'examen_descripcion' => Str::limit($this->describirExamen($examen), 255, ''),
                'ambiente_nombre' => Str::limit($ambienteNombre, 100, ''),
                'controlador_nombre' => Str::limit((string) $controlador->name, 150, ''),

                // Metadatos de acceso.
                'ip_origen' => $request?->ip(),
                'user_agent' => $request ? (Str::limit((string) $request->userAgent(), 255, '') ?: null) : null,
            ]);
        });
    }

    /** "Cálculo Multivariable · Ingeniería de Sistemas · 10/10/2026 08:00" */
    private function describirExamen(Examen $examen): string
    {
        $nombre = $examen->asignatura?->nombre
            ?? $examen->materia?->nombre
            ?? 'Examen #'.$examen->getKey();

        $cuando = $examen->fecha ? $examen->fecha->format('d/m/Y').' '.$examen->hora : null;

        return collect([$nombre, $examen->carrera, $cuando])->filter()->implode(' · ');
    }
}
