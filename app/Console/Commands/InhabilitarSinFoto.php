<?php

namespace App\Console\Commands;

use App\Models\Examen;
use App\Services\FotoEstudianteService;
use Illuminate\Console\Command;

class InhabilitarSinFoto extends Command
{
    protected $signature = 'sciem:inhabilitar-sin-foto';

    protected $description = 'Inhabilita a los estudiantes sin foto válida cuando venció el plazo de subida y el examen aún no empieza.';

    public function handle(FotoEstudianteService $servicio): int
    {
        $total = 0;

        $examenes = Examen::where('estado', '!=', 'finalizado')
            ->whereDate('fecha', '>=', now()->subDay()->toDateString())
            ->get();

        foreach ($examenes as $examen) {
            if (now()->gte($examen->limiteFoto()) && now()->lt($examen->inicio)) {
                $n = $servicio->inhabilitarSinFoto($examen);
                $total += $n;
                $this->line("Examen #{$examen->id} ({$examen->nombre}): {$n} inhabilitado(s).");
            }
        }

        $this->info("Listo. Total inhabilitados: {$total}.");

        return self::SUCCESS;
    }
}
