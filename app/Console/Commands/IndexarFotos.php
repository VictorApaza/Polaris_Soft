<?php

namespace App\Console\Commands;

use App\Models\Estudiante;
use App\Services\FotoDuplicadaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Calcula el hash y la huella de las fotos que ya estaban cargadas, para detectar fotos repetidas. */
class IndexarFotos extends Command
{
    protected $signature = 'fotos:indexar {--duplicadas : además lista los estudiantes que ya comparten la misma foto}';

    protected $description = 'Indexa las fotos existentes (hash + huella) y opcionalmente lista las fotos repetidas entre estudiantes.';

    public function handle(FotoDuplicadaService $servicio): int
    {
        $total = 0;
        Estudiante::whereNotNull('foto_path')->where(function ($q) {
            $q->whereNull('foto_hash')->orWhereNull('foto_huella');
        })->each(function (Estudiante $e) use ($servicio, &$total) {
            if (! Storage::disk('local')->exists($e->foto_path)) {
                return;
            }
            $datos = $servicio->calcular((string) Storage::disk('local')->get($e->foto_path));
            $e->forceFill(['foto_hash' => $datos['hash'], 'foto_huella' => $datos['huella']])->save();
            $total++;
        });
        $this->info("Fotos indexadas: {$total}");

        if ($this->option('duplicadas')) {
            $filas = [];
            $estudiantes = Estudiante::whereNotNull('foto_huella')->get(['id_estudiante', 'codigo_universitario', 'nombres', 'apellidos', 'foto_huella']);
            foreach ($estudiantes as $i => $a) {
                foreach ($estudiantes->slice($i + 1) as $b) {
                    if ($servicio->distancia($a->foto_huella, $b->foto_huella) <= (int) config('sciem.foto_duplicada_distancia', 4)) {
                        $filas[] = [$a->codigo_universitario, $a->nombres.' '.$a->apellidos, $b->codigo_universitario, $b->nombres.' '.$b->apellidos];
                    }
                }
            }
            $filas ? $this->table(['Código A', 'Estudiante A', 'Código B', 'Estudiante B'], $filas) : $this->info('No hay fotos repetidas.');
        }

        return self::SUCCESS;
    }
}
