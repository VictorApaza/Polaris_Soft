<?php

namespace Database\Seeders;

use App\Models\Ambiente;
use Illuminate\Database\Seeder;

class AmbienteSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Edificio Central 102A', 'Planta baja, Bloque Central', 120],
            ['Auditorio Central', null, 200],
            ['Lab. Cómputo 1 y 2', null, 60],
            ['Pabellón B - Aula 304', null, 80],
            ['Edificio Sur 201', null, 90],
            ['Lab. Cómputo 3', null, 40],
        ] as [$nombre, $ubicacion, $capacidad]) {
            $ambiente = Ambiente::firstOrCreate(
                ['nombre' => $nombre],
                ['ubicacion' => $ubicacion, 'capacidad' => $capacidad]
            );
            if ($ubicacion && ! $ambiente->ubicacion) {
                $ambiente->update(['ubicacion' => $ubicacion]);
            }
        }
    }
}
