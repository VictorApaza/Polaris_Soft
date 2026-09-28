<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asignacion extends Model
{
    protected $fillable = ['estudiante_id', 'materia_id', 'grupo_id', 'docente_id'];

    public function estudiante()
    {
        // Ajusta el nombre de la clase si tu modelo de estudiante se llama distinto
        return $this->belongsTo(Estudiante::class);
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
