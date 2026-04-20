<?php

namespace App\Jobs;

use App\Facades\Utils;
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
            $invoice->update([
                'status'          => 'error',
                'mysond_response' => json_encode(['error' => 'xml_content o invoice_name mancante']),
            ]);
            return;
        }

        $fileName = 'IT' . Utils::setting('company_vat_number') . '_' . $invoice->invoice_name . '.xml';

        try {
            $result = $service->importFeAttivo($invoice->xml_content, $fileName, true);
        } catch (Throwable $e) {
            Log::error('importFeAttivo exception: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
            $invoice->update([
                'status'          => 'error',
                'mysond_response' => json_encode(['exception' => $e->getMessage()]),
            ]);
            throw $e;
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

        if ($esito === 0) {
            $invoice->update([
                'status'          => 'sent',
                'sent_at'         => now(),
                'mysond_response' => json_encode($payload),
            ]);
        } else {
            $invoice->update([
                'status'          => 'error',
                'mysond_response' => json_encode($payload),
            ]);
        }
    }
}
