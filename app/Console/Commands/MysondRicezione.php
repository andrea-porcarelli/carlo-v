<?php

namespace App\Console\Commands;

use App\Models\ExternalInvoice;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Deved\FatturaElettronica\FatturaElettronica;
use Illuminate\Console\Command;
use App\Services\MysondFatturaService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MysondRicezione extends Command
{
    /**
     * firma: azione (invia|inviate|ricevute)
     * --file: percorso per l'invio
     * --da/--a: date per le liste
     */
    protected $signature = 'mysond:op {azione : invia, inviate, ricevute}
                            {--file= : Percorso del file XML (solo per invia)}
                            {--dal= : Data inizio YYYY-MM-DD}
                            {--al= : Data inizio YYYY-MM-DD}';

    protected $description = 'Tool di test completo per il Service Mysond Fatturazione Elettronica';

    protected $service;

    public function __construct(MysondFatturaService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $azione = $this->argument('azione');
        $dal = $this->option('dal') ?? now()->subDays(7)->format('Y-m-d');
        $al = $this->option('al') ?? now()->format('Y-m-d');

        try {
            switch ($azione) {
                case 'invia':
                    $this->testInvio();
                    break;
                case 'inviate':
                    $this->testLista('getFattureInviate', "Fatture ATTIVE inviate", $dal, $al);
                    break;
                case 'ricevute':
                    $this->testLista('riceviFatture', "Fatture PASSIVE ricevute", $dal, $al);
                    break;
                case 'azienda':
                    $this->testLista('getDatiAzienda', "Dati azienda", $dal, $al);
                    break;
                case 'inspect':
                    $this->service->exportWsdlStructure();
                    break;
                default:
                    $this->error("Azione non valida. Usa: invia, inviate o ricevute.");
            }
        } catch (Exception $e) {
            $this->error("Errore durante l'esecuzione:");
            $this->line($e->getMessage());
            if ($this->confirm('Vuoi vedere l\'ultimo XML SOAP inviato per debug?')) {
                $this->info($this->service->getLastRequest());
            }
        }
    }

    private function testInvio()
    {
        $path = $this->option('file');
        if (!$path || !file_exists($path)) {
            $this->error("Errore: devi specificare un file valido con --file=/percorso/file.xml");
            return;
        }

        $xml = file_get_contents($path);
        $nomeFile = basename($path);

        $this->info("Invio in corso di $nomeFile...");
        $res = $this->service->inviaFatturaAttiva($xml, $nomeFile);

        $this->success("Richiesta completata!");
        dump($res);
    }

    private function testLista($metodo, $titolo, $dal, $al)
    {
        $this->info("$titolo $dal al $al..");
        $risultati = $this->service->$metodo($dal, $al);

        if (empty($risultati)) {
            $this->warn("Nessun documento trovato.");
            return;
        }

        $headers = ['ID Doc', 'Nome File'. 'Link File', 'Stato', 'Data'];
        $rows = [];

        foreach ($risultati as $doc) {
            $rows[] = [
                $doc->code,
                $doc->docName,
                $doc->docDataLink,
                $doc->stato ?? 'N/D',
                $doc->lastUpdate,
            ];
        }
        if ($metodo === 'riceviFatture') {
            self::import_fatture($risultati);
        }

        $this->table($headers, $rows);
    }

    private static function import_fatture($risultati) {
        $failed = [];

        foreach ($risultati as $doc) {
            $contenuto = Http::get($doc->docDataLink)->body();
            $tempPath = storage_path("app/temp/".$doc->docName);
            file_put_contents($tempPath, $contenuto);
            $xmlPath = $tempPath;

            try {
                if (str_ends_with($doc->docName, '.p7m')) {
                    $xmlPath = self::estraiXmlDaP7m($tempPath);
                }
                echo $xmlPath . PHP_EOL;
                self::importaFattura($xmlPath);
            } catch (\Exception $e) {
                // Salva il file in una cartella persistente per il download
                $failedDir = storage_path("app/failed_invoices");
                if (!is_dir($failedDir)) {
                    mkdir($failedDir, 0755, true);
                }
                @copy($tempPath, $failedDir . '/' . $doc->docName);

                // Prova ad estrarre info base dall'XML per la visualizzazione
                $basicInfo = [];
                try {
                    $basicInfo = self::extractBasicInfo($xmlPath);
                } catch (\Throwable $ignored) {}

                $record = ExternalInvoice::updateOrCreate(
                    ['file_name' => $doc->docName],
                    [
                        'file_name'      => $doc->docName,
                        'sdi_identifier' => $doc->code ?? null,
                        'status'         => 'import_error',
                        'import_error'   => $e->getMessage(),
                        'supplier_name'  => $basicInfo['supplier_name'] ?? null,
                        'date'           => $basicInfo['date'] ?? null,
                        'total_amount'   => $basicInfo['total_amount'] ?? null,
                        'number'         => $basicInfo['number'] ?? null,
                        'file_path'      => 'failed_invoices/' . $doc->docName,
                    ]
                );

                $failed[] = $record;
            }
        }

        if (!empty($failed)) {
            self::notificaTelegram($failed);
        }
    }

    private static function notificaTelegram(array $failedInvoices): void
    {
        if (!config('logging.channels.telegram.handler_with.apiKey')) {
            return;
        }

        $count = count($failedInvoices);
        $lines = [];
        foreach ($failedInvoices as $invoice) {
            $fornitore = $invoice->supplier_name ?? 'Fornitore sconosciuto';
            $data      = $invoice->date ? $invoice->date->format('d/m/Y') : 'N/D';
            $importo   = $invoice->total_amount
                ? number_format($invoice->total_amount, 2, ',', '.') . ' €'
                : 'N/D';
            $lines[] = "📄 <b>{$invoice->file_name}</b>\n   👤 {$fornitore} | 📅 {$data} | 💰 {$importo}";
        }

        $message = "⚠️ <b>Fatture non importate</b> — {$count} "
            . ($count === 1 ? 'fattura' : 'fatture') . "\n\n"
            . implode("\n\n", $lines);

        Log::channel('telegram')->warning($message);
    }

    private static function extractBasicInfo(string $xmlPath): array
    {
        $content = @file_get_contents($xmlPath);
        if (!$content || !str_contains($content, '<?xml')) {
            return [];
        }

        $xml = @simplexml_load_string($content);
        if (!$xml) {
            return [];
        }

        $supplierName = null;
        $anagrafica = $xml->FatturaElettronicaHeader->CedentePrestatore->DatiAnagrafici->Anagrafica ?? null;
        if ($anagrafica) {
            $supplierName = (string)($anagrafica->Denominazione ?? '');
            if (!$supplierName) {
                $supplierName = trim(
                    (string)($anagrafica->Nome ?? '') . ' ' . (string)($anagrafica->Cognome ?? '')
                ) ?: null;
            }
        }

        $date = null;
        $totalAmount = null;
        $number = null;
        $datiDoc = $xml->FatturaElettronicaBody->DatiGenerali->DatiGeneraliDocumento ?? null;
        if ($datiDoc) {
            $date        = (string)($datiDoc->Data ?? '') ?: null;
            $totalAmount = (float)($datiDoc->ImportoTotaleDocumento ?? 0) ?: null;
            $number      = (string)($datiDoc->Numero ?? '') ?: null;
        }

        return [
            'supplier_name' => $supplierName,
            'date'          => $date,
            'total_amount'  => $totalAmount,
            'number'        => $number,
        ];
    }

    private static function estraiXmlDaP7m($path)
    {

        $content = file_get_contents($path);

        // Se il file è già XML
        if (str_starts_with(trim($content), '<?xml')) {
            return $path;
        }

        $output = str_replace('.p7m', '.xml', $path);

        // openssl in DER mode
        $command = "openssl smime -inform DER -verify -noverify -in "
            . escapeshellarg($path)
            . " -out "
            . escapeshellarg($output);

        exec($command, $out, $code);

        if ($code !== 0 || !file_exists($output)) {
            throw new \Exception("Errore estrazione p7m: verifica che il file sia valido DER");
        }

        return $output;
    }

    private static function importaFattura($path)
    {
        DB::transaction(function () use ($path) {

            $reader = new \XMLReader();
            $reader->open($path);
            echo "Fattura " . basename($path) . PHP_EOL;
            $invoiceData = [
                'file_name' => basename($path),
            ];

            $lines = [];
            $supplier = [];

            while ($reader->read()) {

                if ($reader->nodeType === \XMLReader::ELEMENT) {

                    switch ($reader->localName) {

                        case 'TipoDocumento':
                            $invoiceData['document_type'] = $reader->readString();
                            break;

                        case 'Divisa':
                            $invoiceData['currency'] = $reader->readString();
                            break;

                        case 'Numero':
                            $invoiceData['number'] = $reader->readString();
                            break;

                        case 'Data':
                            $invoiceData['date'] = $reader->readString();
                            break;

                        case 'ImportoTotaleDocumento':
                            $invoiceData['total_amount'] = $reader->readString();
                            break;

                        case 'CedentePrestatore':
                            $supplier = self::parseParty($reader);
                            break;


                        case 'DettaglioLinee':
                            $lines[] = self::parseLine($reader);
                            break;
                    }
                }
            }

            $reader->close();
            if ($supplier) {
                try {
                    $supplier = Supplier::updateOrCreate([
                        'fiscal_code' => $supplier['tax_code'],
                        'vat_number' => $supplier['vat_number'],
                    ], [
                        'fiscal_code' => $supplier['tax_code'],
                        'vat_number' => $supplier['vat_number'],
                        'address' => $supplier['address'],
                        'company_name' => $supplier['company_name'],
                        'street_number' => $supplier['street_number'],
                        'zip_code' => $supplier['zip_code'],
                        'city' => $supplier['city'],
                        'province' => $supplier['province'],
                        'nation' => $supplier['country'],
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    Log::info($e->getMessage());
                }
            }

            $invoice = SupplierInvoice::updateOrCreate([
                'supplier_id' => $supplier->id,
                'invoice_number' => $invoiceData['number'],
                'invoice_date' => $invoiceData['date'],
            ], [
                'supplier_id' => $supplier->id,
                'invoice_number' => $invoiceData['number'],
                'invoice_date' => $invoiceData['date'],
                'amount' => $invoiceData['total_amount'],
            ]);

            foreach ($lines as $line) {
                try {
                    $invoice->products()->create([
                        'product_name' => $line['description'],
                        'quantity' => $line['quantity'],
                        'price' => $line['unit_price'],
                        'iva' => $line['vat_rate'],
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    Log::info($e->getMessage());
                }
            }

        });
    }

    private static function parseParty(\XMLReader $reader): array
    {
        $xml = new \SimpleXMLElement($reader->readOuterXML());

        return [
            'company_name' => (string)($xml->DatiAnagrafici->Anagrafica->Denominazione ?? null),
            'first_name' => (string)($xml->DatiAnagrafici->Anagrafica->Nome ?? null),
            'last_name' => (string)($xml->DatiAnagrafici->Anagrafica->Cognome ?? null),
            'vat_number' => (string)($xml->DatiAnagrafici->IdFiscaleIVA->IdCodice ?? null),
            'tax_code' => (string)($xml->DatiAnagrafici->CodiceFiscale ?? null),
            'address' => (string)($xml->Sede->Indirizzo ?? null),
            'street_number' => (string)($xml->Sede->NumeroCivico ?? null),
            'zip_code' => (string)($xml->Sede->CAP ?? null),
            'city' => (string)($xml->Sede->Comune ?? null),
            'province' => (string)($xml->Sede->Provincia ?? null),
            'country' => (string)($xml->Sede->Nazione ?? null),
        ];
    }

    private static function parseLine(\XMLReader $reader): array
    {
        $xml = new \SimpleXMLElement($reader->readOuterXML());

        return [
            'line_number' => (int)($xml->NumeroLinea ?? null),
            'description' => (string)($xml->Descrizione ?? null),
            'quantity' => (float)($xml->Quantita ?? null),
            'unit_of_measure' => (string)($xml->UnitaMisura ?? null),
            'unit_price' => (float)($xml->PrezzoUnitario ?? null),
            'total_price' => (float)($xml->PrezzoTotale ?? null),
            'vat_rate' => (float)($xml->AliquotaIVA ?? null),
            'vat_nature' => (string)($xml->Natura ?? null),
        ];
    }
}
