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

        return view('backoffice.stock.index', compact('stocks', 'lowStockCount'));
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
