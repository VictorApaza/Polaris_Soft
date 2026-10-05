<?php

namespace Database\Seeders;

use App\Models\Ambiente;
use Illuminate\Database\Seeder;

class AmbienteSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Edificio Central 102A', 120],
            ['Auditorio Central', 200],
            ['Lab. Cómputo 1 y 2', 60],
            ['Pabellón B - Aula 304', 80],
            ['Edificio Sur 201', 90],
            ['Lab. Cómputo 3', 40],
        ] as [$nombre, $capacidad]) {
            Ambiente::firstOrCreate(
                ['nombre' => $nombre],
                ['capacidad' => $capacidad]
            );
        }
    }
}
