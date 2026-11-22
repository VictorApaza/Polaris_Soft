<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Command;
use Throwable;

/** RQ26: respaldo de la base de datos y de las fotos. Lo ejecuta el programador de tareas cada día (ver Kernel). */
class Respaldar extends Command
{
    protected $signature = 'sciem:respaldar {--tipo=automatico : manual | automatico} {--sin-archivos : no incluir las fotos ni las evidencias}';

    protected $description = 'Crea un respaldo (base de datos + fotos) en storage/app/backups y borra los automáticos antiguos.';

    public function handle(RespaldoService $respaldos): int
    {
        try {
            $r = $respaldos->crear($this->option('tipo') === 'manual' ? 'manual' : 'automatico', null, $this->option('sin-archivos') ? false : null);
        } catch (Throwable $e) {
            $this->error('No se pudo crear el respaldo: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Respaldo creado: {$r['archivo']} ({$r['tablas']} tablas, {$r['filas']} filas, {$r['archivos']} archivos, ".number_format($r['bytes'] / 1048576, 2).' MB).');

        $borrados = $respaldos->aplicarRetencion();
        if ($borrados > 0) {
            $this->line("Se borraron {$borrados} respaldo(s) automático(s) antiguo(s).");
        }

        return self::SUCCESS;
    }
}
