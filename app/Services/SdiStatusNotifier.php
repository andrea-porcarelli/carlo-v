<?php

namespace App\Services;

use App\Models\TableOrderInvoice;
use Illuminate\Support\Facades\Log;

class SdiStatusNotifier
{
    /**
     * Invia una notifica Telegram al cambio di stato SDI di una fattura.
     * Il canale `telegram` è configurato in config/logging.php usando
     * TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID dall'env.
     */
    public static function notifyStatusChange(
        TableOrderInvoice $invoice,
        ?int $previousStatus,
        int $newStatus,
        ?string $newLabel,
        ?string $descrizione
    ): void {
        $previousLabel = TableOrderInvoice::sdiStatusLabel($previousStatus) ?? 'sconosciuto';
        $code          = $invoice->invoice_code ?? ('#' . $invoice->id);
        $cliente       = $invoice->customer->full_name ?? '—';

        $emoji = match (true) {
            in_array($newStatus, [7, 9])      => '✅',
            in_array($newStatus, [1, 6, 10])  => '❌',
            in_array($newStatus, [8, 11, 12]) => '⚠️',
            default                           => 'ℹ️',
        };

        $msg  = $emoji . " *Fattura {$code}* — cambio stato SDI\n";
        $msg .= "Cliente: {$cliente}\n";
        $msg .= "Da: _{$previousLabel}_ → *" . ($newLabel ?? "Stato {$newStatus}") . "*";
        if ($descrizione) {
            $msg .= "\n" . $descrizione;
        }

        try {
            Log::channel('telegram')->info($msg);
        } catch (\Throwable $e) {
            Log::warning('Telegram notify SDI status failed', ['error' => $e->getMessage()]);
        }
    }
}
