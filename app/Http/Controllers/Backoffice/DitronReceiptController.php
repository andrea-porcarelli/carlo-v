<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\DitronReceipt;
use App\Services\DitronReceiptService;
use App\Services\TableOrderLoggerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class DitronReceiptController extends Controller
{
    public function __construct(
        private DitronReceiptService $service,
        private TableOrderLoggerService $logger,
    ) {}

    public function index(Request $request): View
    {
        $this->assertAdmin();

        $from = $request->input('from', Carbon::today()->subDays(7)->toDateString());
        $to   = $request->input('to', Carbon::today()->toDateString());
        $status = $request->input('status');
        $tableNumber = $request->input('table_number');
        $type = $request->input('type', 'all');

        $query = DitronReceipt::query()
            ->with(['tableOrder.restaurantTable', 'operator', 'cancelledByReceipt', 'cancelsReceipt'])
            ->whereBetween('created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ])
            ->orderByDesc('id');

        if ($type === 'sale') {
            $query->sales();
        } elseif ($type === 'cancel') {
            $query->cancels();
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($tableNumber !== null && $tableNumber !== '') {
            $query->whereHas('tableOrder.restaurantTable', fn($q) => $q->where('table_number', (int) $tableNumber));
        }

        $receipts = $query->paginate(50)->withQueryString();

        return view('backoffice.ditron-receipts.index', [
            'receipts'    => $receipts,
            'from'        => $from,
            'to'          => $to,
            'status'      => $status,
            'tableNumber' => $tableNumber,
            'type'        => $type,
        ]);
    }

    /**
     * Preset di proprietà GETP interrogabili dal backoffice, in linea con
     * ecrcomrt.ini [49]. Espansi da UI, non da utente free-text.
     */
    private const GETP_PRESETS = [
        'last_receipt' => [
            'label'      => 'Ultimo scontrino emesso',
            'properties' => [1, 10, 12],
        ],
        'cash_status' => [
            'label'      => 'Stato cassa (matricola + subtotale corrente)',
            'properties' => [1, 9, 10, 11],
        ],
        'last_z' => [
            'label'      => 'Ultimo azzeramento Z',
            'properties' => [12, 16],
        ],
        'last_credit_note' => [
            'label'      => 'Ultima nota di credito',
            'properties' => [1, 17],
        ],
    ];

    public function readGetp(Request $request): View
    {
        $admin = $this->assertAdmin();

        $preset = $request->input('preset', 'last_receipt');
        if (!isset(self::GETP_PRESETS[$preset])) {
            $preset = 'last_receipt';
        }
        $properties = self::GETP_PRESETS[$preset]['properties'];

        Log::channel('corrispettivi')->info('GETP invoked from backoffice', [
            'admin_user_id' => $admin->id,
            'preset'        => $preset,
            'properties'    => $properties,
        ]);

        $result = $this->service->readProperties($properties);

        return view('backoffice.ditron-receipts.getp', [
            'result'     => $result,
            'preset'     => $preset,
            'properties' => $properties,
            'presets'    => self::GETP_PRESETS,
            'labels'     => self::propertyLabels(),
        ]);
    }

    private static function propertyLabels(): array
    {
        return [
            1  => 'Matricola ECR',
            9  => 'N° ultimo scontrino (in transazione)',
            10 => 'N° ultimo scontrino emesso',
            11 => 'Subtotale corrente / ultimo totale',
            12 => 'Data / N° ultimo Z',
            16 => 'Gran Totale ultimo Z',
            17 => 'N° ultima nota di credito',
        ];
    }

    public function retry(DitronReceipt $receipt): RedirectResponse
    {
        $admin = $this->assertAdmin();

        if (!$receipt->canRetry()) {
            return back()->with('error', "Scontrino #{$receipt->id} non è ritentabile (status={$receipt->status}, {$receipt->attempts}/{$receipt->max_attempts} tentativi).");
        }

        Log::channel('corrispettivi')->info('Retry Ditron da backoffice', [
            'receipt_id'    => $receipt->id,
            'admin_user_id' => $admin->id,
            'attempts'      => $receipt->attempts,
        ]);

        try {
            $updated = $this->service->retry($receipt);
        } catch (Throwable $e) {
            return back()->with('error', 'Errore nel retry: ' . $e->getMessage());
        }

        if ($updated->isSent()) {
            $msg = "Scontrino riemesso.";
            if ($updated->fiscal_number) {
                $msg .= " N° fiscale: {$updated->fiscal_number}";
            }
            return back()->with('success', $msg);
        }

        return back()->with('error', 'Retry eseguito ma l\'invio non è riuscito: ' . ($updated->last_error ?? 'errore sconosciuto'));
    }

    public function cancel(Request $request, DitronReceipt $receipt): RedirectResponse
    {
        $admin = $this->assertAdmin();

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return back()->with('error', 'La motivazione dell\'annullo è obbligatoria.');
        }

        if (!$receipt->isCancellable()) {
            return back()->with('error', "Scontrino #{$receipt->id} non è annullabile in questo momento.");
        }

        Log::channel('corrispettivi')->info('Richiesta annullo DOCANNULLO Ditron da backoffice', [
            'sale_receipt_id' => $receipt->id,
            'admin_user_id'   => $admin->id,
            'reason'          => $reason,
        ]);

        try {
            $cancel = $this->service->emitCancel($receipt, $admin, $reason);
        } catch (Throwable $e) {
            return back()->with('error', 'Errore nell\'emissione dell\'annullo: ' . $e->getMessage());
        }

        // Log traccia visibile su TableOrderLog anche se il tavolo non cambia stato.
        $this->logger->logDitronCancelEmitted($receipt->refresh(), $cancel, $reason, $admin->id);

        if ($cancel->isSent()) {
            return back()->with('success', "Annullo emesso. Nuovo fiscal_number: {$cancel->fiscal_number}");
        }

        return back()->with('error', 'Invio annullo alla cassa fallito: ' . ($cancel->last_error ?? 'errore sconosciuto'));
    }

    private function assertAdmin(): \App\Models\User
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Solo un amministratore può gestire gli scontrini Ditron.');
        }
        return $user;
    }
}
