<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Registro de transparencia de ingreso (RQ8): una fila por cada ingreso AUTORIZADO.
 * Es una bitácora de solo lectura: una vez creado, un registro no se puede modificar ni eliminar.
 */
class TransparenciaIngreso extends Model
{
    protected $table = 'transparencia_ingreso';

    /** No hay created_at/updated_at: la marca de tiempo oficial es fecha_hora_ingreso (hora del servidor). */
    public $timestamps = false;

    public const METODOS = [
        'CODIGO_UNIV' => 'Código universitario',
        'CI' => 'Cédula de identidad',
        'QR' => 'Código QR',
    ];

    protected $fillable = [
        'estudiante_id', 'examen_id', 'ambiente_id', 'controlador_id',
        'metodo_verificacion', 'fecha_hora_ingreso',
        'estudiante_codigo', 'estudiante_nombre', 'examen_descripcion', 'ambiente_nombre', 'controlador_nombre',
        'ip_origen', 'user_agent',
    ];

    protected $casts = ['fecha_hora_ingreso' => 'datetime'];

    /**
     * Inalterabilidad a nivel de aplicación. (En MySQL, además, hay triggers que bloquean
     * los UPDATE/DELETE hechos directamente en la base de datos.)
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Los registros de transparencia de ingreso son inmutables: no se pueden modificar.');
        });

        static::deleting(function () {
            throw new LogicException('Los registros de transparencia de ingreso son inmutables: no se pueden eliminar.');
        });
    }

    // ------------------------------------------------------------------
    //  Relaciones (los datos legibles ya están en las columnas "instantánea")
    // ------------------------------------------------------------------

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

    // ------------------------------------------------------------------
    //  Presentación
    // ------------------------------------------------------------------

    /**
     * Zona horaria con la que se MUESTRAN las horas. La base guarda la hora en la zona de la app
     * (config/app.php); como hoy está en UTC, se muestra en hora de Bolivia. Si el equipo cambia
     * 'timezone' a America/La_Paz, se usará esa directamente.
     */
    public static function zonaHoraria(): string
    {
        $zona = config('app.timezone');

        return $zona === 'UTC' ? 'America/La_Paz' : $zona;
    }

    /** "10/10/2026 14:35:07" en hora local. */
    public function getFechaHoraLocalAttribute(): string
    {
        return $this->fecha_hora_ingreso->copy()->timezone(self::zonaHoraria())->format('d/m/Y H:i:s');
    }

    public function getMetodoEtiquetaAttribute(): string
    {
        return self::METODOS[$this->metodo_verificacion] ?? $this->metodo_verificacion;
    }

    // ------------------------------------------------------------------
    //  Filtros de la vista de auditoría
    // ------------------------------------------------------------------

    /**
     * Filtros admitidos: examen_id, ambiente (nombre exacto), metodo y estudiante (código o nombre).
     */
    public function scopeFiltrar(Builder $consulta, array $filtros): Builder
    {
        return $consulta
            ->when(! empty($filtros['examen_id']), fn ($q) => $q->where('examen_id', (int) $filtros['examen_id']))
            ->when(! empty($filtros['ambiente']), fn ($q) => $q->where('ambiente_nombre', $filtros['ambiente']))
            ->when(! empty($filtros['metodo']), fn ($q) => $q->where('metodo_verificacion', $filtros['metodo']))
            ->when(! empty($filtros['estudiante']), function ($q) use ($filtros) {
                $texto = trim($filtros['estudiante']);
                $q->where(function ($w) use ($texto) {
                    $w->where('estudiante_codigo', 'like', "%{$texto}%")
                      ->orWhere('estudiante_nombre', 'like', "%{$texto}%");
                });
            });
    }
}
