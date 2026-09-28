<?php

namespace Database\Factories;

use App\Models\Materia;
use Illuminate\Database\Eloquent\Factories\Factory;

class MateriaFactory extends Factory
{
    protected $model = Materia::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement([
                'Programación I',
                'Programación II',
                'Bases de Datos',
                'Ingeniería de Software',
                'Sistemas Operativos',
                'Redes de Computadoras',
                'Arquitectura de Computadores',
            ]),
            'sigla' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}