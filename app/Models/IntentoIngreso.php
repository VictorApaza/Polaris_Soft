<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Intento fallido de ingreso a un examen (no registrado, no habilitado, inactivo, QR inválido…). */
class IntentoIngreso extends Model
{
    protected $table = 'intento_ingreso';

    public $timestamps = false;

    protected $fillable = [
        'estudiante_id', 'examen_id', 'ambiente_id', 'controlador_id', 'tipo', 'dato_verificado', 'resultado', 'motivo', 'fecha_hora',
    ];

    protected $casts = ['fecha_hora' => 'datetime'];
}
