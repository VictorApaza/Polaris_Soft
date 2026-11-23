<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Norma particular de un estudiante en un examen (además de las normas generales del examen).
 * Una sola por estudiante y examen (índice único).
 */
class NormaParticular extends Model
{
    protected $table = 'norma_particular';

    protected $fillable = ['examen_id', 'estudiante_id', 'norma', 'registrado_por'];

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id', 'id_estudiante');
    }

    public function examen()
    {
        return $this->belongsTo(Examen::class, 'examen_id');
    }

    public function registrador()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Texto de la norma de ese estudiante en ese examen, o null. Se consulta en cada verificación de ingreso, así
     * que nunca debe romperla: si la tabla todavía no está migrada, devuelve null.
     */
    public static function textoDe(int $estudianteId, int $examenId): ?string
    {
        try {
            return static::where('examen_id', $examenId)->where('estudiante_id', $estudianteId)->value('norma');
        } catch (Throwable) {
            return null;
        }
    }
}
