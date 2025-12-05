<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Models\TableOrder;
use App\Services\TableOrderLoggerService;
use App\Traits\DatatableTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends BaseController
{
    use DatatableTrait;

    protected string $name;

    public function __construct()
    {
        $this->name = 'sales';
    }

    /**
     * Display a listing of sales
     */
    public function index(): View
    {
        return view('backoffice.' . $this->name . '.index');
    }

    /**
     * Get datatable data
     */
    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            // Get only paid orders (completed sales)
            $query = TableOrder::with(['restaurantTable', 'items.dish', 'waiter'])
                ->where('status', 'paid')
                ->orderBy('closed_at', 'desc');

            // Apply filters
            if (!empty($filters['date_from'])) {
                $query->whereDate('closed_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('closed_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['table_number'])) {
                $query->whereHas('restaurantTable', function ($q) use ($filters) {
                    $q->where('table_number', $filters['table_number']);
                });
            }

            $elements = $query->get();

            return $this->editColumns(datatables()->of($elements), $this->name, ['edit'], null, 'restaurant.sales')
                ->addColumn('sale_info', function ($item) {
                    $date = $item->closed_at ? $item->closed_at->format('d/m/Y H:i') : '-';
                    $table = $item->restaurantTable ? 'Tavolo ' . $item->restaurantTable->table_number : 'N/A';
                    return '<strong>' . $table . '</strong><br><small>' . $date . '</small>';
                })
                ->addColumn('items_count', function ($item) {
                    return $item->items->count() . ' prodotti';
                })
                ->addColumn('total', function ($item) {
                    return '<strong>' . Utils::price($item->total_amount) . '</strong>';
                })
                ->addColumn('waiter', function ($item) {
                    return $item->waiter ? $item->waiter->name : '-';
                })
                ->addColumn('duration', function ($item) {
                    if ($item->opened_at && $item->closed_at) {
                        $minutes = $item->opened_at->diffInMinutes($item->closed_at);
                        return $minutes . ' min';
                    }
                    return '-';
                })
                ->rawColumns(['sale_info', 'total', 'action'])
                ->make(true);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show sale details
     */
    public function show($id): View
    {
        $sale = TableOrder::with([
            'restaurantTable',
            'items.dish.category',
            'items.dish.allergens',
            'waiter'
        ])->withTrashed()
            ->findOrFail($id);

        // Load logs for this sale
        $loggerService = new TableOrderLoggerService();
        $logs = $loggerService->getLogsForOrder($id);

        return view('backoffice.' . $this->name . '.show', compact('sale', 'logs'));
    }

    /**
     * Export sales data (future implementation)
     */
    public function export(Request $request)
    {
        // TODO: Implement export functionality (CSV, PDF, Excel)
        return response()->json(['message' => 'Export functionality coming soon']);
    }

    public function tables(): View
    {
        return view('backoffice.' . $this->name . '.tables');
    }

    /**
     * Get datatable data
     */
    public function datatable_tables(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            // Get only paid orders (completed sales)
            $query = TableOrder::with(['restaurantTable', 'items.dish', 'waiter'])
                ->where('status', 'open')
                ->orderBy('created_at', 'desc');

            // Apply filters
            if (!empty($filters['date_from'])) {
                $query->whereDate('closed_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('closed_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['table_number'])) {
                $query->whereHas('restaurantTable', function ($q) use ($filters) {
                    $q->where('table_number', $filters['table_number']);
                });
            }

            $elements = $query->get();

            return $this->editColumns(datatables()->of($elements), $this->name, ['edit'], null, 'restaurant.sales')
                ->addColumn('sale_info', function ($item) {
                    $date = $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-';
                    $table = $item->restaurantTable ? 'Tavolo ' . $item->restaurantTable->table_number : 'N/A';
                    return '<strong>' . $table . '</strong><br><small>' . $date . '</small>';
                })
                ->addColumn('items_count', function ($item) {
                    return $item->items->count() . ' prodotti';
                })
                ->addColumn('total', function ($item) {
                    return '<strong>' . Utils::price($item->total_amount) . '</strong>';
                })
                ->addColumn('waiter', function ($item) {
                    return $item->waiter ? $item->waiter->name : '-';
                })
                ->addColumn('duration', function ($item) {
                    if ($item->opened_at) {
                        $minutes = $item->opened_at->diffInMinutes(Carbon::now());
                        if ($minutes > 60) {
                            $minutes = $item->opened_at->diffInHours(Carbon::now());
                            return round($minutes, 2) . ' h';
                        }
                        return $minutes . ' min';
                    }
                    return '-';
                })
                ->rawColumns(['sale_info', 'total', 'action'])
                ->make(true);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
