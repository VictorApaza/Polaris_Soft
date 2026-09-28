<?php

namespace Database\Factories;

use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use Illuminate\Database\Eloquent\Factories\Factory;

class GrupoFactory extends Factory
{
    protected $model = Grupo::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Grupo ' . fake()->randomElement(['A', 'B', 'C', 'D']),
            'materia_id' => Materia::factory(),
            'docente_id' => Docente::factory(),
        ];
    }
}