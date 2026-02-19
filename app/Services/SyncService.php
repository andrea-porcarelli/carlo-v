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
                        $record = (array) $record;

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
                // First sync: truncate and insert all
                DB::table($table)->truncate();
                foreach (array_chunk($data, 500) as $chunk) {
                    $rows = array_map(function ($record) use (&$latestUpdatedAt) {
                        $record = (array) $record;
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
                    $record = (array) $record;

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
}
