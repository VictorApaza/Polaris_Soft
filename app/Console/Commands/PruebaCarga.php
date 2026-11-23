<?php

namespace App\Console\Commands;

use App\Http\Controllers\ControlEnVivoController;
use App\Http\Controllers\VerificacionController;
use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Prueba de carga del registro de ingreso (requisito de contexto: «procesar un gran número de registros de ingreso
 * en periodos cortos, evitando tiempos de espera excesivos»).
 *
 * Simula la llegada de N estudiantes a un examen y, para cada uno, ejecuta exactamente lo que hace el personal de
 * control: verificar la identidad (POST /verificacion/verificar) y registrar el ingreso (POST /verificacion/ingreso).
 * Mide la latencia de cada paso, el tiempo de las consultas del Control en vivo con el examen ya lleno y comprueba
 * que un segundo ingreso del mismo estudiante siempre se rechaza.
 *
 * SEGURIDAD: todo ocurre dentro de una transacción que SIEMPRE se revierte al terminar; no queda ningún dato de
 * prueba en la base (ni siquiera con las tablas inmutables). Aun así consume recursos reales del servidor: no la
 * ejecutes en horario de exámenes. En producción exige --force.
 */
class PruebaCarga extends Command
{
    protected $signature = 'sciem:prueba-carga
        {--estudiantes=1000 : Cantidad de estudiantes que ingresan (50 a 20000)}
        {--umbral-ms=500 : Límite aceptable de latencia (percentil 95) por operación, en milisegundos}
        {--force : Permite ejecutarla cuando APP_ENV=production}';

    protected $description = 'Mide el rendimiento del registro de ingreso con muchos estudiantes (no deja datos: revierte todo).';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Estás en producción. Esta prueba consume recursos del servidor: usa --force si de verdad quieres correrla.');

            return self::FAILURE;
        }

        $n = (int) $this->option('estudiantes');
        $umbral = max(1, (int) $this->option('umbral-ms'));
        if ($n < 50 || $n > 20000) {
            $this->error('--estudiantes debe estar entre 50 y 20000.');

            return self::FAILURE;
        }

        $this->info("Prueba de carga: {$n} estudiantes · umbral p95 {$umbral} ms · motor ".DB::connection()->getDriverName());
        $this->line('Todo se ejecuta dentro de una transacción que se revierte al final.');

        DB::beginTransaction();
        try {
            return $this->ejecutar($n, $umbral);
        } catch (Throwable $e) {
            $this->error('La prueba falló: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            DB::rollBack();
            $this->line('Datos de prueba revertidos.');
        }
    }

    private function ejecutar(int $n, int $umbral): int
    {
        // ---- Datos sintéticos ----
        $t0 = microtime(true);
        $control = User::create([
            'name' => 'Carga Control', 'nombre' => 'Carga', 'apellido' => 'Control', 'email' => 'carga.control@prueba.local',
            'password' => bcrypt('x'), 'rol' => 'control', 'estado' => 'activo',
        ]);
        $ambiente = Ambiente::create(['nombre' => 'Aula PRUEBA-CARGA', 'ubicacion' => 'Prueba', 'capacidad' => $n]);
        $asig = Asignatura::create(['codigo' => 'CARGA', 'nombre' => 'Prueba de carga']);
        $examen = Examen::create([
            'asignatura_id' => $asig->id_asignatura, 'carrera' => 'Carrera CARGA', 'fecha' => now()->toDateString(),
            'hora_inicio' => '23:59', 'duracion_min' => 120, 'ambiente' => $ambiente->nombre, 'capacidad' => $n, 'estado' => 'abierto',
        ]);

        $ahora = now()->toDateTimeString();
        foreach (array_chunk(range(1, $n), 500) as $lote) {
            DB::table('estudiante')->insert(array_map(fn ($i) => [
                'codigo_universitario' => sprintf('97%07d', $i), 'documento_identidad' => sprintf('97%06d', $i),
                'nombres' => 'Carga'.$i, 'apellidos' => 'Prueba'.$i, 'carrera' => 'Carrera CARGA', 'estado' => 'ACTIVO',
                'foto_path' => 'fotos_estudiantes/carga.jpg', 'foto_estado' => 'APROBADA',
            ], $lote));
        }
        $ids = DB::table('estudiante')->where('carrera', 'Carrera CARGA')->orderBy('id_estudiante')->pluck('id_estudiante')->all();
        foreach (array_chunk($ids, 500) as $lote) {
            DB::table('estudiante_asignatura')->insert(array_map(fn ($id) => [
                'id_estudiante' => $id, 'id_asignatura' => $asig->id_asignatura, 'gestion' => '2026-II', 'estado' => 'ACTIVO',
            ], $lote));
            DB::table('habilitacion')->insert(array_map(fn ($id) => [
                'estudiante_id' => $id, 'examen_id' => $examen->id, 'estado' => 'habilitado', 'created_at' => $ahora, 'updated_at' => $ahora,
            ], $lote));
        }
        $this->line(sprintf('Datos sintéticos listos en %.1f s.', microtime(true) - $t0));

        $vc = app(VerificacionController::class);
        $llamar = function (string $metodo, array $cuerpo) use ($vc, $control) {
            $req = Request::create('/prueba', 'POST', $cuerpo);
            $req->setUserResolver(fn () => $control);
            $ini = microtime(true);
            $resp = app()->call([$vc, $metodo], ['request' => $req]);

            return [(microtime(true) - $ini) * 1000, $resp->getStatusCode()];
        };

        // ---- Llegada de los estudiantes: verificar + registrar ingreso ----
        $verif = $ingr = $total = [];
        $errores = 0;
        $inicio = microtime(true);
        $barra = $this->output->createProgressBar($n);
        foreach ($ids as $i => $id) {
            [$msV, $stV] = $llamar('verificar', [
                'id_examen' => $examen->id, 'metodo_verificacion' => 'CODIGO_UNIV',
                'codigo_universitario' => sprintf('97%07d', $i + 1), 'ambiente_control_id' => $ambiente->id,
            ]);
            [$msI, $stI] = $llamar('ingreso', [
                'id_estudiante' => $id, 'id_examen' => $examen->id, 'metodo' => 'CODIGO_UNIV', 'ambiente_control_id' => $ambiente->id,
            ]);
            if ($stV !== 200 || $stI !== 201) {
                $errores++;
            }
            $verif[] = $msV;
            $ingr[] = $msI;
            $total[] = $msV + $msI;
            $barra->advance();
        }
        $barra->finish();
        $this->newLine();
        $duracion = microtime(true) - $inicio;

        // ---- Segundo ingreso del mismo estudiante: debe rechazarse siempre ----
        $repetidos = min(100, $n);
        $rechazados = 0;
        foreach (array_slice($ids, 0, $repetidos) as $id) {
            try {
                [, $st] = $llamar('ingreso', ['id_estudiante' => $id, 'id_examen' => $examen->id, 'metodo' => 'CODIGO_UNIV', 'ambiente_control_id' => $ambiente->id]);
                $rechazados += $st === 409 ? 1 : 0;
            } catch (Throwable) {
                $rechazados++;
            }
        }
        $registrados = DB::table('ingreso')->where('examen_id', $examen->id)->count();

        // ---- Control en vivo con el examen lleno ----
        $ev = app(ControlEnVivoController::class);
        $enVivo = function (string $metodo) use ($ev, $control, $examen) {
            $req = Request::create('/prueba', 'GET');
            $req->setUserResolver(fn () => $control);
            $ini = microtime(true);
            $ev->{$metodo}($req, $examen);

            return (microtime(true) - $ini) * 1000;
        };
        $msIngresados = $enVivo('ingresados');
        $msPendientes = $enVivo('pendientes');

        // ---- Resultados ----
        $fila = fn (string $nombre, array $v) => [$nombre, $this->pct($v, 50), $this->pct($v, 95), $this->pct($v, 99), round(max($v), 1)];
        $this->newLine();
        $this->table(['Operación (ms)', 'p50', 'p95', 'p99', 'máx'], [
            $fila('Verificar identidad', $verif),
            $fila('Registrar ingreso', $ingr),
            $fila('Verificar + registrar (por estudiante)', $total),
        ]);
        $this->line(sprintf('Rendimiento: %d ingresos en %.1f s → %.0f estudiantes/minuto en un solo puesto de control.', $n, $duracion, $n / max($duracion, 0.001) * 60));
        $this->line(sprintf('Control en vivo con %d ingresos: lista de ingresados %.0f ms · lista de pendientes %.0f ms.', $registrados, $msIngresados, $msPendientes));

        $p95 = max($this->pct($verif, 95), $this->pct($ingr, 95));
        $checks = [
            ["p95 de cada operación ≤ {$umbral} ms (p95 = {$p95} ms)", $p95 <= $umbral],
            ["Sin errores en {$n} ingresos (errores: {$errores})", $errores === 0],
            ["Ingresos guardados = estudiantes ({$registrados}/{$n})", $registrados === $n],
            ["Segundo ingreso rechazado ({$rechazados}/{$repetidos})", $rechazados === $repetidos],
            [sprintf('Consultas del Control en vivo ≤ %d ms', $umbral * 4), $msIngresados <= $umbral * 4 && $msPendientes <= $umbral * 4],
        ];
        $ok = true;
        foreach ($checks as [$texto, $paso]) {
            $this->line(($paso ? '  <info>✔</info> ' : '  <error>✘</error> ').$texto);
            $ok = $ok && $paso;
        }
        $this->line($ok ? '<info>RESULTADO: APROBADA</info>' : '<error>RESULTADO: NO APROBADA</error>');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function pct(array $valores, int $p): float
    {
        sort($valores);
        $i = (int) ceil($p / 100 * count($valores)) - 1;

        return round($valores[max(0, min($i, count($valores) - 1))], 1);
    }
}
