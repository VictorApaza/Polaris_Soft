<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $table = 'estudiante';

    protected $primaryKey = 'id_estudiante';

    protected $fillable = [
        'codigo_universitario',
        'documento_identidad',
        'nombres',
        'apellidos',
        'carrera',
        'correo_institucional',
        'estado',
    ];

    public function usuario()
    {
        return $this->hasOne(
            User::class,
            'estudiante_id',
            'id_estudiante'
        );
    }
}