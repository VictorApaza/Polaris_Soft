<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Ingreso autorizado de un estudiante a un examen: una fila por estudiante y examen.
 *
 * - La fecha y hora la fija SIEMPRE el servidor al crear el registro (reloj del servidor, zona de
 *   config/app.php = America/La_Paz). `fecha_hora_ingreso` no es asignable en masa, y si alguien intenta
 *   mandarla (por ejemplo desde el navegador) se descarta.
 * - Es inmutable: una vez creado no se puede modificar ni eliminar (en MySQL, además, lo impiden triggers).
 *
 * Los datos legibles del momento (nombres, IP, navegador) viven en `transparencia_ingreso` (RQ8);
 * ambas filas se guardan juntas y con la misma hora (App\Services\IngresoService).
 */
class Ingreso extends Model
{
    protected $table = 'ingreso';

    /** No hay created_at/updated_at: la marca de tiempo oficial es fecha_hora_ingreso. */
    public $timestamps = false;

    /** fecha_hora_ingreso NO está aquí a propósito: nadie puede asignarla desde fuera. */
    protected $fillable = [
        'estudiante_id', 'examen_id', 'ambiente_id', 'controlador_id', 'metodo_verificacion',
    ];

    protected $casts = ['fecha_hora_ingreso' => 'datetime'];

    protected static function booted(): void
    {
        // Hora del servidor, sin segundos fraccionarios para que lo guardado y lo devuelto coincidan exactamente.
        static::creating(function (Ingreso $ingreso) {
            $ingreso->fecha_hora_ingreso = now()->startOfSecond();
        });

        static::updating(function () {
            throw new LogicException('Los ingresos registrados son inmutables: no se pueden modificar.');
        });

        static::deleting(function () {
            throw new LogicException('Los ingresos registrados son inmutables: no se pueden eliminar.');
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

    public function controlador()
    {
        return $this->belongsTo(User::class, 'controlador_id');
    }

    /** "23/10/2026 17:58:12" (dd/mm/aaaa HH:mm:ss) en hora de Bolivia; mismo formato que transparencia_ingreso. */
    public function getFechaHoraLocalAttribute(): string
    {
        return $this->fecha_hora_ingreso->copy()->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s');
    }
}
