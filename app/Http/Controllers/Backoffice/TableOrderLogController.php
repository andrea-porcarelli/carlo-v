<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\TableOrderLog;
use App\Models\TableOrder;
use App\Models\User;
use App\Services\TableOrderLoggerService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TableOrderLogController extends Controller
{
    protected TableOrderLoggerService $logger;

    public function __construct(TableOrderLoggerService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Display all logs with filters
     */
    public function index(Request $request)
    {
        $query = TableOrderLog::with(['user:id,name', 'tableOrder.restaurantTable', 'orderItem.dish:id,name'])
            ->orderBy('created_at', 'desc');

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by table order
        if ($request->filled('table_order_id')) {
            $query->where('table_order_id', $request->table_order_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50);

        // Get all users for filter dropdown
        $users = User::select('id', 'name')->orderBy('name')->get();

        // Get all available actions
        $actions = TableOrderLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('backoffice.logs.table-orders', compact('logs', 'users', 'actions'));
    }

    /**
     * Show logs for a specific table order
     */
    public function show(TableOrder $tableOrder)
    {
        $logs = $this->logger->getLogsForOrder($tableOrder->id);

        return view('backoffice.logs.table-order-detail', compact('tableOrder', 'logs'));
    }

    /**
     * Show logs for a specific user
     */
    public function userLogs(User $user, Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

        $logs = $this->logger->getLogsForUser($user->id);
        $stats = $this->logger->getUserStats($user->id, $startDate, $endDate);

        return view('backoffice.logs.user-activity', compact('user', 'logs', 'stats', 'startDate', 'endDate'));
    }

    /**
     * Export logs to CSV
     */
    public function export(Request $request)
    {
        $query = TableOrderLog::with(['user:id,name', 'tableOrder.restaurantTable', 'orderItem.dish:id,name'])
            ->orderBy('created_at', 'desc');

        // Apply same filters as index
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('table_order_id')) {
            $query->where('table_order_id', $request->table_order_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->get();

        $filename = 'table_order_logs_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Data/Ora',
                'Operatore',
                'Azione',
                'Tipo Entità',
                'Tavolo',
                'Ordine ID',
                'Prodotto',
                'Note',
                'IP Address',
            ]);

            // Data rows
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at->format('d/m/Y H:i:s'),
                    $log->user?->name ?? 'N/D',
                    $log->getActionDescription(),
                    $log->entity_type,
                    $log->tableOrder?->restaurantTable?->table_number ?? 'N/D',
                    $log->table_order_id ?? 'N/D',
                    $log->orderItem?->dish?->name ?? 'N/D',
                    $log->notes ?? '',
                    $log->ip_address ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get activity summary for dashboard
     */
    public function activitySummary(Request $request)
    {
        $period = $request->input('period', 'today'); // today, week, month

        $startDate = match($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            default => Carbon::today(),
        };

        $logs = TableOrderLog::where('created_at', '>=', $startDate)->get();

        $summary = [
            'total_actions' => $logs->count(),
            'orders_created' => $logs->where('action', 'create_order')->count(),
            'items_added' => $logs->where('action', 'add_item')->count(),
            'items_removed' => $logs->where('action', 'remove_item')->count(),
            'orders_closed' => $logs->where('action', 'close_order')->count(),
            'most_active_users' => $logs->groupBy('user_id')
                ->map(function($userLogs) {
                    return [
                        'user_name' => $userLogs->first()->user?->name ?? 'N/D',
                        'count' => $userLogs->count(),
                    ];
                })
                ->sortByDesc('count')
                ->take(5)
                ->values(),
            'actions_by_hour' => $logs->groupBy(function($log) {
                return $log->created_at->format('H:00');
            })->map->count(),
        ];

        return response()->json($summary);
    }
}
