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
use Illuminate\Support\Facades\Log;

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
    ) {
        $this->onQueue('printers');
    }

    public function handle(PrinterServiceInterface $printerService): void
    {
        Log::info('PrintOrderItemsJob started', [
            'order_id' => $this->tableOrderId,
            'item_ids' => $this->itemIds,
            'operation' => $this->operation,
        ]);

        $tableOrder = TableOrder::withTrashed()->with('restaurantTable')->find($this->tableOrderId);
        if (!$tableOrder) {
            Log::warning('PrintOrderItemsJob: order not found', ['order_id' => $this->tableOrderId]);
            return;
        }

        $items = OrderItem::withTrashed()->with('dish.category.printer')
            ->whereIn('id', $this->itemIds)
            ->get();

        if ($items->isEmpty()) {
            Log::warning('PrintOrderItemsJob: items not found', ['item_ids' => $this->itemIds]);
            return;
        }

        Log::info('PrintOrderItemsJob: items found', ['count' => $items->count()]);

        if ($this->operatorId) {
            $printerService->setOperatorId($this->operatorId);
        }

        $printerService->printOrderItems($tableOrder, $items, $this->operation);
    }
}
