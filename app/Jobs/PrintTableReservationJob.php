<?php

namespace App\Jobs;

use App\Interfaces\PrinterServiceInterface;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Stampa lo scontrino "PRENOTAZIONE TAVOLO" ricevuto da Misuraca sulla
 * stampante preconto configurata in `Setting::getPrecontoPrinter()`
 * (stessa stampante usata per la cooking booking).
 *
 * Eseguito dalla coda `printers` (supervisor — stessi worker degli altri
 * job di stampa).
 */
class PrintTableReservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 30, 60];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly array $data)
    {
        $this->onQueue('printers');
    }

    public function handle(PrinterServiceInterface $printerService): void
    {
        $reference = $this->data['reference'] ?? null;
        $context = [
            'reference' => $reference,
            'reservation_date' => $this->data['reservation_date'] ?? null,
            'slot_time' => $this->data['slot_time'] ?? null,
            'last_name' => $this->data['last_name'] ?? null,
            'attempt' => $this->attempts(),
        ];

        $printer = Setting::getPrecontoPrinter();
        if (! $printer) {
            Log::error('Table reservation print: preconto printer not configured', $context);

            return;
        }

        Log::info('Table reservation print job started', $context + [
            'printer' => $printer->name ?? $printer->id ?? null,
        ]);

        $printerService->printTableReservation($printer, $this->data);

        Log::info('Table reservation print job completed', $context);
    }
}
