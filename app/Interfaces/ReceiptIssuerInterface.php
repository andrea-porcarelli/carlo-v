<?php

namespace App\Interfaces;

use App\Models\PrecontoSplit;
use App\Models\TableOrder;
use App\Support\IssuedReceiptDto;

/**
 * Contratto unificato per gli emettitori di scontrini (corrispettivi).
 *
 * Implementato da:
 *  - MysondReceiptIssuerAdapter (delegate a CorrispettivoService → SOAP/SdI)
 *  - DitronReceiptService (delegate ad agent locale → cassa Ditron RT)
 *
 * La scelta dell'implementazione è dinamica via setting `corrispettivo_provider`,
 * risolta nel container in RepositoryServiceProvider. Default: mysond.
 */
interface ReceiptIssuerInterface
{
    /**
     * Emette uno scontrino per un TableOrder intero.
     * Ritorna null se l'emissione è stata saltata (provider disabilitato o metodo escluso).
     */
    public function emettiPerOrdine(TableOrder $order, string $paymentMethod, ?int $operatorId): ?IssuedReceiptDto;

    /**
     * Emette uno scontrino per un singolo preconto/split.
     * Ritorna null se l'emissione è stata saltata.
     */
    public function emettiPerSplit(PrecontoSplit $split, string $paymentMethod, ?int $operatorId): ?IssuedReceiptDto;

    /**
     * Nome del provider concreto, per logging/UI ("mysond" o "ditron").
     */
    public function providerName(): string;
}
