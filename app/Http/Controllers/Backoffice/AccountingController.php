<?php

namespace App\Http\Controllers\Backoffice;

use App\Jobs\SendInvoiceToMysondJob;
use App\Models\Customer;
use App\Models\TableOrderInvoice;
use App\Services\MysondFatturaService;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
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

    /**
     * Render the quick-invoice wizard page (standalone invoice issuance).
     */
    public function createInvoice(): View
    {
        return view('backoffice.accounting.invoices.create');
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
                        $xmlBtn = '<a href="' . route('accounting.invoices.xml', $item->id) . '" target="_blank" class="btn btn-xs btn-info" title="Apri XML"><i class="fa fa-file-code"></i></a> '
                                . '<a href="' . route('accounting.invoices.xml-download', $item->id) . '" class="btn btn-xs btn-info" title="Scarica XML"><i class="fa fa-download"></i></a> ';
                    }
                    $resendBtn = '';
                    if (in_array($item->status, ['error', 'pending'])) {
                        $title = !empty($item->xml_content) ? 'Re-invia' : 'Rigenera XML e invia';
                        $resendBtn = '<button class="btn btn-xs btn-warning btn-resend-invoice" data-id="' . $item->id . '" title="' . $title . '"><i class="fa fa-paper-plane"></i></button>';
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

    public function xmlDownload(TableOrderInvoice $invoice): Response
    {
        if (empty($invoice->xml_content)) {
            abort(404, 'XML non disponibile per questa fattura.');
        }
        $filename = ($invoice->invoice_name ?? ('fattura-' . $invoice->id)) . '.xml';
        return response($invoice->xml_content, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function resend(TableOrderInvoice $invoice): JsonResponse
    {
        // Se manca l'XML (es. precedente errore in fase di generazione),
        // proviamo a rigenerarlo ora prima di rimettere la fattura in coda.
        if (empty($invoice->xml_content)) {
            $invoice->loadMissing('customer');
            if (!$invoice->customer) {
                return $this->error(['message' => 'Cliente associato non trovato — impossibile rigenerare XML.']);
            }

            try {
                $xmlResult = app(MysondFatturaService::class)->createInvoice($invoice);
            } catch (\Throwable $e) {
                Log::error('Resend invoice XML regeneration error', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
                $invoice->update([
                    'status'          => 'error',
                    'mysond_response' => json_encode([
                        'response' => 'error',
                        'message'  => $e->getMessage(),
                    ]),
                ]);
                return $this->error(['message' => 'Errore rigenerazione XML: ' . $e->getMessage()]);
            }

            $responsePayload = is_array($xmlResult) ? json_encode($xmlResult) : (string) $xmlResult;

            if (($xmlResult['response'] ?? '') !== 'success') {
                $invoice->update([
                    'status'          => 'error',
                    'mysond_response' => $responsePayload,
                ]);
                return $this->error([
                    'message' => 'Errore rigenerazione XML: ' . ($xmlResult['message'] ?? 'sconosciuto'),
                ]);
            }

            $invoice->update([
                'status'          => 'pending',
                'xml_path'        => $xmlResult['path'] ?? null,
                'xml_content'     => $xmlResult['content'] ?? null,
                'mysond_response' => $responsePayload,
            ]);
        } else {
            $invoice->update(['status' => 'pending']);
        }

        SendInvoiceToMysondJob::dispatch($invoice->id);

        return $this->success(['message' => 'Fattura re-accodata per invio.']);
    }

    public function customers(): View
    {
        return view('backoffice.accounting.customers.index');
    }

    public function customersDatatable(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            $query = Customer::withCount('tableOrderInvoices')
                ->orderBy('full_name');

            if (!empty($filters['search'])) {
                $q = $filters['search'];
                $query->where(function ($sub) use ($q) {
                    $sub->where('full_name', 'like', "%{$q}%")
                        ->orWhere('fiscal_code', 'like', "%{$q}%")
                        ->orWhere('vat_number', 'like', "%{$q}%");
                });
            }
            if (!empty($filters['user_type'])) {
                $query->where('user_type', $filters['user_type']);
            }

            $elements = $query->get();

            return datatables()->of($elements)
                ->addColumn('type_label', function ($item) {
                    return match ($item->user_type) {
                        'private'        => '<span class="label label-default">Privato</span>',
                        'company'        => '<span class="label label-info">Azienda</span>',
                        'public_company' => '<span class="label label-primary">PA</span>',
                        default          => '—',
                    };
                })
                ->addColumn('identifier', function ($item) {
                    $parts = [];
                    if ($item->vat_number)  $parts[] = 'P.IVA ' . e($item->vat_number);
                    if ($item->fiscal_code) $parts[] = 'CF ' . e($item->fiscal_code);
                    return implode('<br>', $parts) ?: '—';
                })
                ->addColumn('address_full', function ($item) {
                    $parts = array_filter([$item->address, $item->zip_code, $item->city, $item->province ? '(' . $item->province . ')' : null]);
                    return e(implode(' ', $parts)) ?: '—';
                })
                ->addColumn('destinatario', function ($item) {
                    $parts = [];
                    if ($item->codice_destinatario) $parts[] = 'SDI: ' . e($item->codice_destinatario);
                    if ($item->pec_destinatario)    $parts[] = 'PEC: ' . e($item->pec_destinatario);
                    return implode('<br>', $parts) ?: '—';
                })
                ->addColumn('invoices_count', function ($item) {
                    return (int) ($item->table_order_invoices_count ?? 0);
                })
                ->rawColumns(['type_label', 'identifier', 'destinatario'])
                ->make(true);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
