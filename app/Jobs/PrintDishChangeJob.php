<?php

namespace App\Jobs;

use App\Interfaces\PrinterServiceInterface;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\TableOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrintDishChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $tableOrderId,
        public readonly int $itemId,
        public readonly string $oldDishName,
        public readonly ?int $oldPrinterId,
        public readonly ?int $operatorId = null,
    ) {
        $this->onQueue('printers');
    }

    public function handle(PrinterServiceInterface $printerService): void
    {
        $tableOrder = TableOrder::with('restaurantTable')->find($this->tableOrderId);
        $item = OrderItem::with('dish.category.printer')->find($this->itemId);

        if (!$tableOrder || !$item) {
            return;
        }

        $oldPrinter = $this->oldPrinterId ? Printer::find($this->oldPrinterId) : null;

        if ($this->operatorId) {
            $printerService->setOperatorId($this->operatorId);
        }

        $printerService->printDishChange($tableOrder, $item, $this->oldDishName, $oldPrinter);
    }
}
