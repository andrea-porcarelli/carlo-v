<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Interfaces\PrinterServiceInterface;
use App\Models\Printer;
use App\Models\PrintLog;
use App\Models\RestaurantTable;
use App\Models\TableOrder;
use App\Models\TableOrderLog;
use Carbon\Carbon;
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

    /**
     * Print filtered logs (from sales index page)
     */
    public function printLogsFiltered(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'table_number' => 'nullable|integer',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
            'categories' => 'required|array|min:1',
            'printer_id' => 'required|exists:printers,id',
        ]);

        $printer = Printer::findOrFail($validated['printer_id']);

        // Build query for logs
        $query = TableOrderLog::with(['user', 'orderItem.dish', 'tableOrder.restaurantTable'])
            ->whereDate('created_at', '>=', $validated['date_from'])
            ->whereDate('created_at', '<=', $validated['date_to'])
            ->orderBy('created_at', 'asc');

        // Filter by user
        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        // Filter by table number
        if (!empty($validated['table_number'])) {
            $restaurantTable = RestaurantTable::where('table_number', $validated['table_number'])->first();
            if ($restaurantTable) {
                $tableOrderIds = TableOrder::where('restaurant_table_id', $restaurantTable->id)->pluck('id');
                $query->whereIn('table_order_id', $tableOrderIds);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Tavolo non trovato',
                ], 404);
            }
        }

        // Filter by categories if not "all"
        if (!in_array('all', $validated['categories'])) {
            $query->whereIn('category', $validated['categories']);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nessun log trovato per i filtri selezionati',
            ], 404);
        }

        try {
            $success = $this->printerService->printFilteredLogs(
                $printer,
                $logs,
                [
                    'date_from' => $validated['date_from'],
                    'date_to' => $validated['date_to'],
                    'user_id' => $validated['user_id'] ?? null,
                    'table_number' => $validated['table_number'] ?? null,
                ],
                Auth::id()
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Log inviati alla stampante',
                    'logs_count' => $logs->count(),
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
