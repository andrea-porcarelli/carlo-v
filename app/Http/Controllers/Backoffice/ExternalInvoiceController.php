<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Models\ExternalInvoice;
use App\Models\MappingProduct;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Traits\DatatableTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExternalInvoiceController extends BaseController
{
    use DatatableTrait;

    protected string $view;

    public function __construct()
    {
        $this->view = 'backoffice.external-invoices';
    }

    public function index(): View
    {
        $months = Utils::map_array(collect([1,2,3,4,5,6,7,8,9,10,11,12])->mapWithKeys(function ($month) {
            return [$month => ucfirst(Carbon::create()->month($month)->locale('it')->translatedFormat('F'))];
        })->toArray());
        $years = Utils::map_simple_array(collect([
            Carbon::now()->subYear()->year,
            Carbon::now()->year,
            Carbon::now()->addYear()->year,
        ])->toArray());
        $document_types = Utils::map_simple_array(
            ExternalInvoice::query()->whereNotNull('document_type')->distinct()->orderBy('document_type')->pluck('document_type')->toArray()
        );
        $statuses = Utils::map_simple_array(
            ExternalInvoice::query()->whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->toArray()
        );
        return view($this->view . '.index', compact('months', 'years', 'document_types', 'statuses'));
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            $query = ExternalInvoice::query()
                ->with(['parties', 'lines'])
                ->when(!empty($filters['supplier']), function ($q) use ($filters) {
                    $q->whereHas('parties', function ($q2) use ($filters) {
                        $q2->where('type', 'supplier')
                            ->where(function ($q3) use ($filters) {
                                $q3->where('company_name', 'like', '%' . $filters['supplier'] . '%')
                                    ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ['%' . $filters['supplier'] . '%']);
                            });
                    });
                })
                ->when(!empty($filters['document_type']), fn($q) => $q->where('document_type', $filters['document_type']))
                ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
                ->when(!empty($filters['month']), fn($q) => $q->whereMonth('date', $filters['month']))
                ->when(!empty($filters['year']), fn($q) => $q->whereYear('date', $filters['year']))
                ->orderBy('date', 'DESC')
                ->orderBy('id', 'DESC');

            return $this->editColumns(datatables()->of($query), 'external-invoices', [])
                ->addColumn('number_date', function ($item) {
                    return '<b>' . e($item->number ?? '-') . '</b><br /><small>' . ($item->date ? $item->date->format('d/m/Y') : '-') . '</small>';
                })
                ->addColumn('supplier', function ($item) {
                    $party = $item->parties->where('type', 'supplier')->first();
                    if (!$party) return '-';
                    $name   = $party->company_name ?: trim($party->first_name . ' ' . $party->last_name);
                    $detail = $party->vat_number ?: $party->tax_code;
                    return '<b>' . e($name) . '</b>' . ($detail ? '<br /><small>' . e($detail) . '</small>' : '');
                })
                ->addColumn('lines_col', function ($item) {
                    return $item->lines->count();
                })
                ->addColumn('total', function ($item) {
                    return '<strong>' . number_format($item->total_amount ?? 0, 2, ',', '.') . ' €</strong>';
                })
                ->addColumn('status_col', function ($item) {
                    return $this->renderStatusBadge($item);
                })
                ->addColumn('actions_col', function ($item) {
                    return $this->renderActions($item);
                })
                ->rawColumns(['number_date', 'supplier', 'total', 'status_col', 'actions_col'])
                ->toJson();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    /**
     * Pagina mapping prodotti: righe senza MappingProduct e non ignorate.
     */
    public function mapping(int $id): View
    {
        $invoice = ExternalInvoice::with('lines', 'parties')->findOrFail($id);

        $mappedNames = MappingProduct::pluck('material_id', 'product_name');

        $linesToMap = $invoice->lines
            ->where('ignore_mapping', false)
            ->filter(fn($line) => !isset($mappedNames[$line->description]));

        $materials = Material::orderBy('label')->get();

        return view($this->view . '.map-products', compact('invoice', 'linesToMap', 'materials', 'mappedNames'));
    }

    /**
     * Salva il mapping manuale delle righe e ricalcola lo status della fattura.
     */
    public function store_mapping(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'mappings'    => 'required|array',
            'multipliers' => 'array',
            'multipliers.*' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $invoice  = ExternalInvoice::with('lines')->findOrFail($id);
        $mappings = $request->input('mappings', []);
        $multipliers = $request->input('multipliers', []);

        DB::beginTransaction();

        try {
            foreach ($mappings as $lineId => $materialId) {
                $line = $invoice->lines->find($lineId);
                if (!$line) {
                    continue;
                }

                $multiplier = isset($multipliers[$lineId]) ? (float) $multipliers[$lineId] : 1;
                $multiplier = $multiplier > 0 ? $multiplier : 1;

                if ($materialId === '0') {
                    $line->update(['ignore_mapping' => true]);
                } else {
                    $line->update(['quantity_multiplier' => $multiplier]);

                    if ($materialId) {
                        MappingProduct::firstOrCreate(
                            ['product_name' => $line->description],
                            ['material_id'  => $materialId]
                        );
                    }
                }
            }

            DB::commit();

            // Ricalcola status
            $this->recomputeInvoiceStatus($invoice->fresh('lines'));

            return $this->success();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Pagina conferma carichi: mostra le righe mappate con il carico netto calcolato.
     */
    public function confirm_stock(int $id): View
    {
        $invoice = ExternalInvoice::with(['lines.material', 'lines.stock', 'parties'])->findOrFail($id);

        $mappedLines  = $invoice->lines->where('ignore_mapping', false)->filter(fn($l) => $l->material !== null);
        $ignoredLines = $invoice->lines->where('ignore_mapping', true);
        $unmappedLines = $invoice->lines->where('ignore_mapping', false)->filter(fn($l) => $l->material === null);

        $supplier = $invoice->parties->where('type', 'supplier')->first();

        return view($this->view . '.confirm-stock', compact(
            'invoice',
            'mappedLines',
            'ignoredLines',
            'unmappedLines',
            'supplier'
        ));
    }

    /**
     * Crea i MaterialStock per le righe mappate e porta la fattura a stock_confirmed.
     */
    public function store_stock(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'multipliers'   => 'array',
            'multipliers.*' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $invoice = ExternalInvoice::with(['lines.material', 'lines.stock'])->findOrFail($id);
        $multipliers = $request->input('multipliers', []);

        DB::beginTransaction();

        try {
            $mappedLines = $invoice->lines
                ->where('ignore_mapping', false)
                ->filter(fn($l) => $l->material !== null);

            foreach ($mappedLines as $line) {
                // Aggiorna il moltiplicatore se passato dal form di conferma
                if (isset($multipliers[$line->id])) {
                    $multiplier = max((float) $multipliers[$line->id], 0.001);
                    $line->update(['quantity_multiplier' => $multiplier]);
                    $line->refresh();
                }

                if ($line->stock) {
                    // Già confermato in precedenza, salta
                    continue;
                }

                $realQty = ($line->quantity ?? 1) * ($line->quantity_multiplier ?? 1);

                MaterialStock::create([
                    'material_id'              => $line->material->id,
                    'external_invoice_line_id' => $line->id,
                    'stock'                    => $realQty,
                    'purchase_date'            => $invoice->date,
                    'purchase_price'           => $line->unit_price,
                ]);
            }

            ExternalInvoice::withoutEvents(function () use ($invoice) {
                $invoice->update(['status' => 'stock_confirmed']);
            });

            DB::commit();

            return $this->success();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(['message' => $e->getMessage()]);
        }
    }

    // ── Helpers privati ───────────────────────────────────────────────────────

    private function recomputeInvoiceStatus(ExternalInvoice $invoice): void
    {
        if (in_array($invoice->status, ['import_error', 'stock_confirmed'])) {
            return;
        }

        $descriptions = $invoice->lines->where('ignore_mapping', false)->pluck('description')->unique();
        $mappedNames  = MappingProduct::whereIn('product_name', $descriptions)->pluck('product_name')->flip();

        $hasPending = $invoice->lines
            ->where('ignore_mapping', false)
            ->contains(fn($line) => !isset($mappedNames[$line->description]));

        $newStatus = $hasPending ? 'pending_mapping' : 'pending_confirmation';

        ExternalInvoice::withoutEvents(fn() => $invoice->update(['status' => $newStatus]));
    }

    private function renderStatusBadge(ExternalInvoice $item): string
    {
        return match ($item->status) {
            'import_error'        => '<span class="label label-danger"><span class="glyphicon glyphicon-warning-sign"></span> Errore import</span>',
            'pending_mapping'     => '<span class="label label-warning"><span class="glyphicon glyphicon-tag"></span> Da mappare</span>',
            'pending_confirmation'=> '<span class="label label-primary"><span class="glyphicon glyphicon-check"></span> Da confermare</span>',
            'stock_confirmed'     => '<span class="label label-success"><span class="glyphicon glyphicon-ok-circle"></span> Stock confermato</span>',
            default               => '<span class="label label-default">' . e($item->status ?? 'N/D') . '</span>',
        };
    }

    private function renderActions(ExternalInvoice $item): string
    {
        $html = '';

        if ($item->status === 'pending_mapping') {
            $url  = route('external-invoices.mapping', $item->id);
            $html .= '<a href="' . $url . '" class="btn btn-xs btn-warning">
                        <span class="glyphicon glyphicon-tag"></span> Mappa prodotti
                      </a> ';
        }

        if ($item->status === 'pending_confirmation') {
            $url  = route('external-invoices.confirm-stock', $item->id);
            $html .= '<a href="' . $url . '" class="btn btn-xs btn-primary">
                        <span class="glyphicon glyphicon-check"></span> Conferma carichi
                      </a> ';
        }

        if ($item->status === 'import_error') {
            $html .= '<span class="text-muted small" title="' . e($item->import_error) . '">
                        <span class="glyphicon glyphicon-info-sign"></span>
                      </span>';
        }

        return $html;
    }
}
