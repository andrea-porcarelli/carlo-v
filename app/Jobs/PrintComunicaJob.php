<?php

namespace App\Jobs;

use App\Interfaces\PrinterServiceInterface;
use App\Models\Printer;
use App\Models\TableOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrintComunicaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $printerId,
        public readonly string $message,
        public readonly int $operatorId,
        public readonly ?int $tableOrderId = null,
    ) {
        $this->onQueue('printers');
    }

    public function handle(PrinterServiceInterface $printerService): void
    {
        $printer = Printer::find($this->printerId);
        if (!$printer) {
            return;
        }

        $tableOrder = $this->tableOrderId
            ? TableOrder::with('restaurantTable')->find($this->tableOrderId)
            : null;

        $printerService->setOperatorId($this->operatorId)
            ->printComunica($printer, $this->message, $this->operatorId, $tableOrder);
    }
}
