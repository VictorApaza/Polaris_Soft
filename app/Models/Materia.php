<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    protected $fillable = ['nombre', 'sigla', 'carrera'];

    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class);
    }

    public function examenes()
    {
        return $this->hasMany(Examen::class);
    }
}
