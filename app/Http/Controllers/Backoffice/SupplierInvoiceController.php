<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Http\Controllers\Backend\Requests\SupplierInvoiceRequest;
use App\Interfaces\SupplierInterface;
use App\Interfaces\SupplierInvoiceInterface;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierInvoiceController extends BaseController
{
    use DatatableTrait;

    protected SupplierInvoiceInterface $interface;
    protected SupplierInterface $supplier;

    public function __construct(
        SupplierInvoiceInterface $interface,
        SupplierInterface $supplier,
    )
    {
        $this->interface = $interface;
        $this->supplier = $supplier;
    }

    public function index( Request $request): View
    {
        try {
            $order_id = $request->get('order_id');
            $order = isset($order_id) ? $this->interface->find($order_id) : null;
            return view('backoffice.pages.suppliers.invoices.index', [
                'order' => $order,
                'suppliers' => $this->supplier->filters()->orderBy('label', 'ASC'),
                'brands' => $this->brand->filters()->orderBy('label', 'ASC'),
            ]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            $elements = $this->interface->filters($request->get('filters'))->orderBy('id', 'DESC');
            return $this->editColumns(datatables()->of($elements), 'suppliers.invoices', ['edit', 'invoice-delivery-notes', 'remove'])
                ->addColumn('details', function ($item) {
                    return "<h4>" . $item->invoice_number . "</h4><b>" . $item->brand_label ."</b><br /><small>Fornitore: " . $item->supplier_label . "</small><br />Del " . Utils::data_long($item->created_at);
                })
                ->addColumn('delivery_notes', function ($item) {
                    $sum = 0;
                    $amount = 0;
                    $return = '
                    <table class="table table-bordered table-sm">
                    <tr><th>Bolla #</th><th>Ordine #</th><th>Pezzi (' . $item->pieces . ') </th><th>Totale (' .  Utils::price($item->amount) . ')</th><th>Consegnata il</th></tr>';
                    if ($item->delivery_notes()->count() > 0) {
                        foreach ($item->delivery_notes()->get() as $delivery_note) {
                            $return .= '<tr>
                            <td>' . $delivery_note->delivery_note->delivery_code . '</td>
                            <td>' . $delivery_note->delivery_note->order->external_code. '</td>
                            <td>' . $delivery_note->delivery_note->pieces. ' | ' . $delivery_note->delivery_note->products()->count() . ' <small>Caricati</small></td>
                            <td>' . Utils::price($delivery_note->delivery_note->total_cost) . '</td>
                            <td>' . Utils::data($delivery_note->delivery_note->delivery_at) . '</td>
                            </tr>';
                            $sum += $delivery_note->delivery_note->products()->count();
                            $amount += $delivery_note->delivery_note->total_cost;
                        }
                    }

                    $return .= '<tr><th colspan="2"></th><th>' . $sum . '</th><th>' . Utils::price($amount) . '</th><th></th></tr>';
                    $return .= '</table>';
                    return $return;
                })
                ->setRowClass(function ($item) {
                    $totalPieces = 0;
                    $totalAmount = 0;

                    foreach ($item->delivery_notes()->get() as $deliveryNoteRelation) {
                        $deliveryNote = $deliveryNoteRelation->delivery_note;
                        $totalPieces += $deliveryNote->products()->count();
                        $totalAmount += $deliveryNote->total_cost;
                    }

                    $piecesMatch = $item->pieces == $totalPieces;
                    $amountMatch = abs($item->amount - $totalAmount) < 0.01; // Float comparison

                    return ($piecesMatch && $amountMatch) ? 'invoice-success' : 'invoice-danger';
                })
                ->rawColumns(['details', 'delivery_notes'])
                ->toJson();
        }
        catch (\Exception $e) {
            dd($e);
            return $this->exception($e, $request);
        }
    }

    public function create(Request $request) : View {
        try {
            return view('backoffice.pages.suppliers.invoices.create', [
                'suppliers' => $this->supplier->filters()->orderBy('label', 'ASC'),
            ]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function store(SupplierInvoiceRequest $request) : JsonResponse {
        try {
            $validated = $request->validated();
            $validated['invoice_at'] = Utils::dataFromIta($validated['invoice_at']);
            $this->interface->store($validated);
            return $this->success(['url' => route('suppliers.refunds')]);
        }
        catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function show(int $id, Request $request) : View
    {
        try {
            $invoice = $this->interface->find($id);
            return view('backoffice.pages.suppliers.invoices.edit', [
                'object' => $invoice,
                'suppliers' => $this->supplier->filters()->orderBy('label', 'ASC'),
            ]);

        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function edit(int $id, SupplierInvoiceRequest $request) : JsonResponse
    {
        try {
            $invoice = $this->interface->find($id);
            if ($invoice->id) {
                $invoice->update($request->validated());
                return $this->success();
            }
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function assign_delivery_note_modal(int $id, Request $request) : JsonResponse
    {
        try {
            $invoice = $this->interface->find($id);
            if ($invoice->id) {
                $delivery_notes = $this->deliveryNote->builder()
                    ->orderBy('id', 'DESC')
                    ->where('brand_id', $invoice->brand_id)
                    ->get();
                $invoice_delivery_notes = $invoice->delivery_notes()->pluck('supplier_delivery_note_id')->toArray();
                return $this->success([
                    'html' => view('backoffice.pages.suppliers.invoices.assign-delivery-notes', compact('delivery_notes', 'invoice', 'invoice_delivery_notes'))->render()
                ]);
            }
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function assign_delivery_note(int $id, Request $request) : JsonResponse
    {
        try {
            $invoice = $this->interface->find($id);
            if ($invoice->id) {
                $delivery_note_id = $request->get('delivery_note_id');
                $invoice_delivery_notes = $invoice->delivery_notes()->pluck('supplier_delivery_note_id')->toArray();
                if (in_array($delivery_note_id, $invoice_delivery_notes)) {
                    $invoice->delivery_notes()->where('supplier_delivery_note_id', $delivery_note_id)->delete();
                    return $this->success(['operation' => 'deleted']);
                } else {
                    $invoice->delivery_notes()->updateOrCreate([
                        'supplier_delivery_note_id' => $delivery_note_id
                    ], [
                        'supplier_delivery_note_id' => $delivery_note_id
                    ]);
                    return $this->success(['operation' => 'added']);
                }
            }
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function delete(int $id, Request $request) : JsonResponse
    {
        try {
            $invoice = $this->interface->find($id);
            if ($invoice->id) {
                if (filled($invoice->filename)){
                    unlink(storage_path() . '/suppliers/invoices/' . $invoice->filename);
                }
                $invoice->delete();
                return $this->success();
            }
        } catch (Exception $e) {
            return $this->exception($e, $request);
        }
    }
}
