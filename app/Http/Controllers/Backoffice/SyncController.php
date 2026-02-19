<?php

namespace App\Http\Controllers\Backoffice;

use App\Models\SyncLog;
use App\Models\SyncWatermark;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SyncController extends BaseController
{
    public function trigger(): JsonResponse
    {
        set_time_limit(0);

        $service = new SyncService(config('sync.role'));
        $results = [];
        $errors = [];

        // Pull: scarica i dati del peer e importa in locale
        try {
            $results = array_merge($results, $service->runZipSync());
        } catch (\Throwable $e) {
            $errors['pull'] = $e->getMessage();
            Log::warning('Sync pull failed: ' . $e->getMessage());
        }

        // Push: invia i dati locali al peer
        try {
            $results = array_merge($results, $service->runPushZipSync());
        } catch (\Throwable $e) {
            $errors['push'] = $e->getMessage();
            Log::warning('Sync push failed: ' . $e->getMessage());
        }

        if (!empty($errors) && empty($results)) {
            return $this->exception(new \RuntimeException(implode(' | ', $errors)));
        }

        return $this->success([
            'results' => $results,
            'errors'  => $errors ?: null,
        ]);
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
