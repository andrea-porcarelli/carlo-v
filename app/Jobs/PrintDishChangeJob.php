<?php

namespace App\Jobs;

use App\Interfaces\PrinterServiceInterface;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\TableOrder;
use App\Services\OperationalIncidentReporter;
use App\Support\OperationalErrorCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

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

        $ok = $printerService->printDishChange($tableOrder, $item, $this->oldDishName, $oldPrinter);

        if (!$ok) {
            app(OperationalIncidentReporter::class)->report(
                code:       OperationalErrorCode::PRINT_KITCHEN_FAILED,
                context:    [
                    'motivo'        => 'cambio piatto non stampato',
                    'old_dish_name' => $this->oldDishName,
                    'item_id'       => $this->itemId,
                ],
                tableOrder: $tableOrder,
                userId:     $this->operatorId,
                source:     self::class,
            );
        }
    }

    public function failed(Throwable $e): void
    {
        $tableOrder = TableOrder::find($this->tableOrderId);

        app(OperationalIncidentReporter::class)->report(
            code:            OperationalErrorCode::PRINT_KITCHEN_FAILED,
            context:         [
                'motivo'  => $e->getMessage() ?: 'errore imprevisto cambio piatto',
                'item_id' => $this->itemId,
            ],
            tableOrder:      $tableOrder,
            userId:          $this->operatorId,
            source:          self::class,
            technicalDetail: $e->getMessage(),
        );
    }
}
