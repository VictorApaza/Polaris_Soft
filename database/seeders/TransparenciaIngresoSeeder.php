<?php

namespace Database\Seeders;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\TransparenciaIngreso;
use App\Models\User;
use App\Services\TransparenciaIngresoService;
use Illuminate\Database\Seeder;

/**
 * SOLO PARA DESARROLLO / DEMOSTRACIÓN (RQ8).
 * Crea unas pocas autorizaciones de ejemplo para ver la vista /transparencia con datos.
 *
 *   php artisan db:seed --class=TransparenciaIngresoSeeder
 *
 * Requiere haber corrido antes:  php artisan migrate:fresh --seed
 * No está incluido en DatabaseSeeder a propósito: la bitácora real solo la llena el sistema al autorizar ingresos.
 */
class TransparenciaIngresoSeeder extends Seeder
{
    public function run(): void
    {
        $controlador = User::where('rol', 'control')->first();
        $examen = Examen::orderBy('fecha')->first();
        $estudiantes = Estudiante::where('estado', 'ACTIVO')->orderBy('id_estudiante')->limit(6)->get();

        if (! $controlador || ! $examen || $estudiantes->isEmpty()) {
            $this->command?->warn('Faltan datos base (usuario de control, examen o estudiantes). Corre primero: php artisan migrate:fresh --seed');

            return;
        }

        $servicio = app(TransparenciaIngresoService::class);
        $metodos = array_keys(TransparenciaIngreso::METODOS);

        foreach ($estudiantes as $i => $estudiante) {
            $servicio->registrar($estudiante, $examen, $controlador, $metodos[$i % count($metodos)]);
        }

        $this->command?->info($estudiantes->count().' autorizaciones de ejemplo registradas.');
    }
}
