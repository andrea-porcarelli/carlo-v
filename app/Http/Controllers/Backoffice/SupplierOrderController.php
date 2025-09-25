<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Http\Controllers\Controller;
use App\Interfaces\SupplierInterface;
use App\Interfaces\SupplierOrderInterface;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SupplierOrderController extends Controller
{
    use DatatableTrait;

    protected SupplierOrderInterface $interface;
    protected SupplierInterface $supplier;

    public function __construct(
        SupplierOrderInterface $interface,
        SupplierInterface $supplier
    )
    {
        $this->interface = $interface;
        $this->supplier = $supplier;
    }

    public function index() : View {
        return view('backoffice.pages.suppliers.orders.index');
    }

    public function datatable(Request $request) : JsonResponse {
        try {
            ini_set('memory_limit', '512M');
            $filters = $request->get('filters') ?? [];
            $elements = $this->interface->filters($filters)
                ->with(['setting'])
                ->withCount('products');
            if ($filters['has_difference'] == 1) {
                $elements = $elements->get()->filter(function ($item) {
                    $ordered =  $item->products()->sum('stock');
                    $loaded = $item->products()->whereHas('product')->sum('stock');
                    return $ordered != $loaded;
                });
            }
            return $this->editColumns(datatables()->of($elements), 'suppliers.orders', ['show-details', 'supplier-orders'])
                ->addColumn('setting', function ($item) {
                    $text = "<b>" . $item->brand->label . "</b> <hr style='margin: 4px 0' />";
                    if ($item->order_type == 'automatic') {
                        $text .= '<small>Automatico: ' . $item->setting->label . ' <br /> Fornitore: ' . ($item->setting->supplier->label ?? '')  . '</small>';
                    } else {
                        $text .= '<small>Manuale <br /> Fornitore: ' . $item->brand->supplier->label . ' </small>';
                    }
                    return $text;
                })
                ->addColumn('products', function ($item) {
                    return '<table class="table table-bordered table-sm">
                        <tr><th></th><th class="text-center">Acquistati</th><th class="text-center">Caricati</th></tr>
                        <tr><td>Articoli</td><td class="text-center">' . $item->barcodes_count . '</td><td class="text-center">' . $item->barcodes_count_with_product . '</td></tr>
                        <tr><td>Barcode</td><td class="text-center">' . $item->products_count . '</td><td class="text-center">' . $item->products_count_with_product . '</td></tr>
                        <tr><td>Pezzi</td><td class="text-center">' . $item->stock_count . '</td><td class="text-center">' . $item->stock_count_with_product . '</td></tr>
                    </table>';
                })
                ->addColumn('order_at', function ($item) {
                    return Utils::data($item->order_at);
                })
                ->addColumn('season', function ($item) {
                    return $item->season->label;
                })
                ->addColumn('total', function ($item) {
                    return '<table class="table table-bordered table-sm">
                        <tr><td>Ordinato</td><td class="text-right">' . Utils::price($item->total_ordered) . '</td></tr>
                        <tr><td>Caricato</td><td class="text-right">' . Utils::price($item->total_loaded) . '</td></tr>
                    </table>';
                })
                ->addColumn('extra', function ($item) {

                    return '<table class="table table-bordered table-sm">
                        <tr><td>Imponibile</td><td class="text-right">' . Utils::price($item->total_loaded) . '</td></tr>
                        ' . ($item->delivery_notes_shipping > 0 ? '<tr><td>Spedizioni</td><td class="text-right">' . Utils::price($item->delivery_notes_shipping) . '</td></tr>' : '') . '
                        ' . ($item->delivery_notes_discount > 0 || $item->delivery_notes_products_discount > 0 || $item->discount > 0 ? '<tr><td>Sconti</td><td class="text-right">' . Utils::price($item->delivery_notes_discount + $item->delivery_notes_products_discount + $item->discount) . '</td></tr>' : '') . '
                        <tr><td>Parziale</td><td class="text-right">' . Utils::price($item->total_loaded + $item->delivery_notes_shipping - $item->delivery_notes_discount - $item->delivery_notes_products_discount - $item->discount) . '</td></tr>
                        <tr><td>Iva</td><td class="text-right">' . Utils::iva($item->total_loaded + $item->delivery_notes_shipping - $item->delivery_notes_discount - $item->delivery_notes_products_discount - $item->discount, $item->brand->supplier->iva) . '</td></tr>
                        <tr><td><b>Totale</b></td><td class="text-right"><b>' . Utils::price_iva($item->total_loaded + $item->delivery_notes_shipping - $item->delivery_notes_discount - $item->delivery_notes_products_discount - $item->discount, $item->brand->supplier->iva) . '</b></td></tr>
                    </table>';
                })
                ->setRowAttr([
                    'style' => function($item) {
                        $color = '#f6ffc3';
                        if ($item->order_status === 'imported' || $item->stock_count_with_product == $item->stock_count) {
                            $color = '#caffc2';
                        }
                        if ($item->order_status === 'error') {
                            $color = '#ffcfcf';
                        }
                        return 'background: '. $color;
                    },
                ])
                ->rawColumns(['setting', 'products', 'total', 'extra'])
                ->toJson();
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    public function create(Request $request) : View {
        try {
            return view('backoffice.pages.suppliers.orders.create', [
                'settings' => $this->supplierSetting->filters(),
                'seasons' => $this->season->for_select(),
                'brands' => $this->brand->for_select(),
                'types' => $this->interface->types(),
            ]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function store(Request $request) : JsonResponse {
        try {
            $rules = [
                'order_type' => 'required',
                'season_id' => 'required',
                'order_at' => 'required',
            ];
            if ($request->get('order_type') == 'automatic') {
                $rules['filename'] = 'required';
                $rules['supplier_setting_id'] = 'required';
            }
            if ($request->get('order_type') == 'manual') {
                $rules['brand_id'] = 'required';
            }
            $request->validate($rules);
            if ($request->get('order_type') == 'automatic' && strlen($request->get('filename')) === 0) {
                return $this->error(['message' => 'Devi inserire il file da importare ( csv o xls)']);
            }
            $parameters = $request->all();
            if (empty($parameters['brand_id'])) {
                unset($parameters['brand_id']);
            }
            $parameters['order_at'] = isset($parameters['order_at']) ? Utils::dataFromIta($parameters['order_at']) : null;
            $item = $this->interface->store($parameters);
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
                $object->forget_cache();
                return view('backoffice.pages.suppliers.orders.edit', [
                    'object' => $object,
                    'settings' => $this->supplierSetting->filters(),
                    'seasons' => $this->season->for_select(),
                    'brands' => $this->brand->for_select(),
                    'types' => $this->interface->types(),
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
            $rules = [
                'order_type' => 'required',
                'season_id' => 'required',
                'order_at' => 'required',
            ];
            if ($request->get('order_type') == 'automatic') {
                $rules['filename'] = 'required';
                $rules['supplier_setting_id'] = 'required';
            }
            if ($request->get('order_type') == 'manual') {
                $rules['brand_id'] = 'required';
            }
            $request->validate($rules);
            if ($request->get('order_type') == 'automatic' && strlen($request->get('filename')) === 0) {
                return $this->error(['message' => 'Devi inserire il file da importare ( csv o xls)']);
            }
            $item = $this->interface->find($id);
            if ($item->id) {
                if ($this->interface->edit($item, $request->all())) {
                    return $this->success(['object' => $item->toArray()]);
                }
                throw new Exception('Element not updated');
            }
            throw new Exception('Element not found');
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function search(Request $request) : JsonResponse {
        try {
            $fields = $request->get('fields');
            $elements = $this->interface->builder()
                ->where(function ($q) use($fields) {
                    $q->whereRaw("
                        order_type = 'automatic'
                        AND exists (
                            select *
                            from `supplier_settings`
                            where `supplier_orders`.`supplier_setting_id` = `supplier_settings`.`id`
                              and `brand_id` = ?
                        )
                        and exists (
                            select *
                            from `supplier_settings`
                            where `supplier_orders`.`supplier_setting_id` = `supplier_settings`.`id`
                            and `supplier_id` = ?
                      )
                    ")
                    ->orWhereRaw("
                        order_type = 'manual'
                        AND brand_id = ?
                    ");
                })
                ->setBindings([$fields['brand_id'], $fields['supplier_id'], $fields['brand_id']])
                ->get();

            $results = [['id' => 0, 'text' => 'Seleziona']];
            $results = array_merge($results, collect($elements)->map(function ($item) {
                return ['id' => $item->id, 'text' => $item->id . ' | ' . $item->external_code ];
            })->toArray());
            return response()->json(['results' => $results, 'pagination' => ['more' => true]]);
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function print_labels(int $id, Request $request) : Response|JsonResponse {
        try {
            $per_page = 240;
            ini_set('memory_limit', '512M');
            $item = $this->interface->find($id);
            if ($request->filled('delivery_note') && $request->get('delivery_note') != 'undefined') {
                $delivery_note = $item->delivery_notes()->where('id', $request->get('delivery_note'))->first();
                $products = $delivery_note->products()
                    ->orderBy('product_id');
                if ($request->has('ids') && $request->filled('ids')) {
                    $products->whereIn('id', explode(',', $request->get('ids')));
                }
                $products = $products->get();
            } else {
                $products = $item->products()
                    ->whereHas('product')
                    ->orderBy('updated_at');
                if ($request->has('ids') && $request->filled('ids')) {
                    $products->whereIn('id', explode(',', $request->get('ids')));
                }
                $products = $products->get();
            }
            if ($request->get('count') == 1) {
                return $this->success(['pages' => ceil($products->count() / $per_page)]);
            }
            if ($request->get('page')) {
                $page = $request->get('page');
                $products = $products->slice($per_page * ($page - 1), $per_page);
            }
            $pages = ceil($products->count() / 48);
            $pdf = PDF::setOptions([
                    'logOutputFile' => null,
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true
                ])
                ->setPaper('a4')
                ->loadView('backoffice.pages.suppliers.orders.print-labels-pdf', [
                    'products' => $products,
                    'position' => $request->get('position', 0),
                    'delivery_note' => $request->get('delivery_note'),
                    'pages' => $pages,
                ]);
            return $pdf->setWarnings(false)->stream('labels.pdf');
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function print_difference(int $id, Request $request) : Response|JsonResponse {
        try {
            ini_set('memory_limit', '512M');
            $item = $this->interface->find($id);
            $products = $item->products()
                ->orderBy('updated_at')
                ->get();
            $delivery_note_id = $request->get('delivery_note');
            if (filled($delivery_note_id)) {
                $products = $products->filter(function ($product) use ($request, $delivery_note_id) {
                    return $product->delivery_note_product($delivery_note_id);
                })->map(function ($product) use ($request, $delivery_note_id) {
                    $product->delivery_note_id = $product->delivery_note_product($delivery_note_id)->supplier_delivery_note_id;
                    return $product;
                });
            }
            $products = $products->groupBy('manufacturer_code');
            $products_loaded = $item->delivery_notes()->get()->sum(function ($delivery) {
                return $delivery->products()->count();
            });
            $pdf = PDF::setOptions([
                    'logOutputFile' => null,
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true
                ])
                ->setPaper('A4', 'landscape')
                ->loadView('backoffice.pages.suppliers.orders.print-difference-pdf', [
                    'item' => $item,
                    'products' => $products,
                    'products_total' => $item->products->sum('stock'),
                    'products_loaded' => $products_loaded,
                    'total_pages' => ceil($products->count() / 9 ),
                    'delivery_note_id' => $delivery_note_id,
                ]);
            return $pdf->setWarnings(false)->stream('labels.pdf');
        } catch (Exception $e) {
            dd($e);
            return $this->exception($e, $request);
        }
    }

    public function print_delivery_notes(int $id, Request $request) : Response|JsonResponse {
        try {
            $item = $this->interface->find($id);
            $pdf = PDF::setOptions([
                'logOutputFile' => null,
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true
            ])
                ->setPaper('A4', 'landscape')
                ->loadView('backoffice.pages.suppliers.orders.print-delivery-notes-pdf', [
                    'item' => $item,
                ]);
            return $pdf->setWarnings(false)->stream('labels.pdf');
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function order_options(int $id, Request $request): JsonResponse
    {
        try {
            $order = $this->interface->find($id);
            return $this->success([
                'html' => view('backoffice.pages.suppliers.products.order-options', [
                    'order' => $order,
                ])->render()
            ]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function remove(int $id, Request $request) : JsonResponse {
        try {
            $item = $this->interface->find($id);
            if ($item->id) {
                $this->interface->remove($item->id);
                return $this->success();
            }
            throw new Exception('Element not found');
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }
}
