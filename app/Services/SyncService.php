<?php

namespace App\Services;

use App\Models\SyncLog;
use App\Models\SyncWatermark;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SyncService
{
    protected string $role;
    protected string $peerUrl;
    protected string $apiToken;
    protected int $timeout;
    protected int $batchSize;
    protected string $triggeredBy;

    public function __construct(string $triggeredBy = 'manual')
    {
        $this->role = config('sync.role');
        $this->peerUrl = rtrim(config('sync.peer_url'), '/');
        $this->apiToken = config('sync.api_token');
        $this->timeout = config('sync.timeout');
        $this->batchSize = config('sync.batch_size');
        $this->triggeredBy = $triggeredBy;

        // Allow the peer URL to be overridden from the DB settings
        // (e.g. 'carlov_url' on the web server stores the zrok public endpoint)
//        $dbPeerUrl = \App\Models\Setting::get('carlov_url', '');
//        if ($dbPeerUrl) {
//            $this->peerUrl = rtrim($dbPeerUrl, '/');
//        }
    }

    public function setTriggeredBy(string $triggeredBy): void
    {
        $this->triggeredBy = $triggeredBy;
    }

    /**
     * Run full sync: pull all tables from peer in the correct order.
     */
    public function runFullSync(): array
    {
        Cache::put('sync_in_progress', true);

        try {
            $peerRole = $this->getPeerRole();
            $tables = config("sync.sync_order.{$peerRole}", []);

            $results = [];
            foreach ($tables as $table) {
                $results[$table] = $this->syncTable($table);
            }

            // Sync media files after media records
            if ($peerRole === 'web') {
                $results['media_files'] = $this->syncMediaFiles();
            }

            return $results;
        } finally {
            Cache::forget('sync_in_progress');
        }
    }

    /**
     * Generate a ZIP of local master tables and POST it to the peer.
     */
    public function runPushZipSync(): array
    {
        $start = microtime(true);
        $role = config('sync.role');
        $masterTables = config("sync.master_tables.{$role}", []);
        $softDeleteTables = config('sync.soft_delete_tables', []);
        $pivotTables = config('sync.pivot_tables', []);

        Log::info('[Sync] Push started', [
            'role'       => $role,
            'peer_url'   => $this->peerUrl,
            'tables'     => $masterTables,
        ]);

        $zipPath = tempnam(sys_get_temp_dir(), 'carlov_push_') . '.zip';

        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create push ZIP archive');
            }

            $tableStats = [];
            foreach ($masterTables as $table) {
                if (in_array($table, $pivotTables)) {
                    $records = DB::table($table)->orderBy('id')->get()->map(fn($r) => (array) $r)->toArray();
                    $deletedIds = [];
                } else {
                    $modelClass = config("sync.models.{$table}");
                    if (!$modelClass || !class_exists($modelClass)) {
                        continue;
                    }
                    $query = $modelClass::query();
                    if (in_array($table, $softDeleteTables)) {
                        $query->withTrashed();
                    }
                    $records = $query->orderBy('id')->get()->toArray();
                    $deletedIds = in_array($table, $softDeleteTables)
                        ? $modelClass::onlyTrashed()->pluck('id')->toArray()
                        : [];
                }

                $tableStats[$table] = count($records);
                $zip->addFromString("{$table}.json", json_encode([
                    'table'       => $table,
                    'data'        => $records,
                    'deleted_ids' => $deletedIds,
                ]));
            }

            $zip->addFromString('manifest.json', json_encode([
                'role'        => $role,
                'tables'      => $masterTables,
                'exported_at' => now()->toIso8601String(),
            ]));

            $zip->close();

            $zipSizeKb = round(filesize($zipPath) / 1024, 1);
            Log::info('[Sync] Push ZIP generated', [
                'size_kb'     => $zipSizeKb,
                'table_stats' => $tableStats,
                'elapsed_ms'  => (int) ((microtime(true) - $start) * 1000),
            ]);

            $uploadStart = microtime(true);
            $response = Http::withHeaders([
                'X-Sync-Token' => $this->apiToken,
            ])->timeout($this->timeout)
                ->attach('zip_file', file_get_contents($zipPath), 'sync_export.zip')
                ->post("{$this->peerUrl}/api/sync/import-zip");

            if (!$response->successful()) {
                throw new \RuntimeException("Push sync failed: HTTP {$response->status()}: {$response->body()}");
            }

            $results = $response->json('results', []);
            $totalMs = (int) ((microtime(true) - $start) * 1000);

            Log::info('[Sync] Push completed', [
                'elapsed_ms'  => $totalMs,
                'upload_ms'   => (int) ((microtime(true) - $uploadStart) * 1000),
                'tables_sent' => count($tableStats),
                'peer_results' => $results,
            ]);

            return $results;
        } catch (\Throwable $e) {
            Log::error('[Sync] Push failed', [
                'error'      => $e->getMessage(),
                'elapsed_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            throw $e;
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    /**
     * Import tables from an already-opened ZipArchive (used by import-zip endpoint).
     */
    public function importFromZip(\ZipArchive $zip): array
    {
        $manifestJson = $zip->getFromName('manifest.json');
        $manifest = $manifestJson ? json_decode($manifestJson, true) : [];
        $peerRole = $manifest['role'] ?? $this->getPeerRole();

        $tables = config("sync.sync_order.{$peerRole}", []);
        $results = [];

        $failedTables = [];
        foreach ($tables as $table) {
            $json = $zip->getFromName("{$table}.json");
            if ($json === false) {
                continue;
            }

            if ($this->hasDependencyFailed($table, $failedTables)) {
                Log::warning("[Sync] Skipping '{$table}': dependency failed", ['failed_deps' => $this->getFailedDependencies($table, $failedTables)]);
                $results[$table] = ['status' => 'skipped', 'reason' => 'dependency_failed'];
                continue;
            }

            $tableData = json_decode($json, true);
            $result = $this->syncTableFromData(
                $table,
                $tableData['data'] ?? [],
                $tableData['deleted_ids'] ?? [],
                $peerRole
            );

            if (($result['status'] ?? '') === 'failed') {
                $failedTables[] = $table;
            }

            $results[$table] = $result;
        }

        return $results;
    }

    /**
     * Download a ZIP from the peer containing all tables and import locally.
     */
    public function runZipSync(): array
    {
        $start = microtime(true);
        Cache::put('sync_in_progress', true);

        Log::info('[Sync] Pull started', [
            'role'     => $this->role,
            'peer_url' => $this->peerUrl,
        ]);

        $zipPath = null;
        try {
            $zipPath = tempnam(sys_get_temp_dir(), 'carlov_sync_') . '.zip';

            $downloadStart = microtime(true);
            $response = Http::withHeaders([
                'X-Sync-Token' => $this->apiToken,
            ])->timeout($this->timeout)->sink($zipPath)->get("{$this->peerUrl}/api/sync/export-zip");

            if (!$response->successful()) {
                throw new \RuntimeException("Failed to download sync ZIP: HTTP {$response->status()}");
            }

            $zipSizeKb = round(filesize($zipPath) / 1024, 1);
            Log::info('[Sync] ZIP downloaded', [
                'size_kb'     => $zipSizeKb,
                'download_ms' => (int) ((microtime(true) - $downloadStart) * 1000),
            ]);

            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException("Failed to open sync ZIP archive");
            }

            $manifestJson = $zip->getFromName('manifest.json');
            $manifest = $manifestJson ? json_decode($manifestJson, true) : [];
            $peerRole = $manifest['role'] ?? $this->getPeerRole();

            Log::info('[Sync] Manifest read', [
                'peer_role'   => $peerRole,
                'exported_at' => $manifest['exported_at'] ?? null,
                'tables'      => $manifest['tables'] ?? [],
            ]);

            $tables = config("sync.sync_order.{$peerRole}", []);
            $results = [];

            $failedTables = [];
            foreach ($tables as $table) {
                $json = $zip->getFromName("{$table}.json");
                if ($json === false) {
                    Log::warning('[Sync] Missing file in ZIP', ['table' => $table]);
                    continue;
                }

                if ($this->hasDependencyFailed($table, $failedTables)) {
                    Log::warning("[Sync] Skipping '{$table}': dependency failed", ['failed_deps' => $this->getFailedDependencies($table, $failedTables)]);
                    $results[$table] = ['status' => 'skipped', 'reason' => 'dependency_failed'];
                    continue;
                }

                $tableData = json_decode($json, true);
                $result = $this->syncTableFromData(
                    $table,
                    $tableData['data'] ?? [],
                    $tableData['deleted_ids'] ?? [],
                    $peerRole
                );

                if (($result['status'] ?? '') === 'failed') {
                    $failedTables[] = $table;
                }

                $results[$table] = $result;
            }

            $zip->close();

            if ($peerRole === 'web') {
                $results['media_files'] = $this->syncMediaFiles();
            }

            $totalMs = (int) ((microtime(true) - $start) * 1000);
            $failed = array_filter($results, fn($r) => ($r['status'] ?? '') === 'failed');

            Log::info('[Sync] Pull completed', [
                'elapsed_ms'    => $totalMs,
                'tables_synced' => count($results),
                'tables_failed' => count($failed),
                'summary'       => array_map(fn($r) => [
                    'status'  => $r['status'] ?? '?',
                    'created' => $r['created'] ?? 0,
                    'updated' => $r['updated'] ?? 0,
                    'deleted' => $r['deleted'] ?? 0,
                ], $results),
            ]);

            return $results;
        } catch (\Throwable $e) {
            Log::error('[Sync] Pull failed', [
                'error'      => $e->getMessage(),
                'elapsed_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            throw $e;
        } finally {
            Cache::forget('sync_in_progress');
            if ($zipPath && file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    /**
     * Import a single table from pre-loaded data (used by ZIP sync).
     */
    protected function syncTableFromData(string $table, array $data, array $deletedIds, string $peerRole): array
    {
        $start = microtime(true);

        Log::info("[Sync] Importing table '{$table}'", [
            'records'     => count($data),
            'deleted_ids' => count($deletedIds),
            'peer_role'   => $peerRole,
        ]);

        $log = SyncLog::create([
            'direction'    => "pull_from_{$peerRole}",
            'peer_role'    => $peerRole,
            'table_name'   => $table,
            'status'       => 'running',
            'triggered_by' => $this->triggeredBy,
        ]);

        try {
            $watermark = SyncWatermark::getWatermark($table);
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalDeleted = 0;
            $totalSkipped = 0;
            $latestUpdatedAt = $watermark;

            if (!empty($data)) {
                $pivotTables = config('sync.pivot_tables', []);
                if (in_array($table, $pivotTables)) {
                    $result = $this->importPivotData($table, $data, $watermark);
                } else {
                    $result = $this->importModelData($table, $data);
                }

                $totalCreated += $result['created'];
                $totalUpdated += $result['updated'];
                $totalSkipped += $result['skipped'];

                if (!empty($result['latest_updated_at'])) {
                    $candidate = Carbon::parse($result['latest_updated_at']);
                    if (!$latestUpdatedAt || $candidate->gt($latestUpdatedAt)) {
                        $latestUpdatedAt = $candidate;
                    }
                }
            }

            if (!empty($deletedIds)) {
                $totalDeleted += $this->applySoftDeletes($table, $deletedIds);
            }

            if ($latestUpdatedAt) {
                SyncWatermark::setWatermark($table, $latestUpdatedAt);
            }

            if ($table === 'settings') {
                $this->invalidateSettingsCache();
            }

            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $totalImported = $totalCreated + $totalUpdated;

            Log::info("[Sync] Table '{$table}' done", [
                'created'     => $totalCreated,
                'updated'     => $totalUpdated,
                'deleted'     => $totalDeleted,
                'skipped'     => $totalSkipped,
                'duration_ms' => $durationMs,
                'watermark'   => $latestUpdatedAt?->toIso8601String(),
            ]);

            $log->update([
                'status'           => 'success',
                'records_exported' => count($data),
                'records_imported' => $totalImported,
                'records_created'  => $totalCreated,
                'records_updated'  => $totalUpdated,
                'records_deleted'  => $totalDeleted,
                'records_skipped'  => $totalSkipped,
                'last_synced_at'   => now(),
                'duration_ms'      => $durationMs,
            ]);

            return [
                'status'      => 'success',
                'exported'    => count($data),
                'created'     => $totalCreated,
                'updated'     => $totalUpdated,
                'deleted'     => $totalDeleted,
                'skipped'     => $totalSkipped,
                'duration_ms' => $durationMs,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            Log::error("[Sync] Table '{$table}' failed", [
                'error'       => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'last_synced_at' => now(),
                'duration_ms'   => $durationMs,
            ]);

            Log::error("Sync failed for table '{$table}': {$e->getMessage()}", ['exception' => $e]);

            return [
                'status'      => 'failed',
                'error'       => $e->getMessage(),
                'duration_ms' => $durationMs,
            ];
        }
    }

    /**
     * Sync a single table from the peer.
     */
    public function syncTable(string $table): array
    {
        $peerRole = $this->getPeerRole();
        $start = microtime(true);

        $log = SyncLog::create([
            'direction' => "pull_from_{$peerRole}",
            'peer_role' => $peerRole,
            'table_name' => $table,
            'status' => 'running',
            'triggered_by' => $this->triggeredBy,
        ]);

        try {
            $watermark = SyncWatermark::getWatermark($table);
            $since = $watermark?->toIso8601String();

            $totalImported = 0;
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalDeleted = 0;
            $totalSkipped = 0;
            $totalExported = 0;
            $latestUpdatedAt = $watermark;
            $page = 1;

            do {
                $response = $this->callPeerExport($table, $since, $page);

                if (!$response) {
                    throw new \RuntimeException("Failed to fetch data from peer for table '{$table}'");
                }

                $data = $response['data'] ?? [];
                $deletedIds = $response['deleted_ids'] ?? [];
                $meta = $response['meta'] ?? [];
                $totalExported += count($data);

                if (!empty($data)) {
                    $pivotTables = config('sync.pivot_tables', []);

                    if (in_array($table, $pivotTables)) {
                        $result = $this->importPivotData($table, $data, $watermark);
                    } else {
                        $result = $this->importModelData($table, $data);
                    }

                    $totalCreated += $result['created'];
                    $totalUpdated += $result['updated'];
                    $totalSkipped += $result['skipped'];
                    $totalImported += $result['created'] + $result['updated'];

                    // Track latest updated_at
                    if (!empty($result['latest_updated_at'])) {
                        $candidate = Carbon::parse($result['latest_updated_at']);
                        if (!$latestUpdatedAt || $candidate->gt($latestUpdatedAt)) {
                            $latestUpdatedAt = $candidate;
                        }
                    }
                }

                // Apply soft deletes
                if (!empty($deletedIds)) {
                    $deleted = $this->applySoftDeletes($table, $deletedIds);
                    $totalDeleted += $deleted;
                }

                $page++;
                $totalPages = $meta['total_pages'] ?? 1;
            } while ($page <= $totalPages);

            // Update watermark
            if ($latestUpdatedAt) {
                SyncWatermark::setWatermark($table, $latestUpdatedAt);
            }

            // Invalidate settings cache after sync
            if ($table === 'settings') {
                $this->invalidateSettingsCache();
            }

            $durationMs = (int) ((microtime(true) - $start) * 1000);

            $log->update([
                'status' => 'success',
                'records_exported' => $totalExported,
                'records_imported' => $totalImported,
                'records_created' => $totalCreated,
                'records_updated' => $totalUpdated,
                'records_deleted' => $totalDeleted,
                'records_skipped' => $totalSkipped,
                'last_synced_at' => now(),
                'duration_ms' => $durationMs,
            ]);

            return [
                'status' => 'success',
                'exported' => $totalExported,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'deleted' => $totalDeleted,
                'skipped' => $totalSkipped,
                'duration_ms' => $durationMs,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'last_synced_at' => now(),
                'duration_ms' => $durationMs,
            ]);

            Log::error("Sync failed for table '{$table}': {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ];
        }
    }

    /**
     * Import model data using upsert with forceFill to preserve IDs and timestamps.
     */
    protected function importModelData(string $table, array $data): array
    {
        $modelClass = config("sync.models.{$table}");
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $latestUpdatedAt = null;

        DB::beginTransaction();
        try {
            $modelClass::withoutEvents(function () use ($modelClass, $table, $data, &$created, &$updated, &$skipped, &$latestUpdatedAt) {
                // Disable activity logging if available
                $loggingDisabled = false;
                if (function_exists('activity')) {
                    activity()->disableLogging();
                    $loggingDisabled = true;
                }

                try {
                    $upsertKey = config("sync.upsert_keys.{$table}", 'id');

                    foreach ($data as $record) {
                        $record = $this->normalizeDateFields((array) $record);

                        // Track latest updated_at
                        if (!empty($record['updated_at'])) {
                            $ts = $record['updated_at'];
                            if (!$latestUpdatedAt || $ts > $latestUpdatedAt) {
                                $latestUpdatedAt = $ts;
                            }
                        }

                        // Check if record exists
                        $existing = null;
                        if ($upsertKey === 'id') {
                            if (empty($record['id'])) {
                                $skipped++;
                                continue;
                            }
                            $softDeleteTables = config('sync.soft_delete_tables', []);
                            if (in_array($table, $softDeleteTables)) {
                                $existing = $modelClass::withTrashed()->find($record['id']);
                            } else {
                                $existing = $modelClass::find($record['id']);
                            }
                        } else {
                            if (empty($record[$upsertKey])) {
                                $skipped++;
                                continue;
                            }
                            $existing = $modelClass::where($upsertKey, $record[$upsertKey])->first();
                        }

                        if ($existing) {
                            $existing->timestamps = false;
                            $existing->forceFill($record);
                            $existing->save();
                            $updated++;
                        } else {
                            $model = new $modelClass();
                            $model->timestamps = false;
                            $model->forceFill($record);
                            $model->save();
                            $created++;
                        }
                    }
                } finally {
                    if ($loggingDisabled && function_exists('activity')) {
                        activity()->enableLogging();
                    }
                }
            });

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return compact('created', 'updated', 'skipped', 'latestUpdatedAt');
    }

    /**
     * Import pivot table data (dish_allergens, dish_materials).
     * First sync: truncate + insert. Incremental: upsert by id.
     */
    protected function importPivotData(string $table, array $data, ?Carbon $watermark): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $latestUpdatedAt = null;

        DB::beginTransaction();
        try {
            if (!$watermark) {
                // First sync: delete all rows and insert (DELETE is transactional, TRUNCATE is not)
                DB::table($table)->delete();
                foreach (array_chunk($data, 500) as $chunk) {
                    $rows = array_map(function ($record) use (&$latestUpdatedAt) {
                        $record = $this->normalizeDateFields((array) $record);
                        if (!empty($record['updated_at']) && (!$latestUpdatedAt || $record['updated_at'] > $latestUpdatedAt)) {
                            $latestUpdatedAt = $record['updated_at'];
                        }
                        return $record;
                    }, $chunk);
                    DB::table($table)->insert($rows);
                    $created += count($rows);
                }
            } else {
                // Incremental: upsert by id
                foreach ($data as $record) {
                    $record = $this->normalizeDateFields((array) $record);

                    if (!empty($record['updated_at']) && (!$latestUpdatedAt || $record['updated_at'] > $latestUpdatedAt)) {
                        $latestUpdatedAt = $record['updated_at'];
                    }

                    if (empty($record['id'])) {
                        $skipped++;
                        continue;
                    }

                    $existing = DB::table($table)->where('id', $record['id'])->first();
                    if ($existing) {
                        DB::table($table)->where('id', $record['id'])->update($record);
                        $updated++;
                    } else {
                        DB::table($table)->insert($record);
                        $created++;
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return compact('created', 'updated', 'skipped', 'latestUpdatedAt');
    }

    /**
     * Apply soft deletes for records deleted on the peer.
     */
    protected function applySoftDeletes(string $table, array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $modelClass = config("sync.models.{$table}");
        $softDeleteTables = config('sync.soft_delete_tables', []);

        if (!in_array($table, $softDeleteTables) || !$modelClass) {
            return 0;
        }

        $count = 0;
        $modelClass::withoutEvents(function () use ($modelClass, $ids, &$count) {
            $records = $modelClass::whereIn('id', $ids)->get();
            foreach ($records as $record) {
                $record->delete();
                $count++;
            }
        });

        return $count;
    }

    /**
     * Sync media files from WEB: download binary files that don't exist locally.
     */
    public function syncMediaFiles(): array
    {
        $start = microtime(true);
        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        $log = SyncLog::create([
            'direction' => 'pull_from_web',
            'peer_role' => 'web',
            'table_name' => 'media_files',
            'status' => 'running',
            'triggered_by' => $this->triggeredBy,
        ]);

        try {
            $mediaRecords = \App\Models\Media::all();

            foreach ($mediaRecords as $media) {
                $localPath = storage_path('app/public/' . $media->folder . '/' . $media->filename);

                if (file_exists($localPath)) {
                    $skipped++;
                    continue;
                }

                // Ensure directory exists
                $dir = dirname($localPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                try {
                    $response = Http::withHeaders([
                        'X-Sync-Token' => $this->apiToken,
                    ])->timeout($this->timeout)->get("{$this->peerUrl}/api/sync/export-media/{$media->id}");

                    if ($response->successful()) {
                        file_put_contents($localPath, $response->body());
                        $downloaded++;
                    } else {
                        $failed++;
                        Log::warning("Failed to download media {$media->id}: HTTP {$response->status()}");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning("Failed to download media {$media->id}: {$e->getMessage()}");
                }
            }

            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $status = $failed > 0 ? 'partial' : 'success';

            $log->update([
                'status' => $status,
                'records_exported' => $mediaRecords->count(),
                'records_created' => $downloaded,
                'records_skipped' => $skipped,
                'records_deleted' => 0,
                'records_updated' => 0,
                'records_imported' => $downloaded,
                'last_synced_at' => now(),
                'duration_ms' => $durationMs,
                'details' => ['failed' => $failed],
            ]);

            return [
                'status' => $status,
                'downloaded' => $downloaded,
                'skipped' => $skipped,
                'failed' => $failed,
                'duration_ms' => $durationMs,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'last_synced_at' => now(),
                'duration_ms' => $durationMs,
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ];
        }
    }

    /**
     * HTTP GET to peer export endpoint with pagination.
     */
    protected function callPeerExport(string $table, ?string $since, int $page): ?array
    {
        $url = "{$this->peerUrl}/api/sync/export/{$table}";

        $params = ['page' => $page];
        if ($since) {
            $params['since'] = $since;
        }

        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = Http::withHeaders([
                'X-Sync-Token' => $this->apiToken,
            ])->timeout($this->timeout)->get($url, $params);

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() === 429 && $attempt < $maxAttempts) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 61);
                $retryAfter = min($retryAfter, 120);
                Log::warning("Sync: rate limited (429) on '{$table}', retry in {$retryAfter}s (attempt {$attempt}/{$maxAttempts})");
                sleep($retryAfter);
                continue;
            }

            throw new \RuntimeException(
                "Peer returned HTTP {$response->status()} for table '{$table}': {$response->body()}"
            );
        }

        throw new \RuntimeException(
            "Peer returned HTTP 429 for table '{$table}' after {$maxAttempts} attempts"
        );
    }

    /**
     * Check connectivity with the peer.
     */
    public function checkPeer(): array
    {
        try {
            $response = Http::withHeaders([
                'X-Sync-Token' => $this->apiToken,
            ])->timeout(10)->get("{$this->peerUrl}/api/sync/status");

            if ($response->successful()) {
                return [
                    'connected' => true,
                    'peer' => $response->json(),
                ];
            }

            return [
                'connected' => false,
                'error' => "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getPeerRole(): string
    {
        return $this->role === 'web' ? 'local' : 'web';
    }

    protected function invalidateSettingsCache(): void
    {
        $settings = \App\Models\Setting::all();
        foreach ($settings as $setting) {
            Cache::forget("setting_{$setting->key}");
        }
    }

    protected function hasDependencyFailed(string $table, array $failedTables): bool
    {
        return !empty($this->getFailedDependencies($table, $failedTables));
    }

    protected function getFailedDependencies(string $table, array $failedTables): array
    {
        $deps = config("sync.table_dependencies.{$table}", []);
        return array_values(array_intersect($deps, $failedTables));
    }

    /**
     * Convert ISO 8601 datetime strings (e.g. "2025-12-06T11:46:53.000000Z")
     * to MySQL format ("2025-12-06 11:46:53") for all fields in a record.
     */
    protected function normalizeDateFields(array $record): array
    {
        foreach ($record as $key => $value) {
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                $record[$key] = Carbon::parse($value)->format('Y-m-d H:i:s');
            }
        }

        return $record;
    }
}
