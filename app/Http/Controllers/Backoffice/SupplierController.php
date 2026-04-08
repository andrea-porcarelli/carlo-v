<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Interfaces\SupplierInterface;
use App\Models\MappingProduct;
use App\Models\SupplierInvoiceProduct;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends BaseController
{
    use DatatableTrait;

    protected SupplierInterface $interface;

    public function __construct(
        SupplierInterface $interface,
    )
    {
        $this->interface = $interface;
    }

    public function index() : View {
        return view('backoffice.suppliers.index');
    }

    public function productComparison(): View
    {
        // Raggruppa le mappature per material_id
        $mappings = MappingProduct::with('material')
            ->whereNotNull('material_id')
            ->get()
            ->groupBy('material_id');

        $rows = $mappings->map(function ($materialMappings) {
            $material    = $materialMappings->first()->material;
            if (!$material) return null;

            $productNames = $materialMappings->pluck('product_name');

            // Tutti gli acquisti validi per questo materiale
            $purchases = SupplierInvoiceProduct::whereIn('product_name', $productNames)
                ->where('quantity_multiplier', '>', 0)
                ->where('ignore_mapping', 0)
                ->whereHas('invoice', fn($q) => $q->whereNull('ignored_at'))
                ->with(['invoice.supplier'])
                ->get()
                ->map(function ($p) {
                    $p->price_per_unit = round($p->price / $p->quantity_multiplier, 4);
                    return $p;
                })
                ->sortBy('price_per_unit')
                ->values();

            if ($purchases->isEmpty()) return null;

            $prices = $purchases->pluck('price_per_unit');
            $best   = $purchases->first(); // già ordinato per price_per_unit ASC

            return [
                'material'        => $material,
                'purchases_count' => $purchases->count(),
                'avg_price'       => round($prices->avg(), 4),
                'min_price'       => $prices->min(),
                'max_price'       => $prices->max(),
                'best'            => [
                    'supplier_name'  => $best->invoice->supplier->company_name ?? '—',
                    'invoice_number' => $best->invoice->invoice_number,
                    'invoice_date'   => $best->invoice->invoice_date?->format('d/m/Y'),
                    'price_per_unit' => $best->price_per_unit,
                ],
                'purchases' => $purchases,
            ];
        })
        ->filter()
        ->sortBy(fn($r) => $r['material']->label)
        ->values();

        return view('backoffice.suppliers.product-comparison', compact('rows'));
    }

    public function datatable(Request $request) : JsonResponse {
        try {
            $filters = $request->get('filters') ?? [];

            $elements = $this->interface->filters($filters);
            return $this->editColumns(datatables()->of($elements), 'suppliers', ['edit'])
                ->addColumn('invoices', function ($item) {
                   return $item->invoices()->count();
                })
                ->addColumn('fiscal_code', function ($item) {
                   return $item->fiscal_code . ' / ' . $item->vat_number;
                })
                ->rawColumns(['referer'])
                ->toJson();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function show(int $id, Request $request) : View {
        try {
            $user = $this->interface->find($id);
            if ($user->id) {
                return view('backoffice.suppliers.edit', [
                    'object' => $user,
                ]);
            }
            throw new Exception('Element not found');
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function update(int $id, Request $request) : JsonResponse {
        try {
            $request->validate([
                'company_name' => 'required',
                'fiscal_code' => 'required',
                'vat_number' => 'required',
            ]);
            $item = $this->interface->find($id);
            if ($item->id) {
                $store = $request->all();
                if ($this->interface->edit($item, $store)) {
                    return $this->success(['user' => $item->toArray()]);
                }
                throw new Exception('Element not updated');
            }
            throw new Exception('Element not found');
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function create(Request $request) : View {
        try {
            return view('backoffice.suppliers.create');
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function store(Request $request) : JsonResponse {
        try {
            $request->validate([
                'company_name' => 'required',
                'fiscal_code' => 'required',
                'vat_number' => 'required',
            ]);
            $store = $request->all();
            $item = $this->interface->store($store);
            return $this->success(['item' => $item->toArray()]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }
}
