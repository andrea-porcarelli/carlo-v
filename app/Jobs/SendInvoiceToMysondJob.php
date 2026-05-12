<?php

namespace App\Jobs;

use App\Facades\Utils;
use App\Models\InvoiceMysondLog;
use App\Models\TableOrderInvoice;
use App\Services\MysondFatturaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendInvoiceToMysondJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public int $invoiceId)
    {
        $this->onQueue('invoices');
    }

    public function handle(MysondFatturaService $service): void
    {
        $invoice = TableOrderInvoice::find($this->invoiceId);
        if (!$invoice) {
            Log::warning("SendInvoiceToMysondJob: invoice {$this->invoiceId} not found");
            return;
        }

        if (empty($invoice->xml_content) || empty($invoice->invoice_name)) {
            $payload = ['error' => 'xml_content o invoice_name mancante'];
            $invoice->update([
                'status'          => 'error',
                'mysond_response' => json_encode($payload),
            ]);
            InvoiceMysondLog::create([
                'table_order_invoice_id' => $invoice->id,
                'operation'              => 'importFeAttivo',
                'outcome'                => InvoiceMysondLog::OUTCOME_ERROR,
                'descrizione'            => $payload['error'],
            ]);
            return;
        }

        $fileName = 'IT' . Utils::setting('company_vat_number') . '_' . $invoice->invoice_name . '.xml';

        $startedAt = microtime(true);
        $result    = null;
        $exception = null;

        try {
            $result = $service->importFeAttivo($invoice->xml_content, $fileName, true);
        } catch (Throwable $e) {
            $exception = $e;
            Log::error('importFeAttivo exception: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
        }

        $durationMs   = (int) round((microtime(true) - $startedAt) * 1000);
        $requestXml   = $service->getLastRequestXml();
        $responseXml  = $service->getLastResponseXml();

        if ($exception !== null) {
            $invoice->update([
                'status'          => 'error',
                'mysond_response' => json_encode([
                    'exception' => $exception->getMessage(),
                    'at'        => now()->toIso8601String(),
                ]),
            ]);
            InvoiceMysondLog::create([
                'table_order_invoice_id' => $invoice->id,
                'operation'              => 'importFeAttivo',
                'outcome'                => InvoiceMysondLog::OUTCOME_EXCEPTION,
                'request_xml'            => $requestXml,
                'response_xml'           => $responseXml,
                'exception_class'        => get_class($exception),
                'exception_message'      => $exception->getMessage(),
                'exception_trace'        => $exception->getTraceAsString(),
                'duration_ms'            => $durationMs,
            ]);
            throw $exception;
        }

        $esito       = isset($result->esito) ? (int) $result->esito : null;
        $codice      = $result->codice ?? null;
        $descrizione = $result->descrizione ?? ($result->messaggio ?? null);

        $payload = [
            'esito'       => $esito,
            'codice'      => $codice,
            'descrizione' => $descrizione,
            'at'          => now()->toIso8601String(),
        ];

        $isSuccess = $esito === 0;

        $invoice->update([
            'status'          => $isSuccess ? 'sent' : 'error',
            'sent_at'         => $isSuccess ? now() : $invoice->sent_at,
            'mysond_response' => json_encode($payload),
        ]);

        InvoiceMysondLog::create([
            'table_order_invoice_id' => $invoice->id,
            'operation'              => 'importFeAttivo',
            'outcome'                => $isSuccess ? InvoiceMysondLog::OUTCOME_SUCCESS : InvoiceMysondLog::OUTCOME_ERROR,
            'esito'                  => $esito,
            'codice'                 => $codice ? (string) $codice : null,
            'descrizione'            => $descrizione,
            'request_xml'            => $requestXml,
            'response_xml'           => $responseXml,
            'duration_ms'            => $durationMs,
        ]);
    }
}
