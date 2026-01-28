<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Interfaces\MaterialInterface;
use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\Printer;
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

            $elements = $this->interface->filters($filters);
            return $this->editColumns(datatables()->of($elements), $this->name, ['edit', 'add-stock'], null, 'restaurant.materials')
                ->addColumn('dishes', function ($item) {
                   return $item->dishes->count();
                })
                ->addColumn('stock', function ($item) {
                   return $item->stock . ' ' . $item->stock_type;
                })
                ->rawColumns(['dishes', 'printer'])
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
                'label' => 'required',
                'stock' => 'required',
                'stock_type' => 'required',
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
                return view('backoffice.' . $this->name . '.edit', compact('object', 'stock_types', 'stockSummary', 'movements'));
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
                'label' => 'required',
                'stock' => 'required',
                'stock_type' => 'required',
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
}
