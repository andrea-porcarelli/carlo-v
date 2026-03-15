<?php

namespace App\Jobs;

use App\Interfaces\PrinterServiceInterface;
use App\Models\TableOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrintMarciaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $tableOrderId,
        public readonly int $operatorId,
    ) {
        $this->onQueue('printers');
    }

    public function handle(PrinterServiceInterface $printerService): void
    {
        $tableOrder = TableOrder::with(['items.dish.category.printer', 'restaurantTable'])->find($this->tableOrderId);
        if (!$tableOrder) {
            return;
        }

        $printerService->printMarciaTavolo($tableOrder, $this->operatorId);
    }
}
