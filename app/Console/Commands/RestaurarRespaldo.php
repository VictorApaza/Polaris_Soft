<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Command;
use Throwable;

/** RQ26: restaura un respaldo desde la terminal (útil si el sistema no abre). Reemplaza TODA la información actual. */
class RestaurarRespaldo extends Command
{
    protected $signature = 'sciem:restaurar {respaldo? : nombre del respaldo sin .zip (si se omite, se listan)} {--force : no pedir confirmación}';

    protected $description = 'Restaura la base de datos y las fotos desde un respaldo guardado en storage/app/backups.';

    public function handle(RespaldoService $respaldos): int
    {
        $nombre = $this->argument('respaldo');

        if (! $nombre) {
            $lista = $respaldos->listar();
            if (! $lista) {
                $this->warn('No hay respaldos guardados.');

                return self::SUCCESS;
            }
            $this->table(['Respaldo', 'Tipo', 'Creado', 'Tablas', 'Filas', 'MB'], array_map(fn ($r) => [
                $r['nombre'], $r['tipo_etiqueta'], $r['creado_en']?->format('d/m/Y H:i') ?? '—', $r['tablas'], $r['filas'], number_format($r['bytes'] / 1048576, 2),
            ], $lista));

            return self::SUCCESS;
        }

        $nombre = preg_replace('/\.zip$/', '', (string) $nombre);
        if (! $respaldos->existe($nombre)) {
            $this->error('No existe ese respaldo. Ejecuta el comando sin argumentos para ver la lista.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Esto REEMPLAZA toda la información actual por la del respaldo. Antes se guarda una copia del estado actual. ¿Continuar?')) {
            return self::SUCCESS;
        }

        try {
            $r = $respaldos->restaurar($nombre);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Restaurado: {$r['tablas']} tablas, {$r['filas']} filas, {$r['archivos']} archivos. Copia previa guardada como {$r['previo']}.");
        foreach ($r['diferencias'] as $d) {
            $this->warn($d);
        }

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
