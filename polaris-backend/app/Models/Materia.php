<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Examen;

class Materia extends Model
{
    protected $fillable = ['nombre', 'sigla'];

    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }
    public function examenes()
{
    return $this->hasMany(Examen::class, 'materia_id');
}
}
