<?php

namespace App\Http\Controllers\Backoffice;

use App\Models\SyncLog;
use App\Models\SyncWatermark;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends BaseController
{
    public function trigger(): JsonResponse
    {
        try {
            $service = new SyncService(config('sync.role'));
            $results = $service->runFullSync();

            return $this->success(['results' => $results]);
        } catch (\Throwable $e) {
            return $this->exception($e);
        }
    }

    public function status(): JsonResponse
    {
        $lastLog = SyncLog::latestRun()->first();
        $watermarks = SyncWatermark::all()->keyBy('table_name');

        return $this->success([
            'last_log' => $lastLog,
            'watermarks' => $watermarks,
        ]);
    }
}
