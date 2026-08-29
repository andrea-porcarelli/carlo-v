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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Lista Note di Credito emesse (TD04). Stessa logica pagina Fatture: sync
     * mirror MySond in ingresso, filtro clienti/scartate, ma senza il pannello
     * "Fatture esterne" (le note credito esterne non sono ancora tracciate
     * come categoria a sé — MySond le include indifferentemente in getFeInviateLink).
     */
    public function creditNotes(MysondInvoiceMirror $mirror): View
    {
        $mirror->sync();

        $customers = Customer::orderBy('full_name')->get(['id', 'full_name'])->map(function ($c) {
            return ['id' => $c->id, 'label' => $c->full_name];
        })->toArray();

        $pendingAcks = MirroredInvoice::pendingAck()
            ->orderBy('first_synced_at')
            ->get();

        return view('backoffice.accounting.credit-notes.index', compact('customers', 'pendingAcks'));
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

    public function mirroredXml(MirroredInvoice $mirrored, MysondInvoiceMirror $mirror)
    {
        if (empty($mirrored->xml_content)) {
            $mirror->downloadXmlAndHydrate($mirrored);
            $mirrored->refresh();
        }

        abort_if(empty($mirrored->xml_content), 404, 'XML non disponibile su MySond.');

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

    /**
     * Rende il wizard in modalità Nota di Credito (TD04). La sorgente può essere:
     *  - `invoice`   → fattura interna esistente (query ?id=X): pre-compila cliente
     *                  e righe copiandoli dalla fattura padre.
     *  - `mirrored`  → fattura esterna (mirrored da MySond, ?id=X): scarica l'XML
     *                  lazy per estrarre le righe; fallback riga singola col totale.
     *  - `blank`     → nessuna sorgente: nota credito senza DatiFattureCollegate.
     *
     * Il POST è gestito dallo stesso `submit()` del wizard: la persistenza dei
     * campi document_type / parent_invoice_id / parent_external_ref avviene lì.
     */
    public function createCreditNote(Request $request): View
    {
        $source = $request->query('source', 'blank');
        $id     = $request->query('id');

        $documentType      = TableOrderInvoice::DOCUMENT_TYPE_CREDIT_NOTE;
        $parentInvoiceId   = null;
        $parentExternalRef = null;
        $parentSummary     = null;
        $prefillCustomer   = null;
        $prefillLines      = null;

        if ($source === 'invoice' && $id) {
            $parent = TableOrderInvoice::with('customer')->findOrFail((int) $id);
            $parentInvoiceId = $parent->id;
            $parentSummary   = sprintf(
                'Fattura %s del %s%s',
                $parent->invoice_code,
                optional($parent->created_at)->format('d/m/Y') ?? '—',
                $parent->customer ? ' — ' . $parent->customer->full_name : ''
            );

            if ($parent->customer) {
                $prefillCustomer = ['id' => $parent->customer->id];
            }

            // Copia le righe della fattura padre così l'operatore può eliminarne
            // alcune (nota credito parziale) o modificarle. Se la padre non ha
            // righe strutturate (fatture legacy da table order), lasciamo vuoto
            // e sarà l'utente a inserirle.
            if (is_array($parent->lines) && count($parent->lines) > 0) {
                $prefillLines = $parent->lines;
            }
        } elseif ($source === 'mirrored' && $id) {
            $mirrored = MirroredInvoice::findOrFail((int) $id);

            // Tentativo di scaricare l'XML lazy se non ancora presente, per
            // estrarre righe e anagrafica cliente. Fail-soft: se il download
            // fallisce ripieghiamo su una riga forfait col totale mirroraro.
            if (empty($mirrored->xml_content)) {
                app(MysondInvoiceMirror::class)->downloadXmlAndHydrate($mirrored);
                $mirrored->refresh();
            }

            $parentExternalRef = [
                'code'                => $mirrored->mysond_code,
                'date'                => $mirrored->mysond_date?->format('Y-m-d'),
                'total'               => $mirrored->mysond_total,
                'mirrored_invoice_id' => $mirrored->id,
            ];
            $parentSummary = sprintf(
                'Fattura esterna %s del %s%s',
                $mirrored->mysond_code ?? '—',
                $mirrored->mysond_date?->format('d/m/Y') ?? '—',
                $mirrored->customer_name ? ' — ' . $mirrored->customer_name : ''
            );

            $prefillLines = $this->extractLinesFromMirroredXml($mirrored);

            // Anagrafica cliente: la fattura esterna non è collegata a nessun
            // Customer locale, ma il CessionarioCommittente dell'XML contiene
            // denominazione/P.IVA/CF/sede. Se troviamo un Customer locale con
            // stessa P.IVA o stesso CF lo riutilizziamo (id); altrimenti
            // pre-popoliamo direttamente i campi del wizard (nuovo cliente
            // creato in fase di submit).
            $customerData = $this->extractCustomerFromMirroredXml($mirrored);
            if ($customerData) {
                $existingCustomer = null;
                if (!empty($customerData['vat_number'])) {
                    $existingCustomer = Customer::where('vat_number', $customerData['vat_number'])->first();
                }
                if (!$existingCustomer && !empty($customerData['fiscal_code'])) {
                    $existingCustomer = Customer::where('fiscal_code', $customerData['fiscal_code'])->first();
                }
                $prefillCustomer = $existingCustomer
                    ? ['id' => $existingCustomer->id]
                    : ['data' => $customerData];
            }
        } elseif ($source !== 'blank') {
            abort(422, 'Sorgente non valida.');
        }

        return view('backoffice.accounting.invoices.credit-note-create', compact(
            'documentType', 'parentInvoiceId', 'parentExternalRef', 'parentSummary',
            'prefillCustomer', 'prefillLines'
        ));
    }

    /**
     * Endpoint AJAX per la modal di selezione fattura sorgente della nota di
     * credito: restituisce fatture interne + fatture esterne (mirrored) in un
     * elenco unificato, filtrate per numero/cliente/data. Limitato a 30 record
     * per tipo per non far esplodere la modal.
     */
    public function creditNoteSourceSuggestions(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $internalQ = TableOrderInvoice::with('customer')
            ->where('document_type', TableOrderInvoice::DOCUMENT_TYPE_INVOICE)
            ->orderByDesc('created_at')
            ->limit(30);

        if ($q !== '') {
            $like = "%{$q}%";
            $internalQ->where(function ($sub) use ($like) {
                $sub->where('invoice_code', 'like', $like)
                    ->orWhere('invoice_name', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', $like));
            });
        }

        $internal = $internalQ->get()->map(fn (TableOrderInvoice $i) => [
            'source'        => 'invoice',
            'id'            => $i->id,
            'code'          => $i->invoice_code,
            'date'          => optional($i->created_at)->format('Y-m-d'),
            'date_display'  => optional($i->created_at)->format('d/m/Y'),
            'customer_name' => optional($i->customer)->full_name,
            'total'         => (float) $i->amount,
        ]);

        $externalQ = MirroredInvoice::whereNull('local_invoice_id')
            ->orderByDesc('mysond_date')
            ->limit(30);

        if ($q !== '') {
            $like = "%{$q}%";
            $externalQ->where(function ($sub) use ($like) {
                $sub->where('mysond_code', 'like', $like)
                    ->orWhere('customer_name', 'like', $like);
            });
        }

        $external = $externalQ->get()->map(fn (MirroredInvoice $m) => [
            'source'        => 'mirrored',
            'id'            => $m->id,
            'code'          => $m->mysond_code,
            'date'          => optional($m->mysond_date)->format('Y-m-d'),
            'date_display'  => optional($m->mysond_date)->format('d/m/Y'),
            'customer_name' => $m->customer_name,
            'total'         => $m->mysond_total !== null ? (float) $m->mysond_total : null,
        ]);

        return response()->json([
            'internal' => $internal->values()->all(),
            'external' => $external->values()->all(),
        ]);
    }

    /**
     * Estrae righe fattura dal XML mirrored per pre-compilare il wizard nota
     * credito. In caso di XML assente o parsing fallito: riga unica "Storno
     * fattura {code}" col totale come fallback ragionevole.
     *
     * @return array<int, array{label: string, quantity: float, unit_price: float, vat_rate?: float, dish_id: null}>
     */
    private function extractLinesFromMirroredXml(MirroredInvoice $mirrored): array
    {
        $fallback = [[
            'label'      => 'Storno fattura ' . ($mirrored->mysond_code ?? $mirrored->file_name),
            'quantity'   => 1.0,
            'unit_price' => $mirrored->mysond_total !== null ? (float) $mirrored->mysond_total : 0.0,
            'dish_id'    => null,
        ]];

        if (empty($mirrored->xml_content)) {
            return $fallback;
        }

        // Namespace-strip: gli XML FatturaPA usano il prefisso `p:` (o `ns2:`)
        // sul root — SimpleXML `->FatturaElettronicaBody` non lo naviga senza
        // registrazione esplicita del namespace. Meglio strippare prefissi e
        // xmlns e lavorare su un albero senza namespace.
        $stripped = preg_replace('#(</?)[A-Za-z_][A-Za-z0-9_\-]*:#', '$1', $mirrored->xml_content);
        $stripped = preg_replace('#\s+xmlns(:[A-Za-z0-9_\-]+)?\s*=\s*"[^"]*"#', '', $stripped);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($stripped);
        if ($xml === false) {
            return $fallback;
        }

        $body = $xml->FatturaElettronicaBody ?? null;
        if (!$body) {
            return $fallback;
        }

        $rows = [];
        foreach ($body->DatiBeniServizi->DettaglioLinee ?? [] as $linea) {
            $qty       = (float) ($linea->Quantita ?? 1);
            $unitNet   = (float) ($linea->PrezzoUnitario ?? 0);
            $vat       = (float) ($linea->AliquotaIVA ?? 0);
            // Il wizard lavora in LORDO (unit_price include IVA), quindi
            // ricostruiamo il lordo dal netto XML.
            $unitGross = round($unitNet * (1 + $vat / 100), 2);
            $rows[] = [
                'label'      => (string) ($linea->Descrizione ?? ''),
                'quantity'   => $qty > 0 ? $qty : 1.0,
                'unit_price' => $unitGross,
                'vat_rate'   => $vat,
                'dish_id'    => null,
            ];
        }

        return count($rows) > 0 ? $rows : $fallback;
    }

    /**
     * Estrae anagrafica CessionarioCommittente + Sede + recapiti SDI dall'XML
     * FatturaPA mirrored. Ritorna un payload compatibile con i campi pubblici
     * di QuickInvoiceWizard (fullName, vatNumber, fiscalCode, address, ...).
     * Ritorna null se l'XML manca, non è parsabile o non contiene nessun
     * identificativo utile (nome/PIVA/CF).
     *
     * @return array<string, string>|null
     */
    private function extractCustomerFromMirroredXml(MirroredInvoice $mirrored): ?array
    {
        if (empty($mirrored->xml_content)) {
            return null;
        }

        $stripped = preg_replace('#(</?)[A-Za-z_][A-Za-z0-9_\-]*:#', '$1', $mirrored->xml_content);
        $stripped = preg_replace('#\s+xmlns(:[A-Za-z0-9_\-]+)?\s*=\s*"[^"]*"#', '', $stripped);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($stripped);
        if ($xml === false) {
            return null;
        }

        $header = $xml->FatturaElettronicaHeader ?? null;
        $ces    = $header?->CessionarioCommittente ?? null;
        if (!$ces) {
            return null;
        }

        $anag = $ces->DatiAnagrafici->Anagrafica ?? null;
        $sede = $ces->Sede ?? null;

        $denominazione = trim((string) ($anag->Denominazione ?? ''));
        $nome          = trim((string) ($anag->Nome ?? ''));
        $cognome       = trim((string) ($anag->Cognome ?? ''));
        $fullName      = $denominazione !== '' ? $denominazione : trim($nome . ' ' . $cognome);

        $piva  = trim((string) ($ces->DatiAnagrafici->IdFiscaleIVA->IdCodice ?? ''));
        $paese = trim((string) ($ces->DatiAnagrafici->IdFiscaleIVA->IdPaese ?? ''));
        $cf    = trim((string) ($ces->DatiAnagrafici->CodiceFiscale ?? ''));

        if ($fullName === '' && $piva === '' && $cf === '') {
            return null;
        }

        $indirizzo = trim((string) ($sede->Indirizzo ?? ''));
        $numero    = trim((string) ($sede->NumeroCivico ?? ''));
        if ($numero !== '') {
            $indirizzo = $indirizzo !== '' ? $indirizzo . ', ' . $numero : $numero;
        }

        // Recapiti SDI dal DatiTrasmissione (posso essere assenti per B2C).
        $codDest = trim((string) ($header->DatiTrasmissione->CodiceDestinatario ?? ''));
        $pec     = trim((string) ($header->DatiTrasmissione->PECDestinatario ?? ''));

        // user_type inferito: PIVA presente → business, altrimenti privato.
        $userType = $piva !== '' ? 'business' : 'private';

        return [
            'user_type'           => $userType,
            'country'             => $paese !== '' ? $paese : (trim((string) ($sede->Nazione ?? 'IT')) ?: 'IT'),
            'full_name'           => $fullName,
            'fiscal_code'         => $cf,
            'vat_number'          => $piva,
            'address'             => $indirizzo,
            'zip_code'            => trim((string) ($sede->CAP ?? '')),
            'city'                => trim((string) ($sede->Comune ?? '')),
            'province'            => trim((string) ($sede->Provincia ?? '')),
            'codice_destinatario' => $codDest,
            'pec_destinatario'    => $pec,
        ];
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->buildInvoicesDatatable($request, TableOrderInvoice::DOCUMENT_TYPE_INVOICE);
    }

    public function creditNotesDatatable(Request $request): JsonResponse
    {
        return $this->buildInvoicesDatatable($request, TableOrderInvoice::DOCUMENT_TYPE_CREDIT_NOTE);
    }

    /**
     * Datatable condivisa fra Fatture (TD01) e Note di Credito (TD04). Il tipo
     * documento viene filtrato server-side, così le due pagine mostrano insiemi
     * disgiunti senza dover far leva sul filtro utente. Le azioni per riga
     * variano leggermente: dalla lista fatture si può emettere una nota credito,
     * dalla lista note credito no (evita nota credito di una nota credito).
     */
    private function buildInvoicesDatatable(Request $request, string $documentType): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            $query = TableOrderInvoice::with(['customer', 'tableOrder'])
                ->where('document_type', $documentType)
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
                    $name  = $item->invoice_name ? ' <small class="text-muted">(' . $item->invoice_name . ')</small>' : '';
                    $type  = $item->document_type === TableOrderInvoice::DOCUMENT_TYPE_CREDIT_NOTE
                        ? ' <span class="label label-warning" title="Nota di credito">NC</span>'
                        : '';
                    return '<strong>' . e($item->invoice_code) . '</strong>' . $name . $type;
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
                        $inspectBtn = '<button class="btn btn-xs btn-info btn-inspect-mysond" data-id="' . $item->id . '" title="Ispeziona storico invii su MySond"><i class="fa fa-search"></i></button> ';
                    }
                    // Nota di credito: solo da fatture TD01 (evita nota credito
                    // di una nota credito) e solo se la fattura è stata generata
                    // (ha invoice_code). Modifica testuale/errore ancora ammessa
                    // — la NC riferisce il numero anche se lo stato SDI è pending.
                    $creditNoteBtn = '';
                    if ($item->document_type === TableOrderInvoice::DOCUMENT_TYPE_INVOICE && !empty($item->invoice_code)) {
                        $url = route('accounting.credit-notes.create', ['source' => 'invoice', 'id' => $item->id]);
                        $creditNoteBtn = '<a href="' . $url . '" class="btn btn-xs btn-warning" title="Emetti nota di credito da questa fattura"><i class="fa fa-file-invoice"></i></a>';
                    }
                    return $xmlBtn . $logBtn . $editBtn . $resendBtn . $sdiBtn . $inspectBtn . $creditNoteBtn;
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
                        'private'           => '<span class="label label-default">Privato</span>',
                        'company'           => '<span class="label label-info">Azienda</span>',
                        'sole_trader'       => '<span class="label label-info">Ditta ind./Libero prof.</span>',
                        'non_profit_entity' => '<span class="label label-warning">Ente Non Comm.</span>',
                        'public_company'    => '<span class="label label-primary">PA</span>',
                        'foreign'           => '<span class="label label-danger">Estero</span>',
                        default             => '—',
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

    /**
     * Invalida la cache dei crediti MySond e forza un nuovo prelievo. Solo admin.
     */
    public function refreshMysondCrediti(MysondFatturaService $mysond): RedirectResponse
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Solo un amministratore può aggiornare i crediti MySond.');
        }

        Cache::forget(MysondFatturaService::CREDITI_CACHE_KEY);
        $info = $mysond->getCreditiInfo();

        if (!empty($info['error'])) {
            return back()->with('error', 'Aggiornamento crediti MySond fallito: ' . $info['error']);
        }

        return back()->with('success', "Crediti MySond aggiornati: {$info['crediti']} residui.");
    }
}
