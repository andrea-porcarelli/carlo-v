<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Pagina principale giacenze
     */
    public function index(Request $request)
    {
        $stocks = $this->stockService->calculateAllStocks();
        $lowStockCount = $stocks->filter(fn($s) => $s['is_low'])->count();

        // Filtri opzionali
        if ($request->filled('filter') && $request->filter === 'low') {
            $stocks = $stocks->filter(fn($s) => $s['is_low']);
        }

        // Ricerca per nome materiale
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $stocks = $stocks->filter(fn($s) => str_contains(strtolower($s['material']->label), $search));
        }

        // Ordinamento
        $sortable = ['imported', 'consumed', 'current'];
        $sort = in_array($request->sort, $sortable) ? $request->sort : null;
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        if ($sort) {
            $stocks = $direction === 'asc'
                ? $stocks->sortBy(fn($s) => $s[$sort])
                : $stocks->sortByDesc(fn($s) => $s[$sort]);
        } else {
            // Default: prima le giacenze più critiche (più sotto soglia in percentuale).
            // Per confrontare uova (pz) e farina (kg) servono grandezze adimensionali → uso la % di deficit.
            $stocks = $stocks->sortByDesc(function ($s) {
                $threshold = $s['material']->alert_threshold;
                if ($threshold === null || $threshold <= 0) {
                    return -INF; // nessuna soglia → in fondo
                }
                // deficit% = (threshold - current) / threshold × 100
                // positivo e crescente = più sotto soglia = più critico
                return (($threshold - $s['current']) / $threshold) * 100;
            });
        }

        return view('backoffice.stock.index', compact('stocks', 'lowStockCount', 'sort', 'direction'));
    }

    /**
     * Aggiorna soglia alert per un materiale
     */
    public function updateThreshold(Request $request, Material $material)
    {
        $validated = $request->validate([
            'alert_threshold' => 'nullable|numeric|min:0'
        ]);

        $material->update($validated);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Soglia aggiornata']);
        }

        return back()->with('success', 'Soglia aggiornata');
    }
}
