<?php

namespace App\Http\Controllers\Backoffice;

use App\Jobs\SendInvoiceToMysondJob;
use App\Models\Customer;
use App\Models\TableOrderInvoice;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AccountingController extends BaseController
{
    use DatatableTrait;

    protected string $name = 'accounting.invoices';

    public function invoices(): View
    {
        $customers = Customer::orderBy('full_name')->get(['id', 'full_name'])->map(function ($c) {
            return ['id' => $c->id, 'label' => $c->full_name];
        })->toArray();

        return view('backoffice.accounting.invoices.index', compact('customers'));
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            $query = TableOrderInvoice::with(['customer', 'tableOrder'])
                ->orderBy('created_at', 'desc');

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

            $elements = $query->get();

            return datatables()->of($elements)
                ->addColumn('code', function ($item) {
                    $name = $item->invoice_name ? ' <small class="text-muted">(' . $item->invoice_name . ')</small>' : '';
                    return '<strong>' . e($item->invoice_code) . '</strong>' . $name;
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
                ->addColumn('created_fmt', function ($item) {
                    return $item->created_at ? $item->created_at->format('d/m/Y H:i') : '—';
                })
                ->addColumn('sent_fmt', function ($item) {
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
                        $xmlBtn = '<a href="' . route('accounting.invoices.xml', $item->id) . '" target="_blank" class="btn btn-xs btn-info" title="XML"><i class="fa fa-file-code"></i></a> ';
                    }
                    $resendBtn = '';
                    if (in_array($item->status, ['error', 'pending']) && !empty($item->xml_content)) {
                        $resendBtn = '<button class="btn btn-xs btn-warning btn-resend-invoice" data-id="' . $item->id . '" title="Re-invia"><i class="fa fa-paper-plane"></i></button>';
                    }
                    return $xmlBtn . $resendBtn;
                })
                ->rawColumns(['code', 'status_badge', 'mysond_desc', 'action'])
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
            ->header('Content-Disposition', 'inline; filename="' . ($invoice->invoice_name ?? 'fattura') . '.xml"');
    }

    public function resend(TableOrderInvoice $invoice): JsonResponse
    {
        if (empty($invoice->xml_content)) {
            return $this->error(['message' => 'XML non disponibile — impossibile re-inviare.']);
        }

        $invoice->update(['status' => 'pending']);
        SendInvoiceToMysondJob::dispatch($invoice->id);

        return $this->success(['message' => 'Fattura re-accodata per invio.']);
    }
}
