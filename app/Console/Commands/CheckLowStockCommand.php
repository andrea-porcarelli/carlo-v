<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLowStockCommand extends Command
{
    protected $signature = 'stock:check-low';

    protected $description = 'Controlla i materiali con giacenza sotto soglia';

    public function handle(StockService $stockService): int
    {
        $lowStockMaterials = $stockService->getLowStockMaterials();

        if ($lowStockMaterials->isEmpty()) {
            $message = 'Nessun materiale sotto soglia.';
            Log::info($message);
            $this->info($message);

            return self::SUCCESS;
        }

        foreach ($lowStockMaterials as $stock) {
            $material = $stock['material'];
            Log::warning("Materiale sotto soglia: {$material->label} — giacenza: {$stock['current']}, soglia: {$material->alert_threshold}");
            $this->warn("Materiale sotto soglia: {$material->label} — giacenza: {$stock['current']}, soglia: {$material->alert_threshold}");
        }

        if (config('logging.channels.telegram.handler_with.apiKey')) {
            $count = $lowStockMaterials->count();

            // I "più critici" sono quelli col rapporto giacenza/soglia più basso
            // (soglia > 0 sempre, per definizione di isLowStock quando threshold != null).
            $topCritical = $lowStockMaterials
                ->sortBy(fn($stock) => $stock['material']->alert_threshold > 0
                    ? $stock['current'] / $stock['material']->alert_threshold
                    : PHP_INT_MAX)
                ->take(5);

            $lines = [];
            foreach ($topCritical as $stock) {
                $material = $stock['material'];
                $name    = e($material->label);
                $unit    = e($material->stock_type ?? '');
                $current = rtrim(rtrim(number_format((float) $stock['current'], 2, '.', ''), '0'), '.');
                $thr     = rtrim(rtrim(number_format((float) $material->alert_threshold, 2, '.', ''), '0'), '.');
                $lines[] = "• <b>{$name}</b> — {$current} / {$thr} {$unit}";
            }

            $header  = "🚨 <b>Scorte in esaurimento</b> — {$count} " . ($count === 1 ? 'materiale' : 'materiali');
            $preface = $count > 5 ? "\n\n<b>I 5 più critici:</b>\n" : "\n\n";
            $body    = $preface . implode("\n", $lines);
            $link    = "\n\n👉 Dettagli: " . route('restaurant.stock.index');

            Log::channel('telegram')->warning($header . $body . $link);
        }

        return self::SUCCESS;
    }
}
