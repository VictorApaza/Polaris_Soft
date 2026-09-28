<?php

namespace Database\Factories;

use App\Models\Asignacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use Illuminate\Database\Eloquent\Factories\Factory;

class AsignacionFactory extends Factory
{
    protected $model = Asignacion::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'materia_id' => Materia::factory(),
            'grupo_id' => Grupo::factory(),
            'docente_id' => Docente::factory(),
        ];
    }
}