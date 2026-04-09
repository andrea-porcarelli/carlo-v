<?php

namespace App\Observers;

use App\Models\ExternalInvoice;
use App\Models\MappingProduct;

class ExternalInvoiceObserver
{
    /**
     * Dopo il commit: le lines sono già persistite in DB.
     */
    public bool $afterCommit = true;

    public function created(ExternalInvoice $invoice): void
    {
        $this->computeStatus($invoice);
    }

    public function updated(ExternalInvoice $invoice): void
    {
        // Non ricalcolare lo status se è già confermato o in errore
        if (in_array($invoice->status, ['import_error', 'stock_confirmed'])) {
            return;
        }
        $this->computeStatus($invoice);
    }

    /**
     * Calcola e imposta lo status della fattura in base allo stato del mapping delle righe.
     *
     * - import_error       → impostato dal command, non toccare
     * - pending_mapping    → almeno una riga senza MappingProduct e non ignorata
     * - pending_confirmation → tutte le righe sono mappate o ignorate, stock non ancora confermato
     * - stock_confirmed    → impostato dall'utente nella pagina di conferma
     */
    private function computeStatus(ExternalInvoice $invoice): void
    {
        if ($invoice->status === 'import_error') {
            return;
        }

        $lines = $invoice->lines;

        if ($lines->isEmpty()) {
            return;
        }

        // Raccoglie tutti i product_name mappati per confronto in memoria
        $descriptions = $lines->where('ignore_mapping', false)->pluck('description')->unique();
        $mappedNames  = MappingProduct::whereIn('product_name', $descriptions)
            ->whereNull('supplier_id')
            ->pluck('product_name')
            ->flip();

        $hasPendingMapping = $lines
            ->where('ignore_mapping', false)
            ->contains(fn($line) => !isset($mappedNames[$line->description]));

        $newStatus = $hasPendingMapping ? 'pending_mapping' : 'pending_confirmation';

        // Aggiorna senza triggherare nuovamente l'observer
        ExternalInvoice::withoutEvents(function () use ($invoice, $newStatus) {
            $invoice->update(['status' => $newStatus]);
        });
    }
}
