<?php

namespace Database\Seeders;

use App\Models\Asignacion;
use App\Models\Ambiente;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\MotivoInhabilitacion;
use App\Models\User;
use App\Support\Catalogo;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos de ejemplo para desarrollo (php artisan migrate:fresh --seed).
     * Contraseña de todos los usuarios de prueba: password123
     */
    public function run(): void
    {
        $this->call(AmbienteSeeder::class);

        // ---- Catálogo de causas de inhabilitación (RQ4) ----
        foreach ([
            'Deuda económica pendiente',
            'Documentación incompleta',
            'Suspensión disciplinaria',
            'Requisito académico no cumplido',
            'Otro',
        ] as $descripcion) {
            MotivoInhabilitacion::firstOrCreate(['descripcion' => $descripcion]);
        }

        // ---- Usuarios (uno por rol + un inactivo) ----
        foreach ([
            ['Carlos', 'Choque', 'Licenciado(a)', 'carlos.choque@sciem.edu', 'administrador', 'activo'],
            ['María', 'Ramos', 'Doctor(a)', 'maria.ramos@sciem.edu', 'docente', 'activo'],
            ['Jorge', 'Torrez', 'Ingeniero(a)', 'jorge.torrez@sciem.edu', 'control', 'activo'],
            ['Andrea', 'Paredes', 'Licenciado(a)', 'andrea.paredes@sciem.edu', 'docente', 'inactivo'],
        ] as [$nombre, $apellido, $grado, $email, $rol, $estado]) {
            User::updateOrCreate(['email' => $email], [
                'name' => "{$nombre} {$apellido}", 'nombre' => $nombre, 'apellido' => $apellido, 'grado' => $grado,
                'rol' => $rol, 'estado' => $estado, 'password' => 'password123',
            ]);
        }

        // ---- Docentes ----
        $docentes = collect([
            ['María', 'Ramos', 'Doctor(a)', '4521367'], ['Luis', 'Fernández', 'Ingeniero(a)', '5123890'],
            ['Sonia', 'Vargas', 'Licenciado(a)', '6034512'], ['Pablo', 'Mendoza', 'Ingeniero(a)', '4876123'],
            ['Ricardo', 'Salinas', 'Doctor(a)', '5567431'], ['Andrea', 'Paredes', 'Licenciado(a)', '6789012'],
        ])->map(fn ($d) => Docente::firstOrCreate(
            ['ci' => $d[3]],
            ['nombre' => $d[0], 'apellido' => $d[1], 'grado' => $d[2]]
        ));

        // ---- Materias + un grupo por materia ----
        $materias = collect([
            ['Cálculo Multivariable', 'MAT-201', 'Ingeniería de Sistemas'],
            ['Física General I', 'FIS-101', 'Ingeniería Civil'],
            ['Estructura de Datos', 'INF-210', 'Ingeniería de Sistemas'],
            ['Química Orgánica', 'QUI-220', 'Ingeniería Química'],
            ['Álgebra Lineal', 'MAT-102', 'Ingeniería Industrial'],
            ['Base de Datos I', 'INF-240', 'Ingeniería de Sistemas'],
        ])->map(function ($m, $i) use ($docentes) {
            $materia = Materia::updateOrCreate(['sigla' => $m[1]], ['nombre' => $m[0], 'carrera' => $m[2]]);
            Grupo::firstOrCreate(
                ['materia_id' => $materia->id, 'nombre' => 'Grupo A'],
                ['docente_id' => $docentes[$i % $docentes->count()]->id]
            );
            Grupo::firstOrCreate(
                ['materia_id' => $materia->id, 'nombre' => 'Grupo B'],
                ['docente_id' => $docentes[($i + 1) % $docentes->count()]->id]
            );
            $materia->carrera_demo = $m[2];

            return $materia;
        });

        // ---- Estudiantes ----
        $carreras = ['Ingeniería de Sistemas', 'Ingeniería Civil', 'Ingeniería Industrial', 'Ingeniería Química', 'Ingeniería Electrónica'];
        $nombres = [
            ['Gabriel Fernando', 'Romero Silva'], ['Salomé', 'Vargas Quispe'], ['Rodrigo', 'Mamani Choque'],
            ['Carolina', 'Flores Ticona'], ['Marco Antonio', 'Ortuño Condori'], ['Valeria', 'Gutiérrez Rojas'],
            ['Diego Alejandro', 'Cussi Nina'], ['Daniela', 'Arce Vásquez'], ['Sebastián', 'Limachi Poma'],
            ['Andrea Paola', 'Choque Mendoza'], ['Luis Fernando', 'Terrazas Peña'], ['Camila', 'Zambrana Rivero'],
        ];
        foreach ($nombres as $i => [$nom, $ape]) {
            // Código universitario: 9 dígitos, empieza con el año (2024 + 5 dígitos correlativos).
            $codigo = '2024'.sprintf('%05d', 34 + $i * 7);
            $carrera = $carreras[$i % count($carreras)];
            $est = Estudiante::firstOrCreate(['codigo_universitario' => $codigo], [
                'facultad' => Catalogo::facultadDeCarrera($carrera) ?? array_key_first(Catalogo::FACULTADES),
                'documento_identidad' => (string) (71000 + $i * 1379), // 5-10 dígitos
                'nombres' => $nom,
                'apellidos' => $ape,
                'carrera' => $carrera,
                'correo_institucional' => $codigo.'@universidad.edu',
                'estado' => $i === 4 ? 'OBSERVADO' : ($i === 9 ? 'INACTIVO' : 'ACTIVO'),
            ]);

            // Cada estudiante queda asignado a 2 materias (alimenta "Capacidad / Asign.")
            foreach ([$i % 6, ($i + 2) % 6] as $k) {
                $materia = $materias[$k];
                // Reparte a los estudiantes entre Grupo A y Grupo B de cada materia.
                $grupo = $materia->grupos()->orderBy('nombre')->get()[intdiv($i, 2) % 2];
                Asignacion::firstOrCreate(
                    ['estudiante_id' => $est->getKey(), 'materia_id' => $materia->id],
                    ['grupo_id' => $grupo->id, 'docente_id' => $grupo->docente_id]
                );
            }
        }

        // ---- Exámenes (fechas relativas a hoy para que el dashboard siempre tenga próximos) ----
        if (Examen::count() === 0) {
            $normasAdm = 'Presentar CI físico original y Credencial Universitaria vigente 15 minutos antes de la hora señalada.';
            $normasSal = 'Se permite el uso de calculadora científica no programable. Prohibido el uso de celulares.';

            foreach ([
                [0, 5, '08:00', 120, 'Edificio Central 102A', 120, 'abierto'],
                [1, 9, '10:30', 90, 'Auditorio Central', 200, 'programado'],
                [2, 12, '14:00', 90, 'Lab. Cómputo 1 y 2', 60, 'programado'],
                [3, 16, '08:00', 90, 'Pabellón B - Aula 304', 80, 'programado'],
                [4, 20, '09:00', 90, 'Edificio Sur 201', 90, 'programado'],
                [5, -12, '15:00', 90, 'Lab. Cómputo 3', 40, 'finalizado'],
            ] as [$m, $dias, $hora, $dur, $amb, $cap, $estado]) {
                Examen::create([
                    'materia_id' => $materias[$m]->id,
                    'carrera' => $materias[$m]->carrera_demo,
                    'fecha' => now()->addDays($dias)->toDateString(),
                    'hora_inicio' => $hora,
                    'duracion_min' => $dur,
                    'ambiente' => $amb,
                    'capacidad' => $cap,
                    'estado' => $estado,
                    'normas_admision' => $normasAdm,
                    'normas_salida' => $normasSal,
                ]);
            }
        }
    }
}
