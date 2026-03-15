<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    private const RETENTION_DAYS = 45;

    public function __construct()
    {
        $this->onQueue('backups');
    }

    public function handle(): void
    {
        $backupDir = storage_path('app/backups');

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $this->runBackup($backupDir);
        $this->pruneOldBackups($backupDir);
    }

    private function runBackup(string $backupDir): void
    {
        $db       = config('database.connections.mysql');
        $host     = $db['host'];
        $port     = (int) ($db['port'] ?? 3306);
        $database = $db['database'];
        $username = $db['username'];
        $password = $db['password'];

        $filename = 'backup_' . now()->format('Y-m-d_H-i') . '.sql.gz';
        $filepath = $backupDir . '/' . $filename;

        // MYSQL_PWD evita di esporre la password nella lista dei processi
        $command = sprintf(
            'MYSQL_PWD=%s mysqldump --host=%s --port=%d --user=%s --single-transaction --routines --triggers %s 2>&1 | gzip > %s',
            escapeshellarg($password),
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $error = implode("\n", $output);
            Log::error('Database backup failed', ['file' => $filename, 'output' => $error]);
            throw new \RuntimeException('Backup failed: ' . $error);
        }

        $sizeKb = round(filesize($filepath) / 1024, 1);
        Log::info('Database backup created', ['file' => $filename, 'size_kb' => $sizeKb]);
    }

    private function pruneOldBackups(string $backupDir): void
    {
        $files   = glob($backupDir . '/backup_*.sql.gz') ?: [];
        $cutoff  = now()->subDays(self::RETENTION_DAYS)->timestamp;
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
                Log::info('Deleted old backup', ['file' => basename($file)]);
            }
        }

        if ($deleted > 0) {
            Log::info("Pruned $deleted backup(s) older than " . self::RETENTION_DAYS . " days");
        }
    }
}
