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

        $lines = [];

        foreach ($lowStockMaterials as $stock) {
            $material = $stock['material'];

            $name = e($material->label);
            $lines[] = "📦 <b>{$name}</b>\n     📉 Giacenza: <b>{$stock['current']}</b> — 🔻 Soglia: <b>{$material->alert_threshold}</b>";
            Log::warning("Materiale sotto soglia: {$material->label} — giacenza: {$stock['current']}, soglia: {$material->alert_threshold}");
            $this->warn("Materiale sotto soglia: {$material->label} — giacenza: {$stock['current']}, soglia: {$material->alert_threshold}");
        }

        if (config('logging.channels.telegram.handler_with.apiKey')) {
            $count = count($lines);
            $telegramMessage = "🚨 <b>Scorte in esaurimento</b> — {$count} " . ($count === 1 ? 'materiale' : 'materiali') . "\n\n"
                . implode("\n\n", $lines);
            Log::channel('telegram')->warning($telegramMessage);
        }

        return self::SUCCESS;
    }
}
