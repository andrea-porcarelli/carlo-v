<?php

namespace App\Jobs;

use App\Models\TableOrderCorrispettivo;
use App\Services\CorrispettivoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCorrispettivoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $corrispettivoId)
    {
        $this->onQueue('corrispettivi');
    }

    public function handle(CorrispettivoService $service): void
    {
        $corrispettivo = TableOrderCorrispettivo::find($this->corrispettivoId);
        if (!$corrispettivo) {
            Log::channel('corrispettivi')->warning('SendCorrispettivoJob: record non trovato', [
                'corrispettivo_id' => $this->corrispettivoId,
            ]);
            return;
        }

        if ($corrispettivo->isSent()) {
            return;
        }

        Log::channel('corrispettivi')->info('Job retry corrispettivo — start', $corrispettivo->getLogContext());
        $service->riprova($corrispettivo);
        $corrispettivo->refresh();

        Log::channel('corrispettivi')->info('Job retry corrispettivo — end', $corrispettivo->getLogContext() + [
            'status' => $corrispettivo->status,
        ]);

        // Se ancora fallito e possiamo ritentare, rilanciamo il job con backoff maggiore.
        if ($corrispettivo->isFailed() && $corrispettivo->attempts < $corrispettivo->max_attempts) {
            $delay = $this->nextDelay($corrispettivo->attempts);
            Log::channel('corrispettivi')->info('Dispatch retry successivo', $corrispettivo->getLogContext() + [
                'delay_seconds' => $delay,
            ]);
            self::dispatch($corrispettivo->id)->delay(now()->addSeconds($delay));
        }
    }

    private function nextDelay(int $attempts): int
    {
        return match ($attempts) {
            1       => 30,
            2       => 120,
            default => 300,
        };
    }
}
