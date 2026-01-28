<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Interfaces\PrinterServiceInterface;
use App\Models\Printer;
use App\Models\PrintLog;
use App\Models\TableOrder;
use App\Models\TableOrderLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PrintLogController extends Controller
{
    protected PrinterServiceInterface $printerService;

    public function __construct(PrinterServiceInterface $printerService)
    {
        $this->printerService = $printerService;
    }

    /**
     * Show print logs for a table order
     */
    public function index(TableOrder $tableOrder): View
    {
        $printLogs = PrintLog::with(['printer', 'user'])
            ->where('table_order_id', $tableOrder->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('backoffice.logs.print-logs', [
            'tableOrder' => $tableOrder,
            'printLogs' => $printLogs,
        ]);
    }

    /**
     * Show print preview
     */
    public function preview(PrintLog $printLog): View
    {
        return view('backoffice.logs.print-preview-page', [
            'printLog' => $printLog,
        ]);
    }

    /**
     * Reprint a log entry
     */
    public function reprint(PrintLog $printLog): JsonResponse
    {
        if (!$printLog->printer) {
            return response()->json([
                'success' => false,
                'message' => 'Stampante non disponibile',
            ], 400);
        }

        $success = $this->printerService->reprint($printLog->id);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Ristampa inviata con successo',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Errore durante la ristampa',
        ], 500);
    }

    /**
     * Print history logs
     */
    public function printHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:table_orders,id',
            'printer_id' => 'required|exists:printers,id',
            'categories' => 'required|array|min:1',
        ]);

        $tableOrder = TableOrder::with('restaurantTable')->findOrFail($validated['sale_id']);
        $printer = Printer::findOrFail($validated['printer_id']);

        // Build query for logs
        $query = TableOrderLog::with(['user', 'orderItem.dish'])
            ->where('table_order_id', $tableOrder->id)
            ->orderBy('created_at', 'asc');

        // Filter by categories if not "all"
        if (!in_array('all', $validated['categories'])) {
            $query->whereIn('category', $validated['categories']);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nessun log trovato per le categorie selezionate',
            ], 404);
        }

        try {
            $success = $this->printerService->printHistory($tableOrder, $printer, $logs, Auth::id());

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Storico inviato alla stampante',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Errore durante la stampa',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
            ], 500);
        }
    }
}
