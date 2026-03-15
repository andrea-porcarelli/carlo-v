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
        $schedule->command('stock:check-low')->everySixHours();
        $schedule->command('sync:run')->everySixHours()->withoutOverlapping()->appendOutputTo(storage_path('logs/sync.log'));

        // Backup del database ogni 2 ore dalle 8:00 alle 04:00 del giorno successivo
        $schedule->command('db:backup')->cron('0 8,10,12,14,16,18,20,22,0,2,4 * * *');
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
