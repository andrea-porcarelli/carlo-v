<?php

namespace App\Services;

use App\Exceptions\PendingSdiRejectionsException;
use App\Models\MirroredInvoice;
use App\Models\Setting;
use App\Models\TableOrderInvoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pull on-demand di tutte le fatture visibili sull'Azienda MySond verso la
 * tabella locale `mirrored_invoices`. MySond è autorevole; questa è una
 * proiezione consultabile senza altre chiamate SOAP per ogni refresh pagina.
 *
 * Trigger previsti:
 *   - all'apertura di /backoffice/accounting (lista fatture)
 *   - prima di ogni emissione (vedi callsite TableOrderController.payTableInvoice
 *     e QuickInvoiceWizard.submit) — chiamato via runOrThrow()
 *
 * Responsabilità in un singolo run:
 *   1. sync() — upsert mirrored_invoices dal feed MySond, riconciliando lo
 *      stato sulle TableOrderInvoice locali matching.
 *   2. allinea il contatore locale `invoice_counter` al massimo Numero su
 *      MySond per evitare collisioni con fatture emesse dall'altro progetto.
 *   3. notifica Telegram quando trova scartate nuove.
 *
 * runOrThrow() aggiunge un quarto step opzionale: lancia
 * PendingSdiRejectionsException se ci sono scartate non riconosciute. Usata
 * dai callsite di emissione, non dalla view-only.
 *
 * Tutte le interazioni SOAP sono fail-soft: se MySond è irraggiungibile
 * lasciamo lo stato locale invariato e l'app prosegue.
 */
class MysondInvoiceMirror
{
    public function __construct(private readonly MysondFatturaService $mysond)
    {
    }

    public function sync(?int $year = null): void
    {
        if (! $this->mysond->isConfigured()) {
            return;
        }

        $year ??= (int) now()->format('Y');

        try {
            $items = $this->mysond->getFeInviateLink($year);
        } catch (Throwable $e) {
            Log::warning('MysondInvoiceMirror: SOAP probe failed', [
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if (empty($items)) {
            return;
        }

        // Primo run: importiamo lo storico come auto-ack-ato così non blocca.
        $bootstrap = MirroredInvoice::count() === 0;

        $newRejections = [];
        $maxNumero = null;
        // Cap idratazioni per sync: ogni download è 1 HTTP + eventuale
        // unwrap p7m (SOAP), quindi con 100 fatture nuove diventa lentissimo.
        // Bootstrap → skip totale (l'utente ha già i metadati; XML on-demand).
        // Post-bootstrap → cap 10: le nuove arrivano poche per volta, e le
        // vecchie senza xml si idratano al primo click "XML"/"Nota di credito".
        $hydrateBudget = $bootstrap ? 0 : 10;

        foreach ($items as $item) {
            $fileName = (string) ($item->docName ?? $item->fileName ?? '');
            if ($fileName === '') {
                continue;
            }
            $fileName = preg_replace('/\.xml$/i', '', $fileName);

            $stato = isset($item->stato) && is_numeric($item->stato) ? (int) $item->stato : null;
            $code  = isset($item->code) ? (string) $item->code : null;

            // Match con eventuale fattura locale per popolare local_invoice_id
            // e mantenere allineato sdi_status sulle TableOrderInvoice.
            $localInvoice = $this->matchLocalInvoice($fileName);

            $payload = [
                'file_name'       => $fileName,
                'mysond_code'     => $code,
                'mysond_date'     => $this->itemDate($item),
                'mysond_total'    => $this->itemTotal($item),
                'customer_name'   => $this->itemString($item, ['intestazione', 'denominazione', 'destinatario']),
                'customer_vat'    => $this->itemString($item, ['piva', 'partitaIva', 'pivaCessionario']),
                'customer_cf'     => $this->itemString($item, ['cf', 'codiceFiscale', 'cfCessionario']),
                'stato'           => $stato,
                'stato_label'     => $stato !== null ? TableOrderInvoice::sdiStatusLabel($stato) : null,
                'local_invoice_id' => $localInvoice?->id,
                'last_synced_at'  => now(),
            ];

            $existing = MirroredInvoice::where('file_name', $fileName)->first();

            if ($existing) {
                $wasPendingRejection = $existing->isPendingAck();
                // Preserva su update i campi arricchiti da XML (customer_name/vat/cf,
                // mysond_total) quando il payload sync li ha nulli: docFeLink non
                // li espone, quindi sovrascriverli azzererebbe i dati già idratati.
                foreach (['customer_name', 'customer_vat', 'customer_cf', 'mysond_total'] as $k) {
                    if (($payload[$k] ?? null) === null && $existing->{$k} !== null) {
                        unset($payload[$k]);
                    }
                }
                $existing->fill($payload)->save();
            } else {
                $payload['first_synced_at'] = now();
                if ($bootstrap) {
                    $payload['acknowledged_at']   = now();
                    $payload['acknowledged_by']   = 'system';
                    $payload['acknowledged_note'] = 'Pre-esistente all\'attivazione del controllo SDI';
                }
                $existing = MirroredInvoice::create($payload);

                if (! $bootstrap && $existing->isPendingAck()) {
                    $newRejections[] = $existing;
                }
                $wasPendingRejection = false;
            }

            // Idratazione XML: docFeLink espone solo metadati minimi
            // (code/date/stato/link) — cliente e totale sono nel FatturaPA
            // scaricato via docDataLink. Il budget conta i tentativi, non i
            // successi: così una riga con link/p7m rotto non consuma tempo a
            // ripetizione (verrà ritentata al refresh successivo — oppure
            // idratata al volo dai bottoni XML/Nota di credito).
            if ($hydrateBudget > 0 && $existing->xml_content === null && !empty($item->docDataLink ?? null)) {
                $hydrateBudget--;
                $this->downloadXmlAndHydrate($existing, $item);
            }

            // Riconcilia lo stato SDI sulla TableOrderInvoice locale, se esiste:
            // mantiene allineato anche il vecchio modello senza dover chiamare
            // mysond:refresh-sdi separatamente.
            //
            // Eccezione: se lo stato locale è già terminale positivo (Consegnata / Accettata),
            // non lo degradiamo. In caso di doppio importFeAttivo sullo stesso file
            // (primo consegnato, secondo scartato come duplicato) MySond restituisce
            // l'esito del secondo tentativo: sovrascrivere annullerebbe l'esito buono
            // riconosciuto in precedenza (anche via adozione manuale).
            $isTerminalPositive = $localInvoice
                && in_array((int) $localInvoice->sdi_status, TableOrderInvoice::SDI_TERMINAL_POSITIVE, true);

            if ($localInvoice && $stato !== null && (int) $localInvoice->sdi_status !== $stato && !$isTerminalPositive) {
                $localInvoice->update([
                    'sdi_status'       => $stato,
                    'sdi_status_label' => TableOrderInvoice::sdiStatusLabel($stato),
                    'sdi_checked_at'   => now(),
                ]);
            }

            // Traccia max numero per il sync contatore.
            if ($code !== null) {
                $n = $this->numeroToInt($code);
                if ($n !== null && ($maxNumero === null || $n > $maxNumero)) {
                    $maxNumero = $n;
                }
            }
        }

        $this->syncCounter($maxNumero);

        if (! empty($newRejections)) {
            $this->notifyTelegram($newRejections);
        }
    }

    /**
     * Esegui sync e lancia PendingSdiRejectionsException se ci sono scartate
     * non riconosciute. Da usare nei callsite di emissione.
     */
    public function runOrThrow(): void
    {
        $this->sync();

        if (! config('services.mysond.block_on_unack_rejections', true)) {
            return;
        }

        $pending = MirroredInvoice::pendingAck()->orderBy('first_synced_at')->get();
        if ($pending->isNotEmpty()) {
            throw new PendingSdiRejectionsException($pending);
        }
    }

    /**
     * Scarica l'XML della fattura via `docDataLink` (URL restituito da MySond
     * in docFeLink), sbusta il .p7m se necessario e idrata mirrored_invoices
     * con customer_name/vat/cf e mysond_total estratti dal FatturaElettronica.
     *
     * Fail-soft: se il download o il parsing fallisce, il record resta senza
     * xml e ritentiamo al prossimo trigger. Ritorna true solo se abbiamo
     * effettivamente scritto xml_content (utile al chiamante per rispettare
     * un budget di idratazioni per run).
     *
     * @param \stdClass|null $item item docFeLink corrispondente (per evitare
     *                             un secondo giro SOAP); se null, lo recupera.
     */
    public function downloadXmlAndHydrate(MirroredInvoice $mirrored, $item = null): bool
    {
        if (! $this->mysond->isConfigured()) {
            return false;
        }

        if ($item === null) {
            $year = $mirrored->mysond_date
                ? (int) $mirrored->mysond_date->format('Y')
                : (int) now()->format('Y');
            try {
                $records = $this->mysond->listInviateByFileName($mirrored->file_name, $year);
            } catch (Throwable $e) {
                Log::warning('MysondInvoiceMirror: listInviateByFileName failed', [
                    'file_name' => $mirrored->file_name,
                    'year'      => $year,
                    'error'     => $e->getMessage(),
                ]);
                return false;
            }
            $item = $records[0] ?? null;
        }

        if ($item === null) {
            return false;
        }

        $link    = (string) ($item->docDataLink ?? '');
        $docName = (string) ($item->docName ?? '');
        if ($link === '') {
            return false;
        }

        try {
            $response = Http::timeout(10)->get($link);
            if (! $response->successful()) {
                Log::warning('MysondInvoiceMirror: docDataLink HTTP not successful', [
                    'file_name' => $mirrored->file_name,
                    'status'    => $response->status(),
                ]);
                return false;
            }
            $body = (string) $response->body();
        } catch (Throwable $e) {
            Log::warning('MysondInvoiceMirror: docDataLink download failed', [
                'file_name' => $mirrored->file_name,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }

        if ($body === '') {
            return false;
        }

        $isP7m = str_ends_with(strtolower($docName), '.p7m')
            || ! $this->looksLikeXml($body);

        if ($isP7m) {
            try {
                $body = (string) $this->mysond->getXmlFromP7m($body);
            } catch (Throwable $e) {
                Log::warning('MysondInvoiceMirror: p7m unwrap failed', [
                    'file_name' => $mirrored->file_name,
                    'error'     => $e->getMessage(),
                ]);
                return false;
            }
            if (! $this->looksLikeXml($body)) {
                return false;
            }
        }

        $hydrated = ['xml_content' => $body, 'xml_fetched_at' => now()];
        $hydrated = array_merge($hydrated, $this->extractInvoiceMetaFromXml($body));

        // Non azzeriamo i campi già valorizzati (es. da tentativi precedenti
        // o dal payload docFeLink) se il parser XML non li ha trovati.
        foreach (['customer_name', 'customer_vat', 'customer_cf', 'mysond_total'] as $k) {
            if (($hydrated[$k] ?? null) === null && $mirrored->{$k} !== null) {
                unset($hydrated[$k]);
            }
        }

        $mirrored->fill($hydrated)->save();
        return true;
    }

    private function looksLikeXml(string $data): bool
    {
        $head = ltrim(substr($data, 0, 200));
        return str_starts_with($head, '<?xml') || str_starts_with($head, '<');
    }

    /**
     * Estrae denominazione cliente + P.IVA/CF + totale documento da un XML
     * FatturaPA. Namespace-agnostico: strippa prefissi (`p:FatturaElettronica`
     * → `FatturaElettronica`) e attributi xmlns per evitare i pattern rigidi
     * di SimpleXML sui namespace default/prefixed.
     *
     * @return array{customer_name?: string|null, customer_vat?: string|null, customer_cf?: string|null, mysond_total?: float|null}
     */
    private function extractInvoiceMetaFromXml(string $xmlContent): array
    {
        $stripped = preg_replace('#(</?)[A-Za-z_][A-Za-z0-9_\-]*:#', '$1', $xmlContent);
        $stripped = preg_replace('#\s+xmlns(:[A-Za-z0-9_\-]+)?\s*=\s*"[^"]*"#', '', $stripped);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($stripped);
        libxml_clear_errors();
        if ($xml === false) {
            return [];
        }

        $out = [];

        $ces = $xml->FatturaElettronicaHeader->CessionarioCommittente ?? null;
        if ($ces) {
            $den   = trim((string) ($ces->DatiAnagrafici->Anagrafica->Denominazione ?? ''));
            $nome  = trim((string) ($ces->DatiAnagrafici->Anagrafica->Nome ?? ''));
            $cog   = trim((string) ($ces->DatiAnagrafici->Anagrafica->Cognome ?? ''));
            $fullName = $den !== '' ? $den : trim($nome . ' ' . $cog);
            if ($fullName !== '') {
                $out['customer_name'] = $fullName;
            }

            $piva = trim((string) ($ces->DatiAnagrafici->IdFiscaleIVA->IdCodice ?? ''));
            if ($piva !== '') {
                $out['customer_vat'] = $piva;
            }

            $cf = trim((string) ($ces->DatiAnagrafici->CodiceFiscale ?? ''));
            if ($cf !== '') {
                $out['customer_cf'] = $cf;
            }
        }

        // FatturaElettronicaBody può ripetersi (fatture riepilogative): sommiamo
        // gli ImportoTotaleDocumento di ciascun body.
        $total = null;
        foreach ($xml->FatturaElettronicaBody ?? [] as $b) {
            $imp = (string) ($b->DatiGenerali->DatiGeneraliDocumento->ImportoTotaleDocumento ?? '');
            if ($imp !== '' && is_numeric($imp)) {
                $total = ($total ?? 0.0) + (float) $imp;
            }
        }
        if ($total !== null) {
            $out['mysond_total'] = round($total, 2);
        }

        return $out;
    }

    private function syncCounter(?int $maxNumero): void
    {
        if ($maxNumero === null) {
            return;
        }
        if (! config('services.mysond.sync_counter_on_issue', true)) {
            return;
        }

        $local = (int) Setting::get('invoice_counter', 0);
        if ($maxNumero > $local) {
            Log::info('MysondInvoiceMirror: bumping invoice_counter to match MySond', [
                'from' => $local,
                'to'   => $maxNumero,
            ]);
            Setting::set('invoice_counter', $maxNumero, 'integer');
        }
    }

    private function matchLocalInvoice(string $fileName): ?TableOrderInvoice
    {
        // Convenzione: MySond accumula i file come "IT{vat}_{invoice_name}".
        // Estraiamo invoice_name e cerchiamo nella locale.
        if (preg_match('/_([A-Z0-9]+)$/i', $fileName, $m)) {
            return TableOrderInvoice::where('invoice_name', $m[1])->first();
        }
        return null;
    }

    private function itemDate($item): ?string
    {
        $date = $item->date ?? $item->dateLong ?? null;
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        if (is_int($date) || (is_string($date) && ctype_digit($date))) {
            $ts = (int) $date;
            if ($ts > 10_000_000_000) {
                $ts = (int) ($ts / 1000);
            }
            return date('Y-m-d', $ts);
        }
        if (is_string($date) && preg_match('/(\d{4}-\d{2}-\d{2})/', $date, $m)) {
            return $m[1];
        }
        return null;
    }

    private function itemTotal($item): ?float
    {
        foreach (['importoTotale', 'totale', 'importo'] as $k) {
            if (isset($item->{$k}) && is_numeric($item->{$k})) {
                return (float) $item->{$k};
            }
        }
        return null;
    }

    private function itemString($item, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($item->{$k}) && $item->{$k} !== '') {
                return (string) $item->{$k};
            }
        }
        return null;
    }

    private function numeroToInt(string $numero): ?int
    {
        if (preg_match('/-(\d+)\s*$/', $numero, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^\s*(\d+)/', $numero, $m)) {
            return (int) $m[1];
        }
        if (preg_match_all('/\d+/', $numero, $m)) {
            return max(array_map('intval', $m[0]));
        }
        return null;
    }

    /**
     * @param array<int, MirroredInvoice> $newRejections
     */
    private function notifyTelegram(array $newRejections): void
    {
        if (! config('logging.channels.telegram.handler_with.apiKey')) {
            return;
        }

        $lines = [
            '🚨 <b>Scartate SDI rilevate su MySond</b> (' . count($newRejections) . ')',
            '',
        ];
        foreach ($newRejections as $r) {
            $lines[] = sprintf(
                '• <code>%s</code> — n. %s — %s',
                htmlspecialchars($r->file_name, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($r->mysond_code ?? '?'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($r->stato_label ?? ''), ENT_QUOTES, 'UTF-8'),
            );
        }
        $lines[] = '';
        $lines[] = '⛔️ Nuove emissioni fattura bloccate finché non vengono riconosciute dal backoffice.';

        try {
            Log::channel('telegram')->warning(implode("\n", $lines));
        } catch (Throwable $e) {
            Log::warning('MysondInvoiceMirror: Telegram notify failed', ['err' => $e->getMessage()]);
        }
    }
}
