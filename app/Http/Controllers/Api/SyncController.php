<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncWatermark;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SyncController extends Controller
{
    public function status(): JsonResponse
    {
        $role = config('sync.role');
        $masterTables = config("sync.master_tables.{$role}", []);

        $watermarks = [];
        foreach ($masterTables as $table) {
            $wm = SyncWatermark::getWatermark($table);
            $watermarks[$table] = $wm?->toIso8601String();
        }

        return response()->json([
            'status' => 'ok',
            'role' => $role,
            'master_tables' => $masterTables,
            'watermarks' => $watermarks,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function export(Request $request, string $table): JsonResponse
    {
        $role = config('sync.role');
        $masterTables = config("sync.master_tables.{$role}", []);

        if (!in_array($table, $masterTables)) {
            return response()->json([
                'error' => "Table '{$table}' is not mastered by this instance ({$role})",
            ], 403);
        }

        $since = $request->query('since');
        $page = max(1, (int) $request->query('page', 1));
        $batchSize = config('sync.batch_size', 500);

        $pivotTables = config('sync.pivot_tables', []);
        $isPivot = in_array($table, $pivotTables);

        if ($isPivot) {
            return $this->exportPivot($table, $since, $page, $batchSize);
        }

        $modelClass = config("sync.models.{$table}");
        if (!$modelClass || !class_exists($modelClass)) {
            return response()->json(['error' => "Model not configured for table '{$table}'"], 500);
        }

        $query = $modelClass::query();

        // Include soft-deleted records
        $softDeleteTables = config('sync.soft_delete_tables', []);
        if (in_array($table, $softDeleteTables)) {
            $query->withTrashed();
        }

        if ($since) {
            $sinceDate = Carbon::parse($since);
            $query->where('updated_at', '>', $sinceDate);
        }

        $query->orderBy('updated_at', 'asc')->orderBy('id', 'asc');

        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / $batchSize));
        $offset = ($page - 1) * $batchSize;

        $records = $query->offset($offset)->limit($batchSize)->get();

        // Collect deleted IDs for soft-delete tables
        $deletedIds = [];
        if (in_array($table, $softDeleteTables) && $since) {
            $deletedIds = $modelClass::onlyTrashed()
                ->where('deleted_at', '>', Carbon::parse($since))
                ->pluck('id')
                ->toArray();
        }

        return response()->json([
            'table' => $table,
            'data' => $records->toArray(),
            'deleted_ids' => $deletedIds,
            'meta' => [
                'page' => $page,
                'per_page' => $batchSize,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    private function exportPivot(string $table, ?string $since, int $page, int $batchSize): JsonResponse
    {
        $query = DB::table($table);

        if ($since) {
            $sinceDate = Carbon::parse($since);
            $query->where('updated_at', '>', $sinceDate);
        }

        $query->orderBy('updated_at', 'asc')->orderBy('id', 'asc');

        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / $batchSize));
        $offset = ($page - 1) * $batchSize;

        $records = $query->offset($offset)->limit($batchSize)->get();

        return response()->json([
            'table' => $table,
            'data' => $records->toArray(),
            'deleted_ids' => [],
            'meta' => [
                'page' => $page,
                'per_page' => $batchSize,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    public function exportMedia(int $mediaId): BinaryFileResponse|JsonResponse
    {
        $role = config('sync.role');
        if ($role !== 'web') {
            return response()->json(['error' => 'Media export is only available on WEB instance'], 403);
        }

        $media = \App\Models\Media::find($mediaId);
        if (!$media) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        $filePath = storage_path('app/public/' . $media->folder . '/' . $media->filename);

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found on disk'], 404);
        }

        return response()->download($filePath, $media->filename, [
            'Content-Type' => $media->mime_type,
        ]);
    }
}
