<?php

namespace App\Console\Commands;

use App\Jobs\DatabaseBackupJob;
use Illuminate\Console\Command;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup';
    protected $description = 'Dispatches a database backup job to the backups queue';

    public function handle(): int
    {
        DatabaseBackupJob::dispatch();
        $this->info('Database backup job dispatched to the backups queue.');
        return self::SUCCESS;
    }
}
