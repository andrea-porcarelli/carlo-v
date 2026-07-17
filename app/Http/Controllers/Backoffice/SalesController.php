<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Models\CashDrawerLog;
use App\Models\PrintLog;
use App\Models\TableOrder;
use App\Models\TableOrderInvoice;
use App\Services\DishCostEstimatorService;
use App\Services\MysondFatturaService;
use App\Services\TableOrderLoggerService;
use App\Traits\DatatableTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends BaseController
{
    use DatatableTrait;

    protected string $name;

    public function __construct()
    {
        $this->name = 'sales';
    }

    /**
     * Display a listing of sales
     */
    public function index(): View
    {
        return view('backoffice.sales.index');
    }

    /**
     * Get datatable data
     */
    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            // Get only paid orders (completed sales)
            $query = TableOrder::with(['restaurantTable', 'items.dish', 'waiter', 'ditronReceipts', 'closeLog.user'])
                ->where('status', 'paid')
                ->orderBy('updated_at', 'desc');

            // Apply filters
            if (!empty($filters['date_from'])) {
                $query->whereDate('closed_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('closed_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['table_number'])) {
                $query->whereHas('restaurantTable', function ($q) use ($filters) {
                    $q->where('table_number', $filters['table_number']);
                });
            }

            if (!empty($filters['payment_method'])) {
                $query->where('payment_method', $filters['payment_method']);
            }

            $elements = $query->get();
            $paymentLabels = TableOrder::paymentMethodLabels();

            return $this->editColumns(datatables()->of($elements), $this->name, ['edit'], null, 'restaurant.sales')
                ->addColumn('sale_info', function ($item) {
                    $date = $item->closed_at ? $item->closed_at->format('d/m/Y H:i') : '-';
                    $table = $item->restaurantTable ? 'Tavolo ' . $item->restaurantTable->table_number : 'N/A';
                    return '<strong>' . $table . '</strong><br><small>' . $date . '</small>';
                })
                ->addColumn('items_count', function ($item) {
                    return $item->items->count() . ' prodotti';
                })
                ->addColumn('total', function ($item) {
                    return '<strong>' . Utils::price($item->total_amount) . '</strong>';
                })
                ->addColumn('payment', function ($item) use ($paymentLabels) {
                    $method = $item->payment_method;
                    $label = $method ? ($paymentLabels[$method] ?? $method) : '-';
                    $badgeClass = match ($method) {
                        'contanti'                            => 'success',
                        'pos'                                 => 'info',
                        'fattura', 'fattura_contanti', 'fattura_pos' => 'purple',
                        'bonifico', 'assegno'                 => 'warning',
                        'misto'                               => 'primary',
                        'chiusura_conto'                      => 'secondary',
                        default                               => 'light',
                    };
                    $badgeStyle = $badgeClass === 'purple'
                        ? 'background:#6f42c1;color:#fff;'
                        : '';
                    $badge = '<span class="badge badge-' . $badgeClass . '" style="' . $badgeStyle . '">' . e($label) . '</span>';

                    // Info Ditron: mostriamo l'ultimo scontrino di vendita associato (numero fiscale se
                    // disponibile, altrimenti il receipt_number dell'agent).
                    $ditronBlock = '';
                    $ditronSale = $item->ditronReceipts
                        ->where('type', \App\Models\DitronReceipt::TYPE_SALE)
                        ->last();
                    if ($ditronSale) {
                        $number = $ditronSale->fiscal_number ?: $ditronSale->receipt_number;
                        $statusLabel = $ditronSale->getStatusLabel();
                        $statusColor = match ($ditronSale->status) {
                            \App\Models\DitronReceipt::STATUS_SENT    => '#28a745',
                            \App\Models\DitronReceipt::STATUS_FAILED  => '#dc3545',
                            default                                   => '#6c757d',
                        };
                        $cancelledSuffix = $ditronSale->isCancelled()
                            ? ' <span style="color:#dc3545;font-weight:600;">(ANNULLATO)</span>'
                            : '';
                        $ditronBlock = '<div style="margin-top:4px;font-size:0.75rem;color:#495057;">'
                            . '<i class="fas fa-receipt"></i> Ditron: '
                            . ($number !== null ? '<strong>#' . e((string) $number) . '</strong>' : '<em>—</em>')
                            . ' <span style="color:' . $statusColor . ';">' . e($statusLabel) . '</span>'
                            . $cancelledSuffix
                            . '</div>';
                    }
                    return $badge . $ditronBlock;
                })
                ->addColumn('waiter', function ($item) {
                    $opener = $item->waiter ? $item->waiter->name : '-';
                    $closer = $item->closeLog && $item->closeLog->user ? $item->closeLog->user->name : '-';
                    return '<div><small class="text-muted">Apre:</small> <strong>' . e($opener) . '</strong></div>'
                        . '<div><small class="text-muted">Chiude:</small> <strong>' . e($closer) . '</strong></div>';
                })
                ->addColumn('duration', function ($item) {
                    if ($item->opened_at && $item->closed_at) {
                        $minutes = $item->opened_at->diffInMinutes($item->closed_at);
                        return $minutes . ' min';
                    }
                    return '-';
                })
                ->rawColumns(['sale_info', 'total', 'payment', 'waiter', 'action'])
                ->make(true);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show sale details
     */
    public function show($id, DishCostEstimatorService $costEstimator): View
    {
        $sale = TableOrder::with([
            'restaurantTable',
            'items.dish.category',
            'items.dish.allergens',
            'items.materials',
            'waiter',
            'closeLog.user',
            'precontoSplits',
            'tableOrderInvoices.customer',
            'corrispettivi.precontoSplit',
            'corrispettivi.operator:id,name',
            'corrispettivi.emissioneAnnullata',
        ])->withTrashed()
            ->findOrFail($id);

        // Load logs for this sale
        $loggerService = new TableOrderLoggerService();
        $logs = $loggerService->getLogsForOrder($id);

        // Load print logs for this sale
        $printLogs = PrintLog::with(['printer', 'user'])
            ->where('table_order_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Load cash drawer logs for this sale
        $cashDrawerLogs = CashDrawerLog::where('table_order_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        // Stima costi materie prime per riga e totale (escluse righe cancellate)
        $items = $sale->items()->withTrashed()->get()->load('materials');
        $itemCostEstimates = [];
        $totalEstimatedCost = 0.0;
        foreach ($items as $item) {
            if ($item->isSegueItem()) {
                continue;
            }
            $unitCost   = $costEstimator->estimateOrderItemUnitCost($item);
            $lineCost   = $costEstimator->estimateOrderItemCost($item);
            $coverage   = $costEstimator->orderItemCostCoverage($item);
            $itemCostEstimates[$item->id] = [
                'unit_cost' => $unitCost,
                'line_cost' => $lineCost,
                'coverage'  => $coverage,
            ];
            if ($item->status !== 'cancelled' && !$item->trashed()) {
                $totalEstimatedCost += $lineCost;
            }
        }
        $totalEstimatedCost = round($totalEstimatedCost, 2);
        $effectiveRevenue   = $sale->hasDiscount() ? $sale->getDiscountedTotal() : (float) $sale->total_amount;
        $estimatedMargin    = round($effectiveRevenue - $totalEstimatedCost, 2);
        $marginPercent      = $effectiveRevenue > 0 ? round(($estimatedMargin / $effectiveRevenue) * 100, 1) : null;
        $costPercent        = $effectiveRevenue > 0 ? round(($totalEstimatedCost / $effectiveRevenue) * 100, 1) : null;

        return view('backoffice.sales.show', compact(
            'sale', 'logs', 'printLogs', 'cashDrawerLogs',
            'itemCostEstimates', 'totalEstimatedCost',
            'estimatedMargin', 'marginPercent', 'costPercent'
        ));
    }

    /**
     * KPI aggregate for the Sales page — mirrors the "Venduto" section
     * of the operational log modal, but honours the date range and table filters.
     */
    public function kpis(Request $request): JsonResponse
    {
        $filters = $request->get('filters') ?? [];

        $query = TableOrder::where('status', 'paid')
            ->with('restaurantTable:id,table_number,is_banco');

        if (!empty($filters['date_from'])) {
            $query->whereDate('closed_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('closed_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['table_number'])) {
            $query->whereHas('restaurantTable', function ($q) use ($filters) {
                $q->where('table_number', $filters['table_number']);
            });
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        $paid = $query->get(['id', 'restaurant_table_id', 'total_amount', 'payment_method', 'autoconsumo', 'closed_at']);

        $buckets = [
            'contanti'       => 0.0,
            'pos'            => 0.0,
            'fatture'        => 0.0,
            'autoconsumo'    => 0.0,
            'chiusure_conto' => 0.0,
            'vendite_banco'  => 0.0,
        ];

        $scontriniCount = 0;
        $fattureCount   = 0;

        foreach ($paid as $order) {
            $amount  = (float) $order->total_amount;
            $isBanco = (bool) $order->restaurantTable?->is_banco;

            if ($isBanco) {
                $buckets['vendite_banco'] += $amount;
            }

            if ($order->autoconsumo) {
                $buckets['autoconsumo'] += $amount;
                continue;
            }

            switch ($order->payment_method) {
                case 'contanti':
                    $buckets['contanti']  += $amount;
                    $scontriniCount++;
                    break;
                case 'pos':
                    $buckets['pos']       += $amount;
                    $scontriniCount++;
                    break;
                case 'fattura':
                case 'fattura_contanti':
                case 'fattura_pos':
                case 'bonifico':
                case 'assegno':
                case 'misto':
                    $buckets['fatture'] += $amount;
                    $fattureCount++;
                    break;
                case 'chiusura_conto':
                    $buckets['chiusure_conto'] += $amount;
                    break;
            }
        }

        $totaleIncassato = $buckets['contanti'] + $buckets['pos'] + $buckets['fatture'];

        return response()->json(array_map(fn($v) => round($v, 2), $buckets) + [
            'totale_incassato' => round($totaleIncassato, 2),
            'scontrini_count'  => $scontriniCount,
            'fatture_count'    => $fattureCount,
        ]);
    }

    /**
     * Export sales data (future implementation)
     */
    public function export(Request $request)
    {
        // TODO: Implement export functionality (CSV, PDF, Excel)
        return response()->json(['message' => 'Export functionality coming soon']);
    }

    /**
     * View/download the XML of a table order invoice
     */
    public function invoiceXml(TableOrderInvoice $invoice)
    {
        if (empty($invoice->xml_content)) {
            abort(404, 'XML non disponibile per questa fattura.');
        }

        $vat  = Utils::setting('company_vat_number');
        $base = $invoice->invoice_name ?: 'fattura';
        $filename = ($vat ? 'IT' . $vat . '_' : '') . $base . '.xml';

        return response($invoice->xml_content, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /**
     * Regenerate XML for a table order invoice and re-send via Mysond
     */
    public function invoiceRegenerate(TableOrderInvoice $invoice): JsonResponse
    {
        if (!$invoice->customer) {
            return response()->json(['success' => false, 'message' => 'Nessun cliente associato alla fattura.'], 422);
        }

        try {
            $mySond = app(MysondFatturaService::class);
            $result = $mySond->createInvoice($invoice);

            $updateData = [
                'mysond_response' => is_array($result) ? json_encode($result) : (string) $result,
                'status'          => 'pending',
                'sent_at'         => null,
            ];

            if (($result['response'] ?? '') === 'success') {
                $updateData['xml_content'] = $result['content'] ?? null;
            } else {
                $updateData['status'] = 'error';
            }

            $invoice->update($updateData);

            if (($result['response'] ?? '') === 'success') {
                \App\Jobs\SendInvoiceToMysondJob::dispatch($invoice->id);
            }

            return response()->json([
                'success' => ($result['response'] ?? '') === 'success',
                'message' => ($result['response'] ?? '') === 'success'
                    ? 'XML rigenerato e accodato per invio a MySond.'
                    : 'Errore nella rigenerazione: ' . ($result['message'] ?? 'sconosciuto'),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Invoice regenerate error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Download PDF rendered from invoice XML (reuses existing pdf.blade.php template)
     */
    public function invoicePdf(TableOrderInvoice $invoice)
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
            'linee'     => $linee,
            'riepilogo' => $riepilogo,
            'pagamento' => $pagamento,
        ];

        $filename = ($invoice->invoice_name ?? 'fattura') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('backoffice.invoices.pdf', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->stream($filename);
    }

    public function tables(): View
    {
        return view('backoffice.sales.tables');
    }

    /**
     * Get datatable data
     */
    public function datatable_tables(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            // Get only paid orders (completed sales)
            $query = TableOrder::with(['restaurantTable', 'items.dish', 'waiter'])
                ->where('status', 'open')
                ->orderBy('created_at', 'desc');

            // Apply filters
            if (!empty($filters['date_from'])) {
                $query->whereDate('closed_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('closed_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['table_number'])) {
                $query->whereHas('restaurantTable', function ($q) use ($filters) {
                    $q->where('table_number', $filters['table_number']);
                });
            }

            $elements = $query->get();

            return $this->editColumns(datatables()->of($elements), $this->name, ['edit'], null, 'restaurant.sales')
                ->addColumn('sale_info', function ($item) {
                    $date = $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-';
                    $table = $item->restaurantTable ? 'Tavolo ' . $item->restaurantTable->table_number : 'N/A';
                    return '<strong>' . $table . '</strong><br><small>' . $date . '</small>';
                })
                ->addColumn('items_count', function ($item) {
                    return $item->items->count() . ' prodotti';
                })
                ->addColumn('total', function ($item) {
                    if ($item->autoconsumo) {
                        return '<strong style="text-decoration: line-through">' . Utils::price($item->total_amount) . '</strong><br /><b>Autoconsumo</b>';
                    }
                    return '<strong>' . Utils::price($item->total_amount) . '</strong>';
                })
                ->addColumn('waiter', function ($item) {
                    return $item->waiter ? $item->waiter->name : '-';
                })
                ->addColumn('duration', function ($item) {
                    if ($item->opened_at) {
                        $minutes = $item->opened_at->diffInMinutes(Carbon::now());
                        if ($minutes > 60) {
                            $minutes = $item->opened_at->diffInHours(Carbon::now());
                            return round($minutes, 2) . ' h';
                        }
                        return $minutes . ' min';
                    }
                    return '-';
                })
                ->rawColumns(['sale_info', 'total', 'action'])
                ->make(true);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
