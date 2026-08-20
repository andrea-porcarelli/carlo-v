<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Interfaces\MaterialInterface;
use App\Models\MappingProduct;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Printer;
use App\Models\SupplierInvoiceProduct;
use App\Services\StockService;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialController extends BaseController
{
    use DatatableTrait;

    protected MaterialInterface $interface;
    protected string $name;
    public function __construct(
        MaterialInterface $interface,
    )
    {
        $this->interface = $interface;
        $this->name = 'materials';
    }

    public function index() : View {
        return view('backoffice.' . $this->name . '.index');
    }

    public function datatable(Request $request) : JsonResponse {
        try {
            $filters = $request->get('filters') ?? [];
            $stockService = app(StockService::class);
            $elements = $this->interface->filters($filters);

            if (!empty($filters['low_stock'])) {
                $lowStockIds = $stockService->getLowStockMaterials()->pluck('material.id')->all();
                $elements = $elements->whereIn('id', $lowStockIds ?: [0]);
            }
            return $this->editColumns(datatables()->of($elements), $this->name, ['edit', 'add-stock'], null, 'restaurant.materials')
                ->addColumn('dishes', function ($item) {
                   return $item->dishes->count();
                })
                ->addColumn('stock', function ($item) use ($stockService) {
                    $stockSummary = $stockService->calculateStock($item);
                    $current = (float) $stockSummary['current'];
                    $html = '<strong>' . $current . '</strong> ' . $item->stock_type;
                    if (!$item->track_stock) {
                        $html .= ' <span class="label label-default" title="Giacenza non tracciata: escluso dagli avvisi Telegram"><i class="fa fa-bell-slash"></i> Non tracciato</span>';
                    } elseif ($stockSummary['is_low']) {
                        $html .= ' <span class="label label-danger" title="Giacenza sotto soglia"><i class="fa fa-exclamation-triangle"></i> Sotto soglia</span>';
                    }
                    return $html;
                })
                ->addColumn('stock_type_label', function ($item) {
                    return $item->stock_type_label;
                })
                ->addColumn('alert_threshold_fmt', function ($item) {
                    if ($item->alert_threshold === null) {
                        return '<span class="text-muted">—</span>';
                    }
                    return $item->alert_threshold . ' ' . $item->stock_type;
                })
                ->rawColumns(['dishes', 'printer', 'stock', 'alert_threshold_fmt'])
                ->toJson();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function create(Request $request) : View {
        try {
            $stock_types = Utils::key_value(Material::stock_types());
            return view('backoffice.' . $this->name . '.create', compact('stock_types'));
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function store(Request $request) : JsonResponse {
        try {
            $request->validate([
                'label'      => [
                    'required',
                    function ($attribute, $value, $fail) {
                        if (Material::whereRaw('LOWER(label) = ?', [strtolower(trim($value))])->exists()) {
                            $fail('Esiste già un ingrediente con questo nome.');
                        }
                    },
                ],
                'stock'       => 'required|numeric|min:0',
                'stock_type'  => ['required', \Illuminate\Validation\Rule::in(array_keys(Material::stock_types()))],
                'track_stock' => 'nullable|boolean',
            ]);
            $store = $request->all();
            $item = $this->interface->store($store);
            return $this->success(['item' => $item->toArray()]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }
    public function show(int $id, Request $request) : View {
        try {
            $object = $this->interface->find($id);
            if ($object->id) {
                $stock_types = Utils::key_value(Material::stock_types());
                $stockService = app(StockService::class);
                $stockSummary = $stockService->calculateStock($object);
                $movements = $stockService->getMovements($object->id);

                $productNames = MappingProduct::where('material_id', $object->id)
                    ->whereNotNull('material_id')
                    ->pluck('product_name');

                $purchases = SupplierInvoiceProduct::whereIn('product_name', $productNames)
                    ->whereHas('invoice', fn($q) => $q->whereNull('ignored_at'))
                    ->with(['invoice.supplier'])
                    ->get()
                    ->map(function ($p) {
                        $p->price_per_unit = ($p->quantity_multiplier > 0)
                            ? round($p->price / $p->quantity_multiplier, 4)
                            : null;
                        return $p;
                    })
                    ->sortByDesc(fn($p) => $p->invoice->invoice_date)
                    ->values();

                $minPrice = $purchases
                    ->where('ignore_mapping', 0)
                    ->where('quantity_multiplier', '>', 0)
                    ->pluck('price_per_unit')
                    ->min() ?? 0;

                $materials = Material::orderBy('label')->get(['id', 'label']);

                return view('backoffice.' . $this->name . '.edit', compact(
                    'object', 'stock_types', 'stockSummary', 'movements',
                    'purchases', 'minPrice', 'materials'
                ));
            }
            throw new Exception('Element not found');
        }
        catch (\Exception $e) {
            dd($e);
            return $this->exception($e, $request);
        }
    }

    public function edit(int $id, Request $request) : JsonResponse {
        try {
            $request->validate([
                'label'       => 'required',
                'stock'       => 'required|numeric|min:0',
                'stock_type'  => ['required', \Illuminate\Validation\Rule::in(array_keys(Material::stock_types()))],
                'track_stock' => 'nullable|boolean',
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

    public function status(int $id): JsonResponse
    {
        try {
            $model = $this->interface->find($id);
            $this->interface->edit($model, ["is_active" => !$model->is_active]);
            return response()->json(['response' => 'success']);
        } catch (\Exception $e) {
            return $this->exception($e, null);
        }
    }

    public function storeStock(int $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'stock' => 'required|numeric|min:0.01',
                'purchase_date' => 'nullable|date',
                'purchase_price' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
            ]);

            $material = $this->interface->find($id);
            if (!$material->id) {
                throw new Exception('Materiale non trovato');
            }

            $stock = MaterialStock::create([
                'material_id' => $material->id,
                'stock' => $request->stock,
                'purchase_date' => $request->purchase_date,
                'purchase_price' => $request->purchase_price,
                'notes' => $request->notes,
            ]);

            return $this->success([
                'stock' => $stock->toArray(),
                'message' => 'Giacenza aggiunta con successo'
            ]);
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function removeStock(int $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'stock' => 'required|numeric|min:0.01',
                'notes' => 'nullable|string|max:1000',
            ]);

            $material = $this->interface->find($id);
            if (!$material->id) {
                throw new Exception('Materiale non trovato');
            }

            $stock = MaterialStock::create([
                'material_id' => $material->id,
                'stock'       => -abs($request->stock),
                'notes'       => $request->notes,
            ]);

            return $this->success([
                'stock'   => $stock->toArray(),
                'message' => 'Quantità rimossa con successo',
            ]);
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }
}
