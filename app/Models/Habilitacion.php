<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Habilitacion extends Model
{
    protected $table = 'habilitacion';

    protected $fillable = [
        'estudiante_id', 'examen_id', 'estado', 'motivo_inhabilitacion_id', 'observacion',
    ];

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id', 'id_estudiante');
    }

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function motivo()
    {
        return $this->belongsTo(MotivoInhabilitacion::class, 'motivo_inhabilitacion_id');
    }

    /**
     * IDs de los estudiantes que pueden rendir el examen (candidatos).
     *  - Examen ligado a una asignatura (formulario nuevo): inscritos en esa asignatura, de la carrera del examen.
     *  - Examen ligado a una materia (modelo anterior): asignados a la materia; si se indica grupo, solo los de ese grupo.
     */
    public static function candidatosIds(Examen $examen, ?int $grupoId = null): array
    {
        if ($examen->asignatura_id) {
            return DB::table('estudiante_asignatura as ea')
                ->join('estudiante as e', 'e.id_estudiante', '=', 'ea.id_estudiante')
                ->where('ea.id_asignatura', $examen->asignatura_id)
                ->when($examen->carrera, fn ($q) => $q->where('e.carrera', $examen->carrera))
                ->pluck('ea.id_estudiante')->unique()->values()->all();
        }

        return Asignacion::where('materia_id', $examen->materia_id)
            ->when($grupoId, fn ($q) => $q->where('grupo_id', $grupoId))
            ->pluck('estudiante_id')->unique()->values()->all();
    }

    /** De una lista de estudiantes, cuáles son realmente candidatos de ese examen. */
    public static function idsAsignados(Examen $examen, array $ids): array
    {
        $candidatos = array_map('intval', static::candidatosIds($examen));

        return array_values(array_intersect(array_map('intval', $ids), $candidatos));
    }

    /** Candidatos del examen; si se indica grupo (solo modelo por materia), solo los de ese grupo. */
    public static function idsDelGrupo(Examen $examen, ?int $grupoId = null): array
    {
        return static::candidatosIds($examen, $grupoId);
    }

    /**
     * Habilita a varios estudiantes para un examen (carga individual o masiva).
     * - Ignora a quienes no estén asignados a la materia del examen.
     * - Con $respetarInhabilitados, no pisa a quienes ya fueron inhabilitados a propósito.
     * Devuelve ['habilitados' => [...], 'ignorados' => [...], 'respetados' => [...]].
     */
    public static function habilitar(Examen $examen, array $ids, bool $respetarInhabilitados = false): array
    {
        $validos = static::idsAsignados($examen, $ids);
        $ignorados = array_values(array_diff($ids, $validos));
        $respetados = [];

        if ($respetarInhabilitados && $validos) {
            $respetados = static::where('examen_id', $examen->id)
                ->where('estado', 'inhabilitado')
                ->whereIn('estudiante_id', $validos)
                ->pluck('estudiante_id')->all();
            $validos = array_values(array_diff($validos, $respetados));
        }

        DB::transaction(function () use ($validos, $examen) {
            foreach ($validos as $estudianteId) {
                static::updateOrCreate(
                    ['estudiante_id' => $estudianteId, 'examen_id' => $examen->id],
                    ['estado' => 'habilitado', 'motivo_inhabilitacion_id' => null, 'observacion' => null]
                );
            }
        });

        return ['habilitados' => $validos, 'ignorados' => $ignorados, 'respetados' => $respetados];
    }
}
