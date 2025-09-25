<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Interfaces\SupplierInterface;
use App\Models\SupplierOrder;
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

    public function datatable(Request $request) : JsonResponse {
        try {
            $filters = $request->get('filters') ?? [];

            $elements = $this->interface->filters($filters);
            return $this->editColumns(datatables()->of($elements), 'suppliers', ['edit'])
                ->addColumn('invoices', function ($item) {
                   return $item->invoices()->count();
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

    public function edit(int $id, Request $request) : JsonResponse {
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

    public function create_entity(Request $request): JsonResponse
    {
        try {
            $categories = $this->category->filters([])->where('category_id', 0)->orderBy('label', 'ASC');
            return $this->success([
                'html' => view('backoffice.pages.suppliers.create_entity', [
                    'type' => $request->get('type'),
                    'row_id' => $request->get('row_id'),
                    'categories' => $categories,
                    'supplier_id' => $request->get('supplier_id'),
                    'brand_id' => $request->get('brand_id'),
                    'supplier_order_id' => $request->get('supplier_order_id'),
                ])->render()
            ]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function store_entity(Request $request): JsonResponse
    {
        try {
            $validate = [
                'type' => 'required|' . Rule::in(['sub-category', 'category', 'color', 'delivery-note'])
            ];
            if ($request->get('type') === 'delivery-note') {
                $validate['delivery_at'] = 'nullable|date_format:Y-m-d';
                $validate['pieces'] = 'nullable|numeric';
                $validate['supplier_order_id'] = 'required|exists:supplier_orders,id';
            }
            $request->validate($validate);
            $category_id = $request->get('category_id');
            $label = $request->get('label');
            $color_code = $request->get('color_code');
            $type = $request->get('type');
            $supplier_id = $request->get('supplier_id');
            $brand_id = $request->get('brand_id');
            $delivery_code = $request->get('delivery_code');
            $pieces = $request->get('pieces');
            $delivery_at = $request->get('delivery_at');
            $supplier_order_id = $request->get('supplier_order_id');
            $invoice_code = $request->get('invoice_code');
            $discount = $request->get('discount');
            $shipping = $request->get('shipping');
            switch ($type) {
                case 'color' :
                    $item = Property::updateOrCreate([
                            'property_type' => 'color',
                            'slug' => Str::slug($label),
                        ], [
                            'label' => $label,
                            'slug' => Str::slug($label),
                            'property_type' => 'color',
                            'color_code' => $color_code
                        ]);
                    break;
                case 'category' :
                        $item = Category::updateOrCreate([
                            'category_id' => 0,
                            'slug' => Str::slug($label),
                        ], [
                            'category_id' => 0,
                            'label' => $label,
                            'slug' => Str::slug($label),
                            'is_active' => 1,
                        ]);
                    break;
                case 'sub-category' :
                    $item = Category::updateOrCreate([
                        'category_id' => $category_id,
                        'slug' => Str::slug($label),
                    ],[
                        'category_id' => $category_id,
                        'label' => $label,
                        'slug' => Str::slug($label),
                        'is_active' => 1,
                    ]);
                    break;
                case 'delivery-note' :
                    $check = SupplierDeliveryNote::where('supplier_id', $supplier_id)
                        ->where('brand_id', $brand_id)
                        ->where('supplier_order_id', $supplier_order_id)
                        ->where('delivery_code', $delivery_code);
                    if ($check->count() == 0) {
                        $item = SupplierDeliveryNote::create([
                            'supplier_id' => $supplier_id,
                            'brand_id' => $brand_id,
                            'supplier_order_id' => $supplier_order_id,
                            'delivery_code' => $delivery_code,
                            'invoice_code' => $invoice_code,
                            'pieces' => $pieces,
                            'discount' => $discount,
                            'shipping' => $shipping,
                            'delivery_at' => $delivery_at,
                        ]);
                    }
                    break;
            }
            if (isset($item)) {
                return $this->success(['item' => $item, 'type' => $type, 'row_id' => $request->get('row_id')]);
            }
            return $this->error(['message' => "Assicurati che l'entità che stai creando non sia già presente"]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function delivery_notes(int $id, Request $request) : JsonResponse {
        try {
            $model = $this->interface->find($id);
            $elements = $model->notes()
                ->where('brand_id', $request->get('brand_id'))
                ->where('supplier_order_id', $request->get('order_id'))
                ->get();
            $results = [['id' => 0, 'text' => 'Seleziona']];
            $results = array_merge($results, $elements->map(function ($item) {
                return ['id' => $item->id, 'text' => $item->id . ' | ' . $item->delivery_code  . ' | ' . $item->pieces . ' pezzi, del ' . Utils::data($item->delivery_at)];
            })->toArray());
            return response()->json(['results' => $results, 'pagination' => ['more' => true]]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function check_label_printer(Request $request) : JsonResponse {
        try {
            Http::get(Utils::setting('printer-server') . '/check-label');
            return $this->success();
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }
}
