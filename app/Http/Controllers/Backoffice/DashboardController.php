<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\TableOrder;
use App\Models\TableOrderLog;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(): View
    {
        $data = Cache::remember('dashboard_data', 300, function () {
            return $this->buildDashboardData();
        });

        return view('backoffice.index', $data);
    }

    private function buildDashboardData(): array
    {
        $now = Carbon::now();
        $monthNames = ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'];

        // ── Revenue KPIs (autoconsumo escluso) ──────────────────────────────
        $revenueToday = $this->paidQuery()
            ->whereDate('closed_at', $now->toDateString())
            ->sum('total_amount');

        $revenueWeek = $this->paidQuery()
            ->whereBetween('closed_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->sum('total_amount');

        $revenueMonth = $this->paidQuery()
            ->whereYear('closed_at', $now->year)
            ->whereMonth('closed_at', $now->month)
            ->sum('total_amount');

        $revenueYear = $this->paidQuery()
            ->whereYear('closed_at', $now->year)
            ->sum('total_amount');

        // ── Conteggio ordini ─────────────────────────────────────────────────
        $ordersToday = $this->paidQuery()
            ->whereDate('closed_at', $now->toDateString())
            ->count();

        $ordersWeek = $this->paidQuery()
            ->whereBetween('closed_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->count();

        $ordersMonth = $this->paidQuery()
            ->whereYear('closed_at', $now->year)
            ->whereMonth('closed_at', $now->month)
            ->count();

        $avgTicketMonth = $ordersMonth > 0 ? round($revenueMonth / $ordersMonth, 2) : 0;

        // ── Miglior scontrino di sempre ──────────────────────────────────────
        $bestReceipt = $this->paidQuery()
            ->with(['restaurantTable', 'items.dish', 'waiter'])
            ->orderByDesc('total_amount')
            ->first();

        // ── Trend settimanale (ultime 8 settimane inclusa corrente) ──────────
        $weeklyRaw = $this->paidQuery()
            ->where('closed_at', '>=', $now->copy()->subWeeks(7)->startOfWeek())
            ->select(
                DB::raw('YEARWEEK(closed_at, 1) as year_week'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders_count')
            )
            ->groupBy('year_week')
            ->orderBy('year_week')
            ->get()
            ->keyBy(fn($r) => (int) $r->year_week);

        $weeklyTrend = [];
        for ($i = 7; $i >= 0; $i--) {
            $weekStart = $now->copy()->subWeeks($i)->startOfWeek();
            $key = (int) ($weekStart->format('o') . str_pad($weekStart->isoWeek(), 2, '0', STR_PAD_LEFT));
            $weeklyTrend[] = [
                'label'   => 'Sett.' . $weekStart->isoWeek() . ' (' . $weekStart->format('d/m') . ')',
                'revenue' => $weeklyRaw->has($key) ? round($weeklyRaw[$key]->revenue, 2) : 0,
                'orders'  => $weeklyRaw->has($key) ? (int) $weeklyRaw[$key]->orders_count : 0,
            ];
        }

        // ── Trend mensile (ultimi 12 mesi incluso corrente) ──────────────────
        $monthlyRaw = $this->paidQuery()
            ->where('closed_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->select(
                DB::raw('YEAR(closed_at) as year'),
                DB::raw('MONTH(closed_at) as month'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders_count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->keyBy(fn($r) => $r->year . '-' . $r->month);

        $monthlyTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i)->startOfMonth();
            $key   = $month->year . '-' . $month->month;
            $monthlyTrend[] = [
                'label'   => $monthNames[$month->month - 1] . ' ' . $month->year,
                'revenue' => $monthlyRaw->has($key) ? round($monthlyRaw[$key]->revenue, 2) : 0,
                'orders'  => $monthlyRaw->has($key) ? (int) $monthlyRaw[$key]->orders_count : 0,
            ];
        }

        // ── Classifica operatori (tutti i tempi) ─────────────────────────────
        $topOperators = $this->paidQuery()
            ->whereNotNull('waiter_id')
            ->select(
                'waiter_id',
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('AVG(total_amount) as avg_ticket')
            )
            ->with('waiter:id,name')
            ->groupBy('waiter_id')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // ── Top 10 piatti più venduti ─────────────────────────────────────────
        $topDishes = OrderItem::select(
                'dish_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(subtotal) as total_revenue')
            )
            ->whereHas('order', fn($q) => $q
                ->where('status', 'paid')
                ->where(fn($q2) => $q2->where('autoconsumo', false)->orWhereNull('autoconsumo'))
            )
            ->with('dish')
            ->groupBy('dish_id')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        // ── Magazzino ─────────────────────────────────────────────────────────
        $stockService  = app(StockService::class);
        $stocks        = $stockService->calculateAllStocks();
        $lowStockCount = $stocks->filter(fn($s) => $s['is_low'])->count();

        return compact(
            'revenueToday', 'revenueWeek', 'revenueMonth', 'revenueYear',
            'ordersToday', 'ordersWeek', 'ordersMonth', 'avgTicketMonth',
            'bestReceipt', 'weeklyTrend', 'monthlyTrend', 'topDishes',
            'topOperators', 'stocks', 'lowStockCount'
        );
    }

    public function clearCache(Request $request): JsonResponse
    {
        Cache::forget('dashboard_data');

        // Cancella la cache giornaliera per la data richiesta (default oggi)
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->toDateString()
            : Carbon::today()->toDateString();

        Cache::forget('dashboard_daily_' . $date);

        return response()->json(['success' => true]);
    }

    public function dailyStats(Request $request): JsonResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->toDateString()
            : Carbon::today()->toDateString();

        $data = Cache::remember('dashboard_daily_' . $date, 300, function () use ($date) {

            // ── Fatturato / media / ordini del giorno ────────────────────────
            $orders = $this->paidQuery()
                ->whereDate('closed_at', $date)
                ->get(['id', 'total_amount']);

            $totale = round($orders->sum('total_amount'), 2);
            $ordini = $orders->count();
            $media  = $ordini > 0 ? round($totale / $ordini, 2) : 0;

            // ── Articoli cancellati ──────────────────────────────────────────
            $cancellazioni = TableOrderLog::where('action', 'remove_item')
                ->whereDate('created_at', $date)
                ->with(['user:id,name', 'tableOrder.restaurantTable:id,table_number'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($log) => [
                    'time'     => $log->created_at->format('H:i'),
                    'operator' => $log->user?->name ?? '-',
                    'table'    => $log->tableOrder?->restaurantTable?->table_number ?? '-',
                    'dish'     => $log->data_before['dish_name'] ?? '-',
                    'qty'      => $log->data_before['quantity'] ?? '-',
                    'price'    => isset($log->data_before['unit_price'])
                        ? number_format($log->data_before['unit_price'], 2, ',', '.')
                        : null,
                ]);

            // ── Modifiche di prezzo ──────────────────────────────────────────
            $modifichePrezzo = TableOrderLog::where('action', 'update_item_price')
                ->whereDate('created_at', $date)
                ->with(['user:id,name', 'tableOrder.restaurantTable:id,table_number'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($log) => [
                    'time'          => $log->created_at->format('H:i'),
                    'operator'      => $log->user?->name ?? '-',
                    'table'         => $log->tableOrder?->restaurantTable?->table_number ?? '-',
                    'dish'          => $log->data_before['dish_name'] ?? $log->data_after['dish_name'] ?? '-',
                    'price_before'  => isset($log->data_before['unit_price'])
                        ? number_format($log->data_before['unit_price'], 2, ',', '.')
                        : '-',
                    'price_after'   => isset($log->data_after['unit_price'])
                        ? number_format($log->data_after['unit_price'], 2, ',', '.')
                        : '-',
                ]);

            // ── Modifiche operatore (quantità, item, coperti) ────────────────
            $modificheOperatore = TableOrderLog::whereIn('action', [
                    'update_item_quantity',
                    'update_item',
                    'update_covers',
                ])
                ->whereDate('created_at', $date)
                ->with(['user:id,name', 'tableOrder.restaurantTable:id,table_number'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($log) => [
                    'time'     => $log->created_at->format('H:i'),
                    'operator' => $log->user?->name ?? '-',
                    'table'    => $log->tableOrder?->restaurantTable?->table_number ?? '-',
                    'action'   => $log->getActionDescription(),
                    'notes'    => $log->notes ?? '-',
                ]);

            return [
                'date'               => $date,
                'totale'             => $totale,
                'media'              => $media,
                'ordini'             => $ordini,
                'cancellazioni'      => ['count' => $cancellazioni->count(), 'logs' => $cancellazioni->values()],
                'modifiche_prezzo'   => ['count' => $modifichePrezzo->count(), 'logs' => $modifichePrezzo->values()],
                'modifiche_operatore'=> ['count' => $modificheOperatore->count(), 'logs' => $modificheOperatore->values()],
            ];
        });

        return response()->json($data);
    }

    private function paidQuery()
    {
        return TableOrder::where('status', 'paid')
            ->where(fn($q) => $q->where('autoconsumo', false)->orWhereNull('autoconsumo'));
    }

    public function forget()
    {
        Session::forget('company_id');
        Session::put('company-to-be-select', false);
    }

    public function select_company(): View
    {
        return view('backoffice.pages.dashboard.select-company');
    }
}
