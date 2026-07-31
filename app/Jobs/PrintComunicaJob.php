<?php

namespace App\Jobs;

use App\Interfaces\PrinterServiceInterface;
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

        $ok = $printerService->setOperatorId($this->operatorId)
            ->printComunica($printer, $this->message, $this->operatorId, $tableOrder);

        if (!$ok) {
            app(OperationalIncidentReporter::class)->report(
                code:       OperationalErrorCode::PRINT_COMUNICA_FAILED,
                context:    [
                    'motivo'     => 'stampante non raggiungibile',
                    'printer_id' => $printer->id,
                    'printer'    => $printer->label,
                ],
                tableOrder: $tableOrder,
                userId:     $this->operatorId,
                source:     self::class,
            );
        }
    }

    public function failed(Throwable $e): void
    {
        $tableOrder = $this->tableOrderId ? TableOrder::find($this->tableOrderId) : null;

        app(OperationalIncidentReporter::class)->report(
            code:            OperationalErrorCode::PRINT_COMUNICA_FAILED,
            context:         [
                'motivo'     => $e->getMessage() ?: 'errore imprevisto',
                'printer_id' => $this->printerId,
            ],
            tableOrder:      $tableOrder,
            userId:          $this->operatorId,
            source:          self::class,
            technicalDetail: $e->getMessage(),
        );
    }
}
