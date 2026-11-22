<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Cada hora: inhabilita a quienes no subieron foto válida a tiempo (requiere el cron de Laravel en el servidor).
        $schedule->command('sciem:inhabilitar-sin-foto')->hourly();

        // RQ26: respaldo automático diario de la base de datos y las fotos (hora configurable en config/sciem.php).
        $schedule->command('sciem:respaldar --tipo=automatico')
            ->dailyAt(config('sciem.respaldo_hora', '02:00'))
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
