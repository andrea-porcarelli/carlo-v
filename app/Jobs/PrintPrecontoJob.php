<?php

namespace App\Jobs;

use App\Interfaces\PrinterServiceInterface;
use App\Models\PrecontoSplit;
use App\Models\TableOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PrintPrecontoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    /**
     * @param int $tableOrderId
     * @param int $operatorId
     * @param int|null $splitCount      null = preconto completo
     * @param string|null $discountType
     * @param float $discountAmount
     * @param int|null $splitId         se impostato, preconto parziale (PrecontoSplit)
     */
    public function __construct(
        public readonly int $tableOrderId,
        public readonly int $operatorId,
        public readonly ?int $splitCount = null,
        public readonly ?string $discountType = null,
        public readonly float $discountAmount = 0,
        public readonly ?int $splitId = null,
    ) {
        $this->onQueue('printers');
    }

    public function handle(PrinterServiceInterface $printerService): void
    {
        Log::info('PrintPrecontoJob start', [
            'table_order_id' => $this->tableOrderId,
            'operator_id'    => $this->operatorId,
            'split_id'       => $this->splitId,
            'split_count'    => $this->splitCount,
            'discount_type'  => $this->discountType,
            'discount_amount'=> $this->discountAmount,
        ]);

        $tableOrder = TableOrder::with(['items.dish.category.printer', 'restaurantTable'])->find($this->tableOrderId);
        if (!$tableOrder) {
            Log::warning('PrintPrecontoJob: tableOrder non trovato', ['id' => $this->tableOrderId]);
            return;
        }

        if ($this->splitId !== null) {
            $split = PrecontoSplit::find($this->splitId);
            if (!$split) {
                Log::warning('PrintPrecontoJob: split non trovato', ['split_id' => $this->splitId]);
                return;
            }
            $ok = $printerService->printPartialPreconto($tableOrder, $split, $this->operatorId);
            Log::info('PrintPrecontoJob: printPartialPreconto esito', ['split_id' => $split->id, 'ok' => $ok]);
        } else {
            $ok = $printerService->printPreconto(
                $tableOrder,
                $this->operatorId,
                $this->splitCount,
                $this->discountType ?? 'none',
                $this->discountAmount
            );
            Log::info('PrintPrecontoJob: printPreconto esito', ['table_order_id' => $tableOrder->id, 'ok' => $ok]);
        }
    }
}
