<?php

namespace Database\Seeders;

use App\Models\Asignacion;
use App\Models\Estudiante;
use App\Models\Materia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Llena las tablas "asignatura" y "estudiante_asignatura" (que usa el formulario de Exámenes)
 * a partir de las materias y asignaciones que ya existen. Se puede ejecutar varias veces.
 *
 *   php artisan db:seed --class=AsignaturaSeeder
 */
class AsignaturaSeeder extends Seeder
{
    public function run(): void
    {
        // Cada materia pasa a ser una asignatura (código = sigla).
        $idsPorMateria = [];
        foreach (Materia::all() as $materia) {
            $existente = DB::table('asignatura')->where('codigo', $materia->sigla)->first();

            $idsPorMateria[$materia->id] = $existente
                ? $existente->id_asignatura
                : DB::table('asignatura')->insertGetId(
                    ['codigo' => $materia->sigla, 'nombre' => $materia->nombre],
                    'id_asignatura'
                );
        }

        // Cada asignación (estudiante + materia) pasa a ser una inscripción estudiante_asignatura.
        foreach (Asignacion::all() as $asignacion) {
            $idAsignatura = $idsPorMateria[$asignacion->materia_id] ?? null;
            $estudiante = Estudiante::find($asignacion->estudiante_id);

            if (! $idAsignatura || ! $estudiante) {
                continue;
            }

            $yaExiste = DB::table('estudiante_asignatura')
                ->where('id_estudiante', $estudiante->getKey())
                ->where('id_asignatura', $idAsignatura)
                ->exists();

            if (! $yaExiste) {
                DB::table('estudiante_asignatura')->insert([
                    'id_estudiante' => $estudiante->getKey(),
                    'id_asignatura' => $idAsignatura,
                    'gestion' => '2026',
                    'estado' => 'ACTIVO',
                ]);
            }
        }
    }
}
