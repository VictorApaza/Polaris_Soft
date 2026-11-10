<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * RQ20: expulsión de un estudiante de un examen (motivo, hora y quién la registró).
 *
 * - La fecha y hora la fija SIEMPRE el servidor al crear el registro (zona America/La_Paz); `fecha_hora` no es
 *   asignable en masa, así que cualquier hora enviada desde el navegador se descarta.
 * - Una sola expulsión por estudiante y examen (índice único).
 */
class Expulsion extends Model
{
    protected $table = 'expulsion';

    /** No hay created_at/updated_at: la marca de tiempo oficial es fecha_hora. */
    public $timestamps = false;

    /** fecha_hora NO está aquí a propósito: nadie puede asignarla desde fuera. */
    protected $fillable = ['examen_id', 'estudiante_id', 'ambiente_id', 'motivo', 'registrado_por'];

    protected $casts = ['fecha_hora' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (Expulsion $expulsion) {
            $expulsion->fecha_hora = now()->startOfSecond();
        });
    }

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

    /** "23/10/2026 17:58:12" (dd/mm/aaaa HH:mm:ss) en hora de Bolivia, igual que `ingreso`. */
    public function getFechaHoraLocalAttribute(): string
    {
        return $this->fecha_hora->copy()->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s');
    }

    /**
     * Expulsión de ese estudiante en ese examen, o null. Se consulta en cada verificación de ingreso, así que
     * nunca debe romperla: si la tabla todavía no está migrada, devuelve null.
     */
    public static function de(int $estudianteId, int $examenId): ?self
    {
        try {
            return static::where('estudiante_id', $estudianteId)->where('examen_id', $examenId)->first();
        } catch (Throwable $e) {
            return null;
        }
    }
}
