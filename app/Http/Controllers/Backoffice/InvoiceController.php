<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Interfaces\SupplierInterface;
use App\Interfaces\SupplierInvoiceInterface;
use App\Models\ExternalInvoice;
use App\Models\MappingProduct;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceImportLog;
use App\Models\SupplierInvoiceProduct;
use App\Traits\DatatableTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends BaseController
{
    use DatatableTrait;

    protected SupplierInvoiceInterface $interface;
    protected SupplierInterface $supplier;
    protected string $name;
    public function __construct(
        SupplierInvoiceInterface $interface,
        SupplierInterface $supplier,
    )
    {
        $this->interface = $interface;
        $this->supplier = $supplier;
        $this->name = 'invoices';
    }

    public function index() : View {
        $suppliers = Supplier::orderBy('company_name')->get()
            ->map(fn($s) => ['id' => $s->id, 'label' => $s->company_name])
            ->toArray();

        $failedInvoices = ExternalInvoice::where('status', 'import_error')
            ->orderByDesc('created_at')
            ->get();

        $importLogsCount = SupplierInvoiceImportLog::count();

        return view('backoffice.' . $this->name . '.index', compact('suppliers', 'failedInvoices', 'importLogsCount'));
    }

    public function toggleIgnore(int $id): JsonResponse
    {
        $invoice = SupplierInvoice::findOrFail($id);

        $invoice->update([
            'ignored_at' => $invoice->ignored_at ? null : now(),
        ]);

        return response()->json([
            'ignored' => (bool) $invoice->ignored_at,
        ]);
    }

    public function importLogs(): JsonResponse
    {
        $logs = SupplierInvoiceImportLog::with('invoice.supplier')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('supplier_invoice_id')
            ->map(function ($rows) {
                $invoice = $rows->first()->invoice;
                return [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'supplier_name'  => $invoice->supplier->company_name ?? '—',
                    'invoice_date'   => $invoice->invoice_date?->format('d/m/Y'),
                    'auto_mapped'    => $rows->where('status', 'auto_mapped')->count(),
                    'partial'        => $rows->where('status', 'partial_mapping')->count(),
                    'to_map'         => $rows->where('status', 'to_map')->count(),
                    'rows'           => $rows->map(fn($r) => [
                        'status'              => $r->status,
                        'product_name'        => $r->product_name,
                        'material_name'       => $r->material_name,
                        'qty_invoice'         => $r->qty_invoice,
                        'quantity_multiplier' => $r->quantity_multiplier,
                        'stock_added'         => $r->stock_added,
                        'stock_unit'          => $r->stock_unit,
                        'notes'               => $r->notes,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json($logs);
    }

    public function ignore_failed(int $id): JsonResponse
    {
        $externalInvoice = ExternalInvoice::findOrFail($id);
        abort_if($externalInvoice->status !== 'import_error', 422, 'Fattura non in stato di errore.');

        $externalInvoice->update(['status' => 'ignored']);

        return response()->json(['success' => true]);
    }

    public function download_failed_file(int $id): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $externalInvoice = ExternalInvoice::findOrFail($id);

        abort_if(!$externalInvoice->file_path, 404);

        $fullPath = storage_path('app/' . $externalInvoice->file_path);

        abort_unless(file_exists($fullPath), 404);

        return response()->download($fullPath, $externalInvoice->file_name);
    }

    public function datatable(Request $request) : JsonResponse {
        try {
            $filters = $request->get('filters') ?? [];

            $operations = ['pdf-invoice',  'ignore-invoice', 'import-stock'];
            if (isset($filters['mapping']) && $filters['mapping'] == 'da_effettuare') {
                $operations[] = 'mapping-product';
            }
            if (isset($filters['import']) && $filters['import'] == 'da_effettuare') {
                $operations[] = 'import-stock';
            }

            $elements = $this->interface->filters($filters)->orderBy('supplier_invoices.created_at', 'DESC');
            return $this->editColumns(datatables()->of($elements), $this->name, $operations)
                ->addColumn('supplier_name', function ($item) {
                   return $item->supplier->extended_name;
                })
                ->addColumn('amount', function ($item) {
                   return Utils::price($item->amount);
                })
                ->addColumn('invoice_number', function ($item) {
                   return $item->invoice_number . '<br /><small>' . $item->filename. '</small>';
                })
                ->addColumn('invoice_date', function ($item) {
                   return Utils::data($item->invoice_date);
                })
                ->addColumn('products', function ($item) {
                   return $item->products_count;
                })
                ->addColumn('mapping', function ($item) {
                    $total     = $item->products_count;
                    $daMappare = $item->products()->whereDoesntHave('material', function ($query) {
                        $query->whereColumn('mapping_products.quantity_multiplier', 'supplier_invoice_products.quantity_multiplier')
                            ->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                            ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                    })->where('ignore_mapping', 0)->count();
                    $mappati   = $item->products()->whereHas('material', function ($query) {
                        $query->whereColumn('mapping_products.quantity_multiplier', 'supplier_invoice_products.quantity_multiplier')
                            ->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                            ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                    })->count();
                    $ignorati  = $item->products()->where('ignore_mapping', 1)->count();

                    $html  = '<div class="mapping-summary">';
                    $html .= '<div style="font-size:11px; color:#888; margin-bottom:3px;">Totale: <strong>' . $total . '</strong> prodotti</div>';
                    $html .= '<div class="mapping-badges">';
                    if ($daMappare > 0) {
                        $html .= '<span class="label label-warning"><span class="glyphicon glyphicon-exclamation-sign"></span> ' . $daMappare . ' da mappare</span> ';
                    }
                    if ($mappati > 0) {
                        $html .= '<span class="label label-primary"><span class="glyphicon glyphicon-link"></span> ' . $mappati . ' mappati</span> ';
                    }
                    if ($ignorati > 0) {
                        $html .= '<span class="label label-default"><span class="glyphicon glyphicon-minus-sign"></span> ' . $ignorati . ' ignorati</span>';
                    }
                    $html .= '</div></div>';
                    return $html;
                })
                ->addColumn('import', function ($item) {
                    $total     = $item->products_count;
                    $daImportare = $item->products()->whereHas('material', function ($query) {
                        $query->whereColumn('mapping_products.quantity_multiplier', 'supplier_invoice_products.quantity_multiplier')
                            ->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                            ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                    })
                        ->where('ignore_mapping', 0)
                        ->whereDoesntHave('stock')
                        ->count();


                    $html  = '<div class="mapping-summary">';
                    $html .= '<div style="font-size:11px; color:#888; margin-bottom:3px;">Totale: <strong>' . $total . '</strong> prodotti</div>';
                    $html .= '<div class="mapping-badges">';
                    if ($daImportare > 0) {
                        $html .= '<span class="label label-warning"><span class="glyphicon glyphicon-exclamation-sign"></span> ' . $daImportare . ' da importare</span> ';
                    }
                    $html .= '</div></div>';
                    return $html;
                })
                ->rawColumns(['invoice_number', 'supplier_name', 'mapping', 'import'])
                ->toJson();
        } catch (\Exception $e) {
            dd($e);
            return $this->exception($e);
        }
    }

    public function show(int $id, Request $request) : View {
        try {
            $user = $this->interface->find($id);
            if ($user->id) {
                return view('backoffice.' . $this->name . '.edit', [
                    'object' => $user,
                ]);
            }
            throw new Exception('Element not found');
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function import_form() : JsonResponse
    {
        $entity = SupplierInvoice::class;
        return $this->success([
            'html' => view('backoffice.invoices.upload-invoice', compact('entity'))->render()
        ]);
    }

    public function import_invoice(Request $request) : JsonResponse
    {

        try {
            $response = self::extract_invoice_data($request->get('file'));

            if ($response['error']) {
                throw new Exception($response['message']);
            }

            return $this->success([
                'html' => view('backoffice.invoices.import-completed', ['name' => $response['name']])->render()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(['message' => "Errore durante l'importazione della fattura: " . $e->getMessage()]);
        }
    }

    public function mapping_products(int $id) : View
    {
        $invoice = SupplierInvoice::findOrFail($id);

        $supplierInvoiceProducts = $invoice->products()
            ->whereDoesntHave('material', function ($query) {
                $query->whereColumn('mapping_products.quantity_multiplier', 'supplier_invoice_products.quantity_multiplier')
                    ->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                    ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
            })
            ->where('ignore_mapping', 0)
            ->orderBy('id')
            ->get();

        $materials = Material::orderBy('label')->get();

        return view('backoffice.invoices.map-products', compact(
            'invoice',
            'supplierInvoiceProducts',
            'materials'
        ));
    }

    public function store_mapping_products(Request $request, $invoiceId)
    {

        $request->validate([
            'mappings' => 'required|array',
            'mappings.*' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value != 0 && !Material::where('id', $value)->exists()) {
                        $fail("Il materiale selezionato non esiste.");
                    }
                }
            ],
            'multipliers.*' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $invoice = $this->interface->find($invoiceId);
        $mappings = $request->input('mappings', []);
        $multipliers = $request->input('multipliers', []);

        DB::beginTransaction();

        try {
            foreach ($mappings as $productId => $materialId) {
                $product = $invoice->products()->find($productId);
                if (!$product) {
                    continue;
                }

                $multiplier = isset($multipliers[$productId]) ? (float) $multipliers[$productId] : 1;
                $multiplier = $multiplier > 0 ? $multiplier : 1;

                if ($materialId === '0') {
                    $product->update(['ignore_mapping' => 1]);
                } else {
                    $product->update(['quantity_multiplier' => $multiplier]);

                    if ($materialId) {
                        MappingProduct::updateOrCreate(
                            ['product_name' => $product->product_name, 'supplier_id' => $invoice->supplier_id],
                            ['material_id' => $materialId, 'quantity_multiplier' => $multiplier]
                        );
                    }
                }
            }

            DB::commit();

            return $this->success();

        } catch (\Exception $e) {
            DB::rollBack();
        }
    }

    public function load_stocks(): JsonResponse
    {
        $products = SupplierInvoiceProduct::whereHas('material')
            ->whereDoesntHave('stock')
            ->where('ignore_mapping', 0)
            ->with(['invoice'])
            ->get();

        $created = 0;
        foreach ($products as $product) {
            $mapping = MappingProduct::where('product_name', $product->product_name)
                ->where('supplier_id', $product->invoice?->supplier_id)
                ->with('material')
                ->first();
            $material = $mapping?->material;
            if (!$material) continue;

            $realQuantity = $product->quantity * ($product->quantity_multiplier ?? 1);

            $stock = MaterialStock::firstOrCreate(
                ['supplier_invoice_product_id' => $product->id],
                [
                    'material_id'    => $material->id,
                    'stock'          => $realQuantity,
                    'purchase_date'  => $product->invoice?->invoice_date,
                    'purchase_price' => $product->price,
                ]
            );

            if ($stock->wasRecentlyCreated) {
                $created++;
            }
        }

        return $this->success([
            'created' => $created,
            'message' => $created > 0
                ? "Caricate {$created} giacenze."
                : 'Nessuna nuova giacenza da caricare.',
        ]);
    }

    public function downloadPdf(int $id): Response
    {
        $invoice = SupplierInvoice::findOrFail($id);

        abort_if(!$invoice->filename, 404, 'Nessun file XML associato a questa fattura.');

        $candidates = [
            storage_path('app/private/invoices/' . $invoice->filename),
            storage_path('app/temp/' . $invoice->filename),
            storage_path('app/failed_invoices/' . $invoice->filename),
        ];
        $xmlPath = collect($candidates)->first(fn($p) => file_exists($p));
        abort_unless($xmlPath, 404, 'File XML non trovato.');

        $content = file_get_contents($xmlPath);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        abort_if($xml === false, 422, 'File XML non valido.');

        // Prova a estrarre il PDF allegato (copia di cortesia)
        $body = $xml->FatturaElettronicaBody;
        if (isset($body->Allegati)) {
            foreach ($body->Allegati as $allegato) {
                if (strtoupper((string)$allegato->FormatoAttachment) === 'PDF') {
                    $pdfBytes = base64_decode((string)$allegato->Attachment);
                    if ($pdfBytes && str_starts_with($pdfBytes, '%PDF')) {
                        $filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $invoice->filename);
                        $filename = preg_replace('/\.xml$/i', '.pdf', $filename);
                        return response($pdfBytes, 200, [
                            'Content-Type'        => 'application/pdf',
                            'Content-Disposition' => 'inline; filename="' . $filename . '"',
                        ]);
                    }
                }
            }
        }

        // Fallback: genera PDF da dati XML tramite dompdf
        $header    = $xml->FatturaElettronicaHeader;
        $cedente   = $header->CedentePrestatore;
        $committente = $header->CessionarioCommittente;
        $datiDoc   = $body->DatiGenerali->DatiGeneraliDocumento;

        $cedenteName = (string)($cedente->DatiAnagrafici->Anagrafica->Denominazione ?? '');
        if (!$cedenteName) {
            $cedenteName = trim(
                (string)($cedente->DatiAnagrafici->Anagrafica->Nome ?? '') . ' ' .
                (string)($cedente->DatiAnagrafici->Anagrafica->Cognome ?? '')
            );
        }

        $committenteName = (string)($committente->DatiAnagrafici->Anagrafica->Denominazione ?? '');
        if (!$committenteName) {
            $committenteName = trim(
                (string)($committente->DatiAnagrafici->Anagrafica->Nome ?? '') . ' ' .
                (string)($committente->DatiAnagrafici->Anagrafica->Cognome ?? '')
            );
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
        foreach ($body->DatiBeniServizi->DatiRiepilogo as $r) {
            $riepilogo[] = [
                'aliquota'   => (float)$r->AliquotaIVA,
                'imponibile' => (float)$r->ImponibileImporto,
                'imposta'    => (float)$r->Imposta,
            ];
        }

        $pagamento = null;
        if (isset($body->DatiPagamento->DettaglioPagamento)) {
            $dp = $body->DatiPagamento->DettaglioPagamento;
            $pagamento = [
                'modalita' => (string)($dp->ModalitaPagamento ?? ''),
                'scadenza'  => (string)($dp->DataScadenzaPagamento ?? ''),
                'importo'   => (float)($dp->ImportoPagamento ?? 0),
            ];
        }

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
            'linee'      => $linee,
            'riepilogo'  => $riepilogo,
            'pagamento'  => $pagamento,
        ];

        $filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $invoice->filename);
        $filename = preg_replace('/\.xml$/i', '.pdf', $filename);

        $pdf = Pdf::loadView('backoffice.invoices.pdf', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->stream($filename);
    }

    public function importStockPreview(int $id): JsonResponse
    {
        $invoice = SupplierInvoice::with('supplier')->findOrFail($id);

        $products = $invoice->products()
            ->where('ignore_mapping', 0)
            ->orderBy('id')
            ->get();

        $productsData = $products->map(function ($product) use ($invoice) {
            $mapping = MappingProduct::where('product_name', $product->product_name)
                ->where('supplier_id', $invoice->supplier_id)
                ->with('material')
                ->first();

            $material = $mapping?->material;
            $multiplier = $mapping?->quantity_multiplier ?? $product->quantity_multiplier ?? 1;
            $stockPreview = round($product->quantity * $multiplier, 4);

            return [
                'id'                  => $product->id,
                'product_name'        => $product->product_name,
                'quantity'            => $product->quantity,
                'quantity_unit'       => $product->quantity_unit,
                'quantity_multiplier' => $multiplier,
                'price'               => $product->price,
                'material_id'         => $material?->id,
                'material_label'      => $material?->label,
                'stock_unit'          => $material?->stock_type,
                'stock_preview'       => $stockPreview,
                'has_stock'           => $product->stock()->exists(),
            ];
        });

        return $this->success([
            'invoice'  => [
                'id'       => $invoice->id,
                'number'   => $invoice->invoice_number,
                'supplier' => $invoice->supplier->company_name ?? '—',
            ],
            'products' => $productsData->values(),
        ]);
    }

    public function loadInvoiceStocks(Request $request, int $id): JsonResponse
    {
        $invoice = SupplierInvoice::findOrFail($id);

        $request->validate([
            'products'                       => 'required|array',
            'products.*.id'                  => 'required|integer',
            'products.*.material_id'         => 'nullable|integer|exists:materials,id',
            'products.*.quantity_multiplier' => 'nullable|numeric|min:0.001',
        ]);

        $created = 0;

        DB::beginTransaction();
        try {
            foreach ($request->input('products') as $row) {
                $product = $invoice->products()->find($row['id']);
                if (!$product) continue;

                $materialId = $row['material_id'] ?? null;
                $multiplier = isset($row['quantity_multiplier']) && $row['quantity_multiplier'] > 0
                    ? (float) $row['quantity_multiplier']
                    : 1;

                if (!$materialId) continue;

                // Aggiorna mappatura e moltiplicatore
                MappingProduct::updateOrCreate(
                    ['product_name' => $product->product_name, 'supplier_id' => $invoice->supplier_id],
                    ['material_id' => $materialId, 'quantity_multiplier' => $multiplier]
                );
                $product->update(['quantity_multiplier' => $multiplier]);

                $realQuantity = $product->quantity * $multiplier;

                $stock = MaterialStock::firstOrCreate(
                    ['supplier_invoice_product_id' => $product->id],
                    [
                        'material_id'    => $materialId,
                        'stock'          => $realQuantity,
                        'purchase_date'  => $invoice->invoice_date,
                        'purchase_price' => $product->price,
                    ]
                );

                if ($stock->wasRecentlyCreated) {
                    $created++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(['message' => 'Errore durante il caricamento delle giacenze: ' . $e->getMessage()]);
        }

        return $this->success([
            'created' => $created,
            'message' => $created > 0
                ? "Caricate {$created} giacenze."
                : 'Nessuna nuova giacenza da caricare.',
        ]);
    }

    public function checkPriceAlerts(): JsonResponse
    {
        // Materiali dei prodotti in attesa di carico
        $pendingMaterialIds = SupplierInvoiceProduct::whereHas('material')
            ->whereDoesntHave('stock')
            ->where('ignore_mapping', 0)
            ->with('material')
            ->get()
            ->pluck('material.id')
            ->unique()
            ->filter()
            ->values();

        if ($pendingMaterialIds->isEmpty()) {
            return $this->success(['has_alerts' => false, 'count' => 0]);
        }

        $alertCount = 0;
        $mappings = MappingProduct::whereIn('material_id', $pendingMaterialIds)
            ->get()
            ->groupBy('material_id');

        foreach ($mappings as $materialId => $materialMappings) {
            $productNames = $materialMappings->pluck('product_name');

            $prices = SupplierInvoiceProduct::whereIn('product_name', $productNames)
                ->where('quantity_multiplier', '>', 0)
                ->where('ignore_mapping', 0)
                ->whereHas('invoice', fn($q) => $q->whereNull('ignored_at'))
                ->get()
                ->map(fn($p) => $p->price / $p->quantity_multiplier)
                ->filter(fn($p) => $p > 0);

            if ($prices->count() < 2) continue;

            $min = $prices->min();
            $max = $prices->max();
            if ($min > 0 && (($max - $min) / $min * 100) > 20) {
                $alertCount++;
            }
        }

        return $this->success(['has_alerts' => $alertCount > 0, 'count' => $alertCount]);
    }

    private function extract_invoice_data($file) : array
    {
        try {
            DB::beginTransaction();
            $original_name = $file['name'];
            $filename = $file['basename'];
            $fileContent = file_get_contents(storage_path() . '/app/private/invoices/' . $filename);

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($fileContent);
            if ($xml === false) {
                throw new \Exception('File XML non valido');
            }

            // Estrai dati fornitore
            $supplier = $this->extractSupplier($xml);
            $invoice = $this->extractInvoice($xml, $supplier, $filename);
            $productsCount = $this->extractProducts($xml, $invoice);

            DB::commit();
            return ['error' => false, 'name' => $original_name];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['error' => true, 'message' => $e->getMessage()];
        }

    }

    private function extractSupplier($xml): Supplier
    {
        $cedente = $xml->FatturaElettronicaHeader->CedentePrestatore;
        $datiAnagrafici = $cedente->DatiAnagrafici;
        $anagrafica = $datiAnagrafici->Anagrafica;
        $sede = $cedente->Sede;

        // Gestisci denominazione o nome+cognome
        $companyName = (string)($anagrafica->Denominazione ?? '');
        if (!$companyName) {
            $nome = (string)($anagrafica->Nome ?? '');
            $cognome = (string)($anagrafica->Cognome ?? '');
            $companyName = trim($nome . ' ' . $cognome);
        }

        $vatNumber = str_replace('IT', '', (string)$datiAnagrafici->IdFiscaleIVA->IdCodice);

        $supplierData = [
            'vat_number' => $vatNumber,
            'fiscal_code' => (string)($datiAnagrafici->CodiceFiscale ?? $vatNumber),
            'company_name' => $companyName,
            'address' => (string)($sede->Indirizzo ?? ''),
            'number' => (string)($sede->NumeroCivico ?? ''),
            'zip_code' => (string)($sede->CAP ?? ''),
            'city' => (string)($sede->Comune ?? ''),
            'province' => (string)($sede->Provincia ?? ''),
            'nation' => (string)($sede->Nazione ?? 'IT'),
        ];

        return Supplier::firstOrCreate(
            ['vat_number' => $supplierData['vat_number']],
            $supplierData
        );
    }

    private function extractInvoice($xml, Supplier $supplier, string $filename): SupplierInvoice
    {
        $body = $xml->FatturaElettronicaBody;
        $datiDocumento = $body->DatiGenerali->DatiGeneraliDocumento;

        $invoiceNumber = (string)$datiDocumento->Numero;
        $invoiceDate = (string)$datiDocumento->Data;
        $totalAmount = (float)($datiDocumento->ImportoTotaleDocumento ?? 0);

        // Verifica duplicato
        $existingInvoice = SupplierInvoice::where('supplier_id', $supplier->id)
            ->where('invoice_number', $invoiceNumber)
            ->first();

        if ($existingInvoice) {
            throw new \Exception("Fattura numero {$invoiceNumber} già presente nel sistema");
        }

        return SupplierInvoice::create([
            'supplier_id' => $supplier->id,
            'invoice_number' => $invoiceNumber,
            'filename' => $filename,
            'amount' => $totalAmount,
            'invoice_date' => $invoiceDate,
        ]);
    }

    private function extractProducts($xml, SupplierInvoice $invoice): int
    {
        $body = $xml->FatturaElettronicaBody;
        $dettaglioLinee = $body->DatiBeniServizi->DettaglioLinee;

        $count = 0;

        foreach ($dettaglioLinee as $linea) {
            $quantity = (float)($linea->Quantita ?? 0);

            // Salta righe descrittive
            if ($quantity <= 0) {
                continue;
            }

            SupplierInvoiceProduct::create([
                'supplier_invoice_id' => $invoice->id,
                'product_name' => (string)$linea->Descrizione,
                'quantity' => $quantity,
                'price' => (float)($linea->PrezzoUnitario ?? 0),
                'iva' => (float)($linea->AliquotaIVA ?? 0),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Associa automaticamente i material_stocks ai prodotti delle fatture
     * in base ai mapping già esistenti nella tabella mapping_products
     */
    private function associateMaterialStocks(): void
    {
        // Recupera tutti i prodotti delle fatture che hanno un mapping material ma non hanno ancora uno stock
        $productsToAssociate = SupplierInvoiceProduct::whereHas('material')
            ->whereDoesntHave('stock')
            ->with(['invoice'])
            ->get();

        foreach ($productsToAssociate as $product) {
            $mapping = MappingProduct::where('product_name', $product->product_name)
                ->where('supplier_id', $product->invoice?->supplier_id)
                ->first();
            $material = $mapping?->material;

            if ($material) {
                $realQuantity = $product->quantity * ($product->quantity_multiplier ?? 1);

                // Crea il record MaterialStock
                $materialStock = MaterialStock::firstOrCreate([
                    'supplier_invoice_product_id' => $product->id,
                ], [
                    'material_id' => $material->id,
                    'stock' => $realQuantity,
                ]);

                // Se il MaterialStock è stato appena creato, aggiorna lo stock del Material
                if ($materialStock->wasRecentlyCreated) {
                    $materialModel = Material::find($material->id);
                    if ($materialModel) {
                        $materialModel->increment('stock', $realQuantity);
                    }
                }
            }
        }
    }

    public function to_map() : View {
        $suppliers = Supplier::orderBy('company_name')->get()
            ->map(fn($s) => ['id' => $s->id, 'label' => $s->company_name])
            ->toArray();

        return view('backoffice.' . $this->name . '.to_map', compact('suppliers'));
    }

    public function to_import() : View {
        $suppliers = Supplier::orderBy('company_name')->get()
            ->map(fn($s) => ['id' => $s->id, 'label' => $s->company_name])
            ->toArray();

        return view('backoffice.' . $this->name . '.to_import', compact('suppliers'));
    }

}
