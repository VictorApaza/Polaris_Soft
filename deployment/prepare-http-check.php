<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.connections.mysql.host') !== 'db'
    || config('database.connections.mysql.database') !== 'polaris_demo') {
    throw new RuntimeException('Solo se admite la base local de demostración.');
}

$materia = App\Models\Materia::firstOrFail();
$estudiante = App\Models\Estudiante::firstOrCreate(['codigo_universitario' => '202600999'], [
    'documento_identidad' => '9900999', 'nombres' => 'Prueba', 'apellidos' => 'Simultanea',
    'carrera' => $materia->carrera, 'estado' => 'ACTIVO',
]);
$examen = App\Models\Examen::create([
    'materia_id' => $materia->id, 'carrera' => $materia->carrera,
    'fecha' => now()->addDays(3)->toDateString(), 'hora_inicio' => '17:30',
    'duracion_min' => 90, 'ambiente' => 'Aula de prueba simultanea', 'capacidad' => 30, 'estado' => 'abierto',
]);
App\Models\Habilitacion::create([
    'estudiante_id' => $estudiante->getKey(), 'examen_id' => $examen->id, 'estado' => 'habilitado',
]);
echo json_encode(['student' => $estudiante->getKey(), 'exam' => $examen->id]).PHP_EOL;
