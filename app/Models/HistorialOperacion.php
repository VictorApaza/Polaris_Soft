<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class HistorialOperacion extends Model
{
    protected $table = 'historial_operaciones';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'usuario_nombre',
        'usuario_rol',
        'ip',
        'accion',
        'detalle',
        'examen_id',
        'examen_nombre',
        'registros_afectados',
        'estado',
        'error',
        'fecha_hora',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'registros_afectados' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de operaciones es inmutable.'));
        static::deleting(fn () => throw new LogicException('El historial de operaciones es inmutable.'));
    }
}
