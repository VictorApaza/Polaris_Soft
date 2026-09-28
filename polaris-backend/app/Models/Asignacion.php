<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asignacion extends Model
{

        protected $table = 'asignacion';
    protected $fillable = ['estudiante_id', 'materia_id', 'grupo_id', 'docente_id'];

public function estudiante()
{
    return $this->belongsTo(
        Estudiante::class,
        'estudiante_id',
        'id_estudiante'
    );
}

    public function materia()
    {
        return $this->belongsTo(Materia::class);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function docente()
    {
        return $this->belongsTo(Docente::class);
    }
}
