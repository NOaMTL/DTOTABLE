<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Les commandes Artisan de l'application.
     *
     * @var array
     */
    protected $commands = [
        Commands\ImportTableauDataCommand::class,
    ];

    /**
     * Définir les tâches planifiées de l'application.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Exemple: importer automatiquement chaque nuit à 2h du matin
        // $schedule->command('tableau:import')->dailyAt('02:00');
    }

    /**
     * Enregistrer les commandes pour l'application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
