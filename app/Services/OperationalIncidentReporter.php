<?php

namespace App\Services;

use App\Exceptions\OperationalException;
use App\Models\OperationalIncident;
use App\Models\TableOrder;
use App\Support\OperationalErrorCode;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Unico entry point per registrare un incidente operativo.
 *
 * Persiste il record su `operational_incidents`, invia (best-effort) una
 * notifica Telegram se il codice lo prevede, e restituisce l'istanza salvata
 * al chiamante — che di solito la include nella risposta HTTP verso l'operatore
 * frontoffice (chiave `incident_id`) per correlazione con il feed di notifiche.
 */
class OperationalIncidentReporter
{
    public function report(
        OperationalErrorCode $code,
        array $context = [],
        ?TableOrder $tableOrder = null,
        ?int $userId = null,
        ?string $source = null,
        ?string $technicalDetail = null,
    ): OperationalIncident {
        $incident = OperationalIncident::create([
            'code'             => $code->value,
            'severity'         => $code->severity(),
            'category'         => $code->category(),
            'operator_message' => $code->operatorMessage($context),
            'technical_detail' => $technicalDetail,
            'context'          => $context ?: null,
            'table_order_id'   => $tableOrder?->id,
            'user_id'          => $userId,
            'source'           => $source,
        ]);

        if ($code->notifyTelegram()) {
            $this->notifyTelegram($incident);
        }

        Log::info('operational_incident', [
            'incident_id' => $incident->id,
            'code'        => $incident->code,
            'severity'    => $incident->severity,
            'source'      => $source,
        ]);

        return $incident;
    }

    public function reportException(
        OperationalException $e,
        ?TableOrder $tableOrder = null,
        ?int $userId = null,
        ?string $source = null,
    ): OperationalIncident {
        return $this->report(
            code:            $e->errorCode,
            context:         $e->context,
            tableOrder:      $tableOrder,
            userId:          $userId,
            source:          $source,
            technicalDetail: $e->getPrevious()?->getMessage() ?? $e->getMessage(),
        );
    }

    private function notifyTelegram(OperationalIncident $incident): void
    {
        $code = $incident->errorCode();
        $emoji = $code?->telegramEmoji() ?? 'ℹ️';

        $tableLine = null;
        if ($incident->table_order_id) {
            $table = $incident->tableOrder?->restaurantTable;
            $tableRef = $table?->is_banco
                ? 'Banco'
                : ($table?->table_number ? "Tavolo {$table->table_number}" : "Ordine #{$incident->table_order_id}");
            $tableLine = $tableRef;
        }

        $lines = array_filter([
            "{$emoji} <b>{$incident->code}</b>",
            $incident->operator_message,
            $tableLine,
            $incident->technical_detail ? "Dettaglio tecnico: {$incident->technical_detail}" : null,
            $incident->source ? "Origine: {$incident->source}" : null,
        ]);

        try {
            $level = match ($incident->severity) {
                OperationalIncident::SEVERITY_CRITICAL => 'critical',
                OperationalIncident::SEVERITY_ERROR    => 'error',
                OperationalIncident::SEVERITY_WARN     => 'warning',
                default                                => 'info',
            };
            Log::channel('telegram')->{$level}(implode("\n", $lines));
            $incident->forceFill(['telegram_notified_at' => now()])->save();
        } catch (Throwable $e) {
            Log::warning('Telegram notify operational incident failed', [
                'incident_id' => $incident->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
