<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Jobs\SendInvoiceToMysondJob;
use App\Models\Customer;
use App\Models\InvoiceMysondLog;
use App\Models\MirroredInvoice;
use App\Models\TableOrderInvoice;
use App\Services\MysondFatturaService;
use App\Services\MysondInvoiceMirror;
use App\Services\SdiStatusNotifier;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AccountingController extends BaseController
{
    use DatatableTrait;

    protected string $name = 'accounting.invoices';

    public function invoices(MysondInvoiceMirror $mirror): View
    {
        // All'apertura della sezione fatture sincronizziamo il mirror locale
        // con MySond — single source of truth per stato SDI, contatore e
        // visibilità delle fatture emesse anche dall'altro progetto.
        // Fail-soft internamente: se MySond è giù la pagina si apre comunque.
        $mirror->sync();

        $customers = Customer::orderBy('full_name')->get(['id', 'full_name'])->map(function ($c) {
            return ['id' => $c->id, 'label' => $c->full_name];
        })->toArray();

        // Fatture viste su MySond ma non emesse da Carlo V (carlo-v o altri
        // software della stessa azienda, o emesse manualmente da MySond).
        $externalInvoices = MirroredInvoice::whereNull('local_invoice_id')
            ->orderByDesc('mysond_date')
            ->limit(100)
            ->get();

        $pendingAcks = MirroredInvoice::pendingAck()
            ->orderBy('first_synced_at')
            ->get();

        return view('backoffice.accounting.invoices.index', compact(
            'customers', 'externalInvoices', 'pendingAcks'
        ));
    }

    public function ackMirroredRejection(Request $request, MirroredInvoice $mirrored)
    {
        abort_unless($mirrored->isRejected(), 422, 'Fattura non in stato di scarto.');

        $data = $request->validate([
            'note' => 'required|string|min:5|max:500',
        ]);

        $mirrored->update([
            'acknowledged_at'   => now(),
            'acknowledged_by'   => auth()->user()?->email ?: 'admin',
            'acknowledged_note' => $data['note'],
        ]);

        return back()->with('flash', 'Scartata riconosciuta. Emissioni nuovamente sbloccate (se non ce ne sono altre pendenti).');
    }

    public function mirroredXml(MirroredInvoice $mirrored)
    {
        abort_if(empty($mirrored->xml_content), 404, 'XML non ancora scaricato. Funzione lazy download da implementare.');

        return response($mirrored->xml_content, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$mirrored->file_name.'.xml"',
        ]);
    }

    /**
     * Render the quick-invoice wizard page (standalone invoice issuance).
     */
    public function createInvoice(): View
    {
        return view('backoffice.accounting.invoices.create');
    }

    public function editInvoice(TableOrderInvoice $invoice): View
    {
        abort_unless($invoice->isEditable(), 403, 'Solo le fatture Scartate o in errore possono essere modificate.');
        return view('backoffice.accounting.invoices.edit', compact('invoice'));
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            $query = TableOrderInvoice::with(['customer', 'tableOrder'])
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            if (!empty($filters['customer_id'])) {
                $query->where('customer_id', $filters['customer_id']);
            }
            if (!empty($filters['invoice_code'])) {
                $q = $filters['invoice_code'];
                $query->where(function ($sub) use ($q) {
                    $sub->where('invoice_code', 'like', "%{$q}%")
                        ->orWhere('invoice_name', 'like', "%{$q}%");
                });
            }
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            return datatables()->eloquent($query)
                ->order(function () {
                    // Ordinamento forzato a livello di query: created_at DESC.
                    // Sovrascrive il default ordering di yajra che applica i parametri DataTables.
                })
                ->addColumn('code', function ($item) {
                    $name = $item->invoice_name ? ' <small class="text-muted">(' . $item->invoice_name . ')</small>' : '';
                    return '<strong>' . e($item->invoice_code) . '</strong>' . $name;
                })
                ->addColumn('customer_name', function ($item) {
                    return $item->customer->full_name ?? '—';
                })
                ->addColumn('amount_fmt', function ($item) {
                    return number_format((float) $item->amount, 2, ',', '.') . ' €';
                })
                ->addColumn('status_badge', function ($item) {
                    return match ($item->status) {
                        'sent'    => '<span class="label label-success">Inviata</span>',
                        'error'   => '<span class="label label-danger">Errore</span>',
                        default   => '<span class="label label-warning">In coda</span>',
                    };
                })
                ->editColumn('created_at', function ($item) {
                    return $item->created_at ? $item->created_at->format('d/m/Y H:i') : '—';
                })
                ->editColumn('sent_at', function ($item) {
                    return $item->sent_at ? $item->sent_at->format('d/m/Y H:i') : '—';
                })
                ->addColumn('mysond_desc', function ($item) {
                    if (empty($item->mysond_response)) {
                        return '—';
                    }
                    $payload = json_decode($item->mysond_response, true) ?: [];
                    $desc = $payload['descrizione'] ?? ($payload['message'] ?? '');
                    $cod  = $payload['codice'] ?? null;
                    $text = $desc;
                    if ($cod) {
                        $text .= ' (cod. ' . $cod . ')';
                    }
                    return '<small>' . e($text) . '</small>';
                })
                ->addColumn('action', function ($item) {
                    $xmlBtn = '';
                    if (!empty($item->xml_content)) {
                        $xmlBtn = '<a href="' . route('accounting.invoices.xml', $item->id) . '" target="_blank" class="btn btn-xs btn-info" title="Apri XML"><i class="fa fa-file-code"></i></a> '
                                . '<a href="' . route('accounting.invoices.xml-download', $item->id) . '" class="btn btn-xs btn-info" title="Scarica XML"><i class="fa fa-download"></i></a> '
                                . '<a href="' . route('accounting.invoices.pdf', $item->id) . '" target="_blank" class="btn btn-xs btn-danger" title="PDF"><i class="fa fa-file-pdf"></i></a> ';
                    }
                    $logBtn = '<button class="btn btn-xs btn-default btn-show-mysond-logs" data-id="' . $item->id . '" title="Log invio MySond"><i class="fa fa-list-alt"></i></button> ';
                    $editBtn = '';
                    if ($item->isEditable()) {
                        $editBtn = '<a href="' . route('accounting.invoices.edit', $item->id) . '" class="btn btn-xs btn-success" title="Modifica e re-invia"><i class="fa fa-pencil"></i></a> ';
                    }
                    $resendBtn = '';
                    if (in_array($item->status, ['error', 'pending'])) {
                        $title = !empty($item->xml_content) ? 'Re-invia' : 'Rigenera XML e invia';
                        $resendBtn = '<button class="btn btn-xs btn-warning btn-resend-invoice" data-id="' . $item->id . '" title="' . $title . '"><i class="fa fa-paper-plane"></i></button> ';
                    }
                    $sdiBtn = '';
                    if ($item->status === 'sent' && !empty($item->invoice_name)) {
                        $sdiBtn = '<button class="btn btn-xs btn-primary btn-refresh-sdi" data-id="' . $item->id . '" title="Aggiorna esito SDI (ultima notifica)"><i class="fa fa-sync"></i></button> ';
                    }
                    $inspectBtn = '';
                    if (!empty($item->invoice_name)) {
                        $inspectBtn = '<button class="btn btn-xs btn-info btn-inspect-mysond" data-id="' . $item->id . '" title="Ispeziona storico invii su MySond"><i class="fa fa-search"></i></button>';
                    }
                    return $xmlBtn . $logBtn . $editBtn . $resendBtn . $sdiBtn . $inspectBtn;
                })
                ->addColumn('sdi_status_label_fmt', function ($item) {
                    if ($item->sdi_status === null) {
                        return '—';
                    }
                    $label = e($item->sdi_status_label ?? ('Stato ' . $item->sdi_status));
                    $class = match (true) {
                        in_array($item->sdi_status, [7, 9]) => 'label-success',
                        in_array($item->sdi_status, [1, 6, 10]) => 'label-danger',
                        in_array($item->sdi_status, [8, 11, 12]) => 'label-warning',
                        default => 'label-default',
                    };
                    $when = $item->sdi_checked_at
                        ? '<div class="text-muted" style="font-size:11px; white-space:nowrap; margin-top:3px;">' . $item->sdi_checked_at->format('d/m/Y H:i') . '</div>'
                        : '';
                    return '<div style="white-space:nowrap;"><span class="label ' . $class . '">' . $label . '</span></div>' . $when;
                })
                ->rawColumns(['code', 'status_badge', 'mysond_desc', 'sdi_status_label_fmt', 'action'])
                ->make(true);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function xml(TableOrderInvoice $invoice): Response
    {
        if (empty($invoice->xml_content)) {
            abort(404, 'XML non disponibile per questa fattura.');
        }
        return response($invoice->xml_content, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'inline; filename="' . $this->sdiFilename($invoice) . '"');
    }

    public function xmlDownload(TableOrderInvoice $invoice): Response
    {
        if (empty($invoice->xml_content)) {
            abort(404, 'XML non disponibile per questa fattura.');
        }
        return response($invoice->xml_content, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename="' . $this->sdiFilename($invoice) . '"');
    }

    private function sdiFilename(TableOrderInvoice $invoice): string
    {
        $vat  = Utils::setting('company_vat_number');
        $base = $invoice->invoice_name ?: ('fattura-' . $invoice->id);
        return ($vat ? 'IT' . $vat . '_' : '') . $base . '.xml';
    }

    /**
     * Render the invoice as PDF using the dedicated accounting template.
     */
    public function pdf(TableOrderInvoice $invoice)
    {
        if (empty($invoice->xml_content)) {
            abort(404, 'XML non disponibile — impossibile generare il PDF.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($invoice->xml_content);
        abort_if($xml === false, 422, 'XML non valido.');

        $header      = $xml->FatturaElettronicaHeader;
        $body        = $xml->FatturaElettronicaBody;
        $cedente     = $header->CedentePrestatore;
        $committente = $header->CessionarioCommittente;
        $datiDoc     = $body->DatiGenerali->DatiGeneraliDocumento;

        $cedenteName = (string)($cedente->DatiAnagrafici->Anagrafica->Denominazione ?? '');
        if (!$cedenteName) {
            $cedenteName = trim((string)($cedente->DatiAnagrafici->Anagrafica->Nome ?? '') . ' ' .
                                (string)($cedente->DatiAnagrafici->Anagrafica->Cognome ?? ''));
        }
        $committenteName = (string)($committente->DatiAnagrafici->Anagrafica->Denominazione ?? '');
        if (!$committenteName) {
            $committenteName = trim((string)($committente->DatiAnagrafici->Anagrafica->Nome ?? '') . ' ' .
                                    (string)($committente->DatiAnagrafici->Anagrafica->Cognome ?? ''));
        }

        $linee = [];
        foreach ($body->DatiBeniServizi->DettaglioLinee as $linea) {
            $linee[] = [
                'numero'          => (string)$linea->NumeroLinea,
                'descrizione'     => (string)$linea->Descrizione,
                'quantita'        => (float)($linea->Quantita ?? 0),
                'unita'           => (string)($linea->UnitaMisura ?? ''),
                'prezzo_unitario' => (float)($linea->PrezzoUnitario ?? 0),
                'prezzo_totale'   => (float)($linea->PrezzoTotale ?? 0),
                'iva'             => (float)($linea->AliquotaIVA ?? 0),
            ];
        }

        $riepilogo = [];
        foreach (($body->DatiBeniServizi->DatiRiepilogo ?? []) as $r) {
            $riepilogo[] = [
                'aliquota'   => (float)$r->AliquotaIVA,
                'imponibile' => (float)$r->ImponibileImporto,
                'imposta'    => (float)($r->Imposta ?? 0),
            ];
        }

        $pagamento = null;
        if (isset($body->DatiPagamento->DettaglioPagamento)) {
            $dp = $body->DatiPagamento->DettaglioPagamento;
            $pagamento = [
                'modalita' => (string)($dp->ModalitaPagamento ?? ''),
                'scadenza' => (string)($dp->DataScadenzaPagamento ?? ''),
                'importo'  => (float)($dp->ImportoPagamento ?? 0),
            ];
        }

        $invoice->loadMissing('customer');

        $data = [
            'invoice'     => $invoice,
            'cedente'     => [
                'nome'      => $cedenteName,
                'piva'      => (string)($cedente->DatiAnagrafici->IdFiscaleIVA->IdCodice ?? ''),
                'indirizzo' => (string)($cedente->Sede->Indirizzo ?? ''),
                'cap'       => (string)($cedente->Sede->CAP ?? ''),
                'comune'    => (string)($cedente->Sede->Comune ?? ''),
                'provincia' => (string)($cedente->Sede->Provincia ?? ''),
            ],
            'committente' => [
                'nome'      => $committenteName,
                'piva'      => (string)($committente->DatiAnagrafici->IdFiscaleIVA->IdCodice ?? ''),
                'indirizzo' => (string)($committente->Sede->Indirizzo ?? ''),
                'cap'       => (string)($committente->Sede->CAP ?? ''),
                'comune'    => (string)($committente->Sede->Comune ?? ''),
                'provincia' => (string)($committente->Sede->Provincia ?? ''),
            ],
            'documento'   => [
                'numero' => (string)$datiDoc->Numero,
                'data'   => (string)$datiDoc->Data,
                'tipo'   => (string)($datiDoc->TipoDocumento ?? 'TD01'),
                'divisa' => (string)($datiDoc->Divisa ?? 'EUR'),
                'totale' => (float)($datiDoc->ImportoTotaleDocumento ?? 0),
            ],
            'linee'     => $linee,
            'riepilogo' => $riepilogo,
            'pagamento' => $pagamento,
        ];

        $filename = ($invoice->invoice_name ?? 'fattura') . '.pdf';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('backoffice.accounting.invoices.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }

    /**
     * Aggiorna lo stato SDI di una fattura interrogando MySond (getNotifica).
     * Il tentativo viene tracciato in invoice_mysond_logs come l'importFeAttivo.
     */
    public function refreshSdi(TableOrderInvoice $invoice): JsonResponse
    {
        if (empty($invoice->invoice_name)) {
            return $this->error(['message' => 'invoice_name mancante — impossibile interrogare MySond.']);
        }

        $vatDigits = \App\Helpers\VatHelper::sanitize(\App\Facades\Utils::setting('company_vat_number'));
        $fileName  = 'IT' . $vatDigits . '_' . $invoice->invoice_name;

        $service   = app(MysondFatturaService::class);
        $startedAt = microtime(true);
        $notifica  = null;
        $exception = null;

        try {
            $notifica = $service->getNotifica($fileName);
        } catch (\Throwable $e) {
            $exception = $e;
            Log::error('refreshSdi error', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
        }

        $durationMs  = (int) round((microtime(true) - $startedAt) * 1000);
        $requestXml  = $service->getLastRequestXml();
        $responseXml = $service->getLastResponseXml();

        if ($exception !== null) {
            InvoiceMysondLog::create([
                'table_order_invoice_id' => $invoice->id,
                'operation'              => 'getNotifica',
                'outcome'                => InvoiceMysondLog::OUTCOME_EXCEPTION,
                'request_xml'            => $requestXml,
                'response_xml'           => $responseXml,
                'exception_class'        => get_class($exception),
                'exception_message'      => $exception->getMessage(),
                'exception_trace'        => $exception->getTraceAsString(),
                'duration_ms'            => $durationMs,
            ]);
            return $this->error(['message' => 'Errore interrogazione MySond: ' . $exception->getMessage()]);
        }

        if ($notifica === null) {
            InvoiceMysondLog::create([
                'table_order_invoice_id' => $invoice->id,
                'operation'              => 'getNotifica',
                'outcome'                => InvoiceMysondLog::OUTCOME_ERROR,
                'descrizione'            => 'Nessuna notifica disponibile per ' . $fileName,
                'request_xml'            => $requestXml,
                'response_xml'           => $responseXml,
                'duration_ms'            => $durationMs,
            ]);
            return $this->error(['message' => 'Nessuna notifica disponibile.']);
        }

        // I campi esatti dell'oggetto Notifica non sono documentati ma osservati
        // comunemente: `tipo`/`tipoNotifica` (RC/NS/MC/...), `stato`/`esito` numerico,
        // `descrizione`, `data`/`dataNotifica`. Estraiamo difensivamente.
        $code = null;
        foreach (['stato', 'esito', 'codice', 'codiceEsito'] as $k) {
            if (isset($notifica->{$k}) && is_numeric($notifica->{$k})) {
                $code = (int) $notifica->{$k};
                break;
            }
        }
        $descrizione = $notifica->descrizione ?? ($notifica->messaggio ?? null);
        $tipo        = $notifica->tipo ?? ($notifica->tipoNotifica ?? null);

        $label = TableOrderInvoice::sdiStatusLabel($code);
        if (!$label && $tipo) {
            $label = (string) $tipo;
        }

        $previousStatus = $invoice->sdi_status;
        $statusChanged  = $code !== null && $previousStatus !== $code;

        // Non degradare da terminale positivo (Consegnata/Accettata) a scartata:
        // getNotifica restituisce l'ultima notifica per fileName e in caso di
        // doppio importFeAttivo darebbe l'esito del duplicato scartato.
        $isTerminalPositive = in_array((int) $previousStatus, TableOrderInvoice::SDI_TERMINAL_POSITIVE, true);
        $skipDowngrade      = $isTerminalPositive && $code !== null && !in_array($code, TableOrderInvoice::SDI_TERMINAL_POSITIVE, true);

        if ($skipDowngrade) {
            InvoiceMysondLog::create([
                'table_order_invoice_id' => $invoice->id,
                'operation'              => 'getNotifica',
                'outcome'                => InvoiceMysondLog::OUTCOME_ERROR,
                'esito'                  => $code,
                'codice'                 => $tipo ? (string) $tipo : null,
                'descrizione'            => sprintf(
                    'Downgrade ignorato: locale già %s (%d), MySond ha risposto %s (%d). Usare "Ispeziona su MySond" per vedere lo storico.',
                    TableOrderInvoice::sdiStatusLabel($previousStatus) ?? '',
                    $previousStatus,
                    $label ?? '',
                    $code
                ),
                'request_xml'            => $requestXml,
                'response_xml'           => $responseXml,
                'duration_ms'            => $durationMs,
            ]);
            return $this->success([
                'message' => 'Nessun aggiornamento: la fattura è già in stato terminale ('
                    . (TableOrderInvoice::sdiStatusLabel($previousStatus) ?? 'Consegnata')
                    . '). L\'ultima notifica MySond (' . ($label ?? 'sconosciuta') . ') non retrograda lo stato locale.',
                'status'  => $previousStatus,
                'label'   => TableOrderInvoice::sdiStatusLabel($previousStatus),
            ]);
        }

        $invoice->update([
            'sdi_status'       => $code,
            'sdi_status_label' => $label,
            'sdi_checked_at'   => now(),
            'sdi_response'     => json_encode([
                'tipo'        => $tipo,
                'stato'       => $code,
                'descrizione' => $descrizione,
                'raw'         => json_decode(json_encode($notifica), true),
                'at'          => now()->toIso8601String(),
            ]),
        ]);

        if ($statusChanged) {
            $invoice->loadMissing('customer');
            SdiStatusNotifier::notifyStatusChange($invoice, $previousStatus, $code, $label, $descrizione);
        }

        InvoiceMysondLog::create([
            'table_order_invoice_id' => $invoice->id,
            'operation'              => 'getNotifica',
            'outcome'                => InvoiceMysondLog::OUTCOME_SUCCESS,
            'esito'                  => $code,
            'codice'                 => $tipo ? (string) $tipo : null,
            'descrizione'            => $descrizione ?: $label,
            'request_xml'            => $requestXml,
            'response_xml'           => $responseXml,
            'duration_ms'            => $durationMs,
        ]);

        return $this->success([
            'message' => 'Esito SDI aggiornato: ' . ($label ?? 'sconosciuto'),
            'status'  => $code,
            'label'   => $label,
        ]);
    }

    /**
     * Ispeziona su MySond tutti i record docFeLink che matchano il fileName
     * della fattura. Serve quando importFeAttivo è stato eseguito più volte
     * sullo stesso file (consegnato la prima volta, scartato la seconda come
     * duplicato): getNotifica restituisce solo l'ultimo esito, questo metodo
     * mostra la storia completa e permette di adottare l'esito buono.
     */
    public function inspectMysond(TableOrderInvoice $invoice): JsonResponse
    {
        if (empty($invoice->invoice_name)) {
            return $this->error(['message' => 'invoice_name mancante — impossibile interrogare MySond.']);
        }

        $vatDigits = \App\Helpers\VatHelper::sanitize(\App\Facades\Utils::setting('company_vat_number'));
        $fileName  = 'IT' . $vatDigits . '_' . $invoice->invoice_name;

        $year = $invoice->created_at ? (int) $invoice->created_at->format('Y') : (int) now()->format('Y');

        $service = app(MysondFatturaService::class);

        try {
            $records = $service->listInviateByFileName($fileName, $year);
        } catch (\Throwable $e) {
            Log::error('inspectMysond error', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            return $this->error(['message' => 'Errore interrogazione MySond: ' . $e->getMessage()]);
        }

        $rows = array_map(function ($item) {
            $stato = isset($item->stato) && is_numeric($item->stato) ? (int) $item->stato : null;
            $raw   = json_decode(json_encode($item), true) ?: [];
            return [
                'code'          => isset($item->code) ? (string) $item->code : null,
                'doc_name'      => (string) ($item->docName ?? $item->fileName ?? ''),
                'stato'         => $stato,
                'stato_label'   => $stato !== null ? TableOrderInvoice::sdiStatusLabel($stato) : null,
                'is_success'    => $stato !== null && in_array($stato, [7, 9], true),
                'is_rejected'   => $stato !== null && in_array($stato, TableOrderInvoice::SDI_REJECTED_CODES, true),
                'date'          => $raw['date'] ?? ($raw['dateLong'] ?? null),
                'total'         => $raw['importoTotale'] ?? ($raw['totale'] ?? ($raw['importo'] ?? null)),
                'descrizione'   => $raw['descrizione'] ?? ($raw['messaggio'] ?? null),
                'raw'           => $raw,
            ];
        }, $records);

        return response()->json([
            'invoice' => [
                'id'           => $invoice->id,
                'invoice_code' => $invoice->invoice_code,
                'invoice_name' => $invoice->invoice_name,
                'status'       => $invoice->status,
                'sdi_status'   => $invoice->sdi_status,
                'sdi_status_label' => $invoice->sdi_status_label,
            ],
            'file_name' => $fileName,
            'records'   => $rows,
            'has_success' => collect($rows)->contains('is_success', true),
        ]);
    }

    /**
     * Adotta come esito ufficiale uno dei record trovati su MySond.
     * Consentito solo se lo stato scelto è di successo (Consegnata/Accettata):
     * imposta status='sent', allinea sdi_status e sdi_status_label, e registra
     * l'operazione in invoice_mysond_logs per audit.
     */
    public function adoptMysondOutcome(Request $request, TableOrderInvoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'stato'       => 'required|integer',
            'codice'      => 'nullable|string|max:100',
            'descrizione' => 'nullable|string|max:1000',
        ]);

        $stato = (int) $data['stato'];
        if (!in_array($stato, [7, 9], true)) {
            return $this->error(['message' => 'Adozione consentita solo per esiti di successo (Consegnata / Accettata).']);
        }

        $label       = TableOrderInvoice::sdiStatusLabel($stato);
        $descrizione = $data['descrizione'] ?? $label;
        $codice      = $data['codice'] ?? null;

        $previousStatus = $invoice->status;
        $previousSdi    = $invoice->sdi_status;

        $invoice->update([
            'status'           => 'sent',
            'sent_at'          => $invoice->sent_at ?: now(),
            'sdi_status'       => $stato,
            'sdi_status_label' => $label,
            'sdi_checked_at'   => now(),
            'sdi_response'     => json_encode([
                'adopted'     => true,
                'stato'       => $stato,
                'descrizione' => $descrizione,
                'codice'      => $codice,
                'previous'    => [
                    'status'     => $previousStatus,
                    'sdi_status' => $previousSdi,
                ],
                'adopted_by'  => auth()->user()?->email,
                'at'          => now()->toIso8601String(),
            ]),
        ]);

        InvoiceMysondLog::create([
            'table_order_invoice_id' => $invoice->id,
            'operation'              => 'adoptFromMysond',
            'outcome'                => InvoiceMysondLog::OUTCOME_SUCCESS,
            'esito'                  => $stato,
            'codice'                 => $codice,
            'descrizione'            => sprintf(
                'Adottato esito %s da MySond (era status=%s, sdi_status=%s). Utente: %s',
                $label ?? ('Stato ' . $stato),
                $previousStatus ?? '—',
                $previousSdi ?? '—',
                auth()->user()?->email ?? 'admin'
            ),
        ]);

        return $this->success([
            'message' => 'Esito adottato: ' . ($label ?? ('Stato ' . $stato)),
        ]);
    }

    public function resend(TableOrderInvoice $invoice): JsonResponse
    {
        $needsRegeneration = empty($invoice->xml_content);

        if ($needsRegeneration) {
            $invoice->loadMissing('customer');
            if (!$invoice->customer) {
                return $this->error(['message' => 'Cliente associato non trovato — impossibile rigenerare XML.']);
            }

            try {
                $xmlResult = app(MysondFatturaService::class)->createInvoice($invoice);
            } catch (\Throwable $e) {
                Log::error('Resend invoice XML regeneration error', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
                InvoiceMysondLog::logCreateInvoice($invoice->id, null, $e);
                $invoice->update([
                    'status'          => 'error',
                    'mysond_response' => json_encode([
                        'response' => 'error',
                        'message'  => $e->getMessage(),
                    ]),
                ]);
                return $this->error(['message' => 'Errore rigenerazione XML: ' . $e->getMessage()]);
            }

            InvoiceMysondLog::logCreateInvoice($invoice->id, $xmlResult);

            $responsePayload = is_array($xmlResult) ? json_encode($xmlResult) : (string) $xmlResult;

            if (($xmlResult['response'] ?? '') !== 'success') {
                $invoice->update([
                    'status'          => 'error',
                    'mysond_response' => $responsePayload,
                ]);
                return $this->error([
                    'message' => 'Errore rigenerazione XML: ' . ($xmlResult['message'] ?? 'sconosciuto'),
                ]);
            }

            $invoice->update([
                'status'          => 'pending',
                'xml_content'     => $xmlResult['content'] ?? null,
                'mysond_response' => $responsePayload,
            ]);
        } else {
            $invoice->update(['status' => 'pending']);
        }

        SendInvoiceToMysondJob::dispatch($invoice->id);

        return $this->success(['message' => 'Fattura re-accodata per invio.']);
    }

    public function logs(TableOrderInvoice $invoice): JsonResponse
    {
        $logs = $invoice->mysondLogs()->get()->map(function (InvoiceMysondLog $log) {
            return [
                'id'                => $log->id,
                'operation'         => $log->operation,
                'outcome'           => $log->outcome,
                'esito'             => $log->esito,
                'codice'            => $log->codice,
                'descrizione'       => $log->descrizione,
                'request_xml'       => $log->request_xml,
                'response_xml'      => $log->response_xml,
                'exception_class'   => $log->exception_class,
                'exception_message' => $log->exception_message,
                'exception_trace'   => $log->exception_trace,
                'duration_ms'       => $log->duration_ms,
                'created_at'        => $log->created_at?->format('d/m/Y H:i:s'),
            ];
        });

        return response()->json([
            'invoice' => [
                'id'           => $invoice->id,
                'invoice_code' => $invoice->invoice_code,
                'invoice_name' => $invoice->invoice_name,
                'status'       => $invoice->status,
            ],
            'logs' => $logs,
        ]);
    }

    public function customers(): View
    {
        return view('backoffice.accounting.customers.index');
    }

    public function customersDatatable(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            $query = Customer::withCount('tableOrderInvoices')
                ->orderBy('full_name');

            if (!empty($filters['search'])) {
                $q = $filters['search'];
                $query->where(function ($sub) use ($q) {
                    $sub->where('full_name', 'like', "%{$q}%")
                        ->orWhere('fiscal_code', 'like', "%{$q}%")
                        ->orWhere('vat_number', 'like', "%{$q}%");
                });
            }
            if (!empty($filters['user_type'])) {
                $query->where('user_type', $filters['user_type']);
            }

            $elements = $query->get();

            return datatables()->of($elements)
                ->addColumn('type_label', function ($item) {
                    return match ($item->user_type) {
                        'private'        => '<span class="label label-default">Privato</span>',
                        'company'        => '<span class="label label-info">Azienda</span>',
                        'public_company' => '<span class="label label-primary">PA</span>',
                        default          => '—',
                    };
                })
                ->addColumn('identifier', function ($item) {
                    $parts = [];
                    if ($item->vat_number)  $parts[] = 'P.IVA ' . e($item->vat_number);
                    if ($item->fiscal_code) $parts[] = 'CF ' . e($item->fiscal_code);
                    return implode('<br>', $parts) ?: '—';
                })
                ->addColumn('address_full', function ($item) {
                    $parts = array_filter([$item->address, $item->zip_code, $item->city, $item->province ? '(' . $item->province . ')' : null]);
                    return e(implode(' ', $parts)) ?: '—';
                })
                ->addColumn('destinatario', function ($item) {
                    $parts = [];
                    if ($item->codice_destinatario) $parts[] = 'SDI: ' . e($item->codice_destinatario);
                    if ($item->pec_destinatario)    $parts[] = 'PEC: ' . e($item->pec_destinatario);
                    return implode('<br>', $parts) ?: '—';
                })
                ->addColumn('invoices_count', function ($item) {
                    return (int) ($item->table_order_invoices_count ?? 0);
                })
                ->rawColumns(['type_label', 'identifier', 'destinatario'])
                ->make(true);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
