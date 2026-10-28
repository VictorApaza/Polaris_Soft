<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\IntentoIngreso;

/**
 * RQ12 (Sprint 3): estudiante que intenta ingresar a un examen que no le corresponde.
 *
 * Un examen "no le corresponde" al estudiante cuando:
 *   1. no tiene ninguna fila en `habilitacion` para ese examen (ni habilitado ni inhabilitado), y
 *   2. no es candidato del examen, es decir, no está inscrito en la asignatura / materia del examen
 *      (mismo criterio que usa Habilitacion::candidatosIds()).
 *
 * Si el estudiante sí es candidato pero nadie lo habilitó, sigue siendo el caso de RQ9
 * ("no tiene habilitación registrada para este examen"): este servicio NO lo toma.
 *
 * Esta clase solo consulta; el registro del intento lo hace VerificacionController en `intento_ingreso`.
 */
class ExamenNoCorrespondeService
{
    /** Valor que se guarda en intento_ingreso.motivo y verificacion.resultado (RQ19 filtrará por él). */
    public const MOTIVO = 'EXAMEN_NO_CORRESPONDE';

    public const MENSAJE = 'Este examen no le corresponde al estudiante: no está registrado en la materia del examen.';

    /** ¿El examen no le corresponde a este estudiante? */
    public function noLeCorresponde(Examen $examen, Estudiante $estudiante): bool
    {
        // Examen sin asignatura ni materia: no hay forma de saber a quién le corresponde.
        // Se conserva el comportamiento de RQ9 para no rechazar por error.
        if (! $examen->asignatura_id && ! $examen->materia_id) {
            return false;
        }

        $estudianteId = (int) $estudiante->getKey();

        $tieneHabilitacion = Habilitacion::where('estudiante_id', $estudianteId)
            ->where('examen_id', $examen->id)
            ->exists();

        if ($tieneHabilitacion) {
            return false;
        }

        return empty(Habilitacion::idsAsignados($examen, [$estudianteId]));
    }

    /**
     * Datos para la alerta del personal de control: a qué examen(es) SÍ le corresponde entrar al estudiante
     * y cuántas veces ya intentó entrar a este examen. Llamar DESPUÉS de registrar el intento actual.
     */
    public function datosDeAlerta(Examen $examen, Estudiante $estudiante): array
    {
        return [
            'numero_intento' => $this->intentosRegistrados($examen, $estudiante),
            'examenes_asignados' => $this->examenesAsignados($estudiante),
        ];
    }

    /** Intentos de este estudiante a este examen guardados con el motivo EXAMEN_NO_CORRESPONDE. */
    public function intentosRegistrados(Examen $examen, Estudiante $estudiante): int
    {
        return IntentoIngreso::where('estudiante_id', $estudiante->getKey())
            ->where('examen_id', $examen->id)
            ->where('motivo', self::MOTIVO)
            ->count();
    }

    /** Próximos exámenes (no finalizados) para los que el estudiante está habilitado, el más cercano primero. */
    public function examenesAsignados(Estudiante $estudiante, int $limite = 3): array
    {
        return Habilitacion::with(['examen.asignatura', 'examen.materia'])
            ->where('estudiante_id', $estudiante->getKey())
            ->where('estado', 'habilitado')
            ->get()
            ->pluck('examen')
            ->filter(fn ($x) => $x && $x->estado !== 'finalizado' && $x->fin->isFuture())
            ->sortBy(fn ($x) => $x->inicio->timestamp)
            ->take($limite)
            ->map(fn ($x) => [
                'id' => $x->id,
                'nombre' => $x->nombre,
                'fecha_local' => $x->fecha->format('d/m/Y'),
                'hora' => $x->hora,
                'ambiente' => (string) $x->ambiente,
            ])
            ->values()
            ->all();
    }
}
