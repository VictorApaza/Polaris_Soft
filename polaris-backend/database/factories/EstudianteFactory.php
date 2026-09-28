<?php

namespace Database\Factories;

use App\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

class EstudianteFactory extends Factory
{
    protected $model = Estudiante::class;

    public function definition(): array
    {
        return [
            'codigo_universitario' => fake()->unique()->numerify('########'),
            'documento_identidad' => fake()->unique()->numerify('########'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName() . ' ' . fake()->lastName(),
            'carrera' => fake()->randomElement([
                'Ingeniería Informática',
                'Ingeniería de Sistemas',
                'Ingeniería Industrial',
            ]),
            'correo_institucional' => fake()->unique()->safeEmail(),
            'estado' => 'activo',
        ];
    }
}