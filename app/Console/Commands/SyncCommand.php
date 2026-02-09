<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Models\SyncWatermark;
use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    protected $signature = 'sync:run
        {--table= : Sync a specific table only}
        {--full : Reset watermarks and run a full sync}
        {--status : Show current sync status and recent logs}
        {--check : Check peer connectivity}';

    protected $description = 'Synchronize data with the peer instance (WEB <-> LOCAL)';

    public function handle(): int
    {
        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('check')) {
            return $this->checkPeer();
        }

        $role = config('sync.role');
        $service = new SyncService('manual');

        $this->info("Sync instance role: {$role}");
        $this->info("Peer URL: " . config('sync.peer_url'));
        $this->newLine();

        if ($this->option('full')) {
            $this->warn('Full sync requested: resetting watermarks...');
            $this->resetWatermarks();
        }

        $table = $this->option('table');

        if ($table) {
            return $this->syncSingleTable($service, $table);
        }

        return $this->syncAll($service);
    }

    protected function syncSingleTable(SyncService $service, string $table): int
    {
        $this->info("Syncing table: {$table}");

        $result = $service->syncTable($table);
        $this->displayResult($table, $result);

        return $result['status'] === 'success' ? self::SUCCESS : self::FAILURE;
    }

    protected function syncAll(SyncService $service): int
    {
        $this->info('Starting full sync...');
        $this->newLine();

        $results = $service->runFullSync();

        $this->newLine();
        $this->info('Sync Summary:');
        $this->newLine();

        $headers = ['Table', 'Status', 'Created', 'Updated', 'Deleted', 'Skipped', 'Time'];
        $rows = [];
        $hasFailure = false;

        foreach ($results as $table => $result) {
            $status = $result['status'] ?? 'unknown';
            if ($status === 'failed') {
                $hasFailure = true;
            }

            $rows[] = [
                $table,
                $status === 'success' ? '<fg=green>OK</>' : ($status === 'partial' ? '<fg=yellow>PARTIAL</>' : '<fg=red>FAIL</>'),
                $result['created'] ?? $result['downloaded'] ?? 0,
                $result['updated'] ?? 0,
                $result['deleted'] ?? 0,
                $result['skipped'] ?? 0,
                isset($result['duration_ms']) ? $result['duration_ms'] . 'ms' : '-',
            ];
        }

        $this->table($headers, $rows);

        if ($hasFailure) {
            $this->error('Some tables failed to sync. Check logs for details.');
            return self::FAILURE;
        }

        $this->info('Sync completed successfully.');
        return self::SUCCESS;
    }

    protected function displayResult(string $table, array $result): void
    {
        if ($result['status'] === 'success') {
            $this->info("  [{$table}] OK - C:{$result['created']} U:{$result['updated']} D:{$result['deleted']} S:{$result['skipped']} ({$result['duration_ms']}ms)");
        } elseif ($result['status'] === 'partial') {
            $downloaded = $result['downloaded'] ?? 0;
            $failed = $result['failed'] ?? 0;
            $this->warn("  [{$table}] PARTIAL - Downloaded:{$downloaded} Failed:{$failed} Skipped:{$result['skipped']} ({$result['duration_ms']}ms)");
        } else {
            $this->error("  [{$table}] FAILED - {$result['error']}");
        }
    }

    protected function showStatus(): int
    {
        $role = config('sync.role');
        $peerRole = $role === 'web' ? 'local' : 'web';

        $this->info("Instance role: {$role}");
        $this->info("Peer URL: " . config('sync.peer_url'));
        $this->newLine();

        // Watermarks
        $this->info('Watermarks:');
        $tables = config("sync.sync_order.{$peerRole}", []);
        $watermarkRows = [];
        foreach ($tables as $table) {
            $wm = SyncWatermark::getWatermark($table);
            $watermarkRows[] = [$table, $wm ? $wm->format('Y-m-d H:i:s') : 'never'];
        }
        $this->table(['Table', 'Last Synced At'], $watermarkRows);

        // Recent logs
        $this->newLine();
        $this->info('Recent sync logs (last 20):');
        $logs = SyncLog::latestRun()->limit(20)->get();

        if ($logs->isEmpty()) {
            $this->warn('No sync logs found.');
            return self::SUCCESS;
        }

        $logRows = [];
        foreach ($logs as $log) {
            $logRows[] = [
                $log->created_at->format('Y-m-d H:i:s'),
                $log->table_name,
                $log->status,
                "C:{$log->records_created} U:{$log->records_updated} D:{$log->records_deleted} S:{$log->records_skipped}",
                $log->duration_ms . 'ms',
                $log->triggered_by,
                $log->error_message ? substr($log->error_message, 0, 50) : '',
            ];
        }
        $this->table(['Time', 'Table', 'Status', 'C/U/D/S', 'Duration', 'By', 'Error'], $logRows);

        return self::SUCCESS;
    }

    protected function checkPeer(): int
    {
        $this->info('Checking peer connectivity...');

        $service = new SyncService();
        $result = $service->checkPeer();

        if ($result['connected']) {
            $peer = $result['peer'];
            $this->info("Connected to peer!");
            $this->info("  Role: {$peer['role']}");
            $this->info("  Tables: " . implode(', ', $peer['master_tables'] ?? []));
            return self::SUCCESS;
        }

        $this->error("Cannot connect to peer: {$result['error']}");
        return self::FAILURE;
    }

    protected function resetWatermarks(): void
    {
        $peerRole = config('sync.role') === 'web' ? 'local' : 'web';
        $tables = config("sync.sync_order.{$peerRole}", []);

        foreach ($tables as $table) {
            SyncWatermark::resetWatermark($table);
        }

        $this->info('Watermarks reset.');
    }
}
