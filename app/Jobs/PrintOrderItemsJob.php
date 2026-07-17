<?php

namespace App\Jobs;

use App\Interfaces\PrinterServiceInterface;
use App\Models\OrderItem;
use App\Models\TableOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrintOrderItemsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $tableOrderId,
        public readonly array $itemIds,
        public readonly string $operation,
        public readonly ?int $operatorId = null,
        public readonly array $quantityOverrides = [],
    ) {
        $this->onQueue('printers');
    }

    public function handle(PrinterServiceInterface $printerService): void
    {
        $tableOrder = TableOrder::withTrashed()->with('restaurantTable')->find($this->tableOrderId);
        if (!$tableOrder) {
            return;
        }

        $items = OrderItem::withTrashed()->with('dish.category.printer')
            ->whereIn('id', $this->itemIds)
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        if (!empty($this->quantityOverrides)) {
            foreach ($items as $item) {
                if (isset($this->quantityOverrides[$item->id])) {
                    $item->quantity = (int) $this->quantityOverrides[$item->id];
                    $item->subtotal = $item->calculateSubtotal();
                }
            }
        }

        if ($this->operatorId) {
            $printerService->setOperatorId($this->operatorId);
        }

        // È il primo invio della comanda se nessun altro item del tavolo è già stato stampato
        $isFirstSend = !$tableOrder->items()
            ->whereNotIn('id', $this->itemIds)
            ->whereNotNull('first_printed_at')
            ->exists();

        $ok = $printerService->printOrderItems($tableOrder, $items, $this->operation, $isFirstSend);

        if ($ok && in_array($this->operation, ['add', 'update'], true)) {
            OrderItem::whereIn('id', $this->itemIds)
                ->whereNull('first_printed_at')
                ->update(['first_printed_at' => now()]);
        }
    }
}
