<?php

namespace App\Services;

use App\Interfaces\ReceiptIssuerInterface;
use App\Models\PrecontoSplit;
use App\Models\TableOrder;
use App\Support\IssuedReceiptDto;

/**
 * Adapter che espone CorrispettivoService (path SOAP Mysond) attraverso
 * il contratto unificato ReceiptIssuerInterface, senza modificarlo.
 *
 * Wrappa il risultato (TableOrderCorrispettivo|null) nel DTO normalizzato.
 */
final class MysondReceiptIssuerAdapter implements ReceiptIssuerInterface
{
    public function __construct(
        private CorrispettivoService $corrispettivoService,
    ) {}

    public function providerName(): string
    {
        return 'mysond';
    }

    public function emettiPerOrdine(TableOrder $order, string $paymentMethod, ?int $operatorId): ?IssuedReceiptDto
    {
        $corrispettivo = $this->corrispettivoService->emettiPerOrdine($order, $paymentMethod, $operatorId);
        return $corrispettivo ? IssuedReceiptDto::fromMysond($corrispettivo->refresh()) : null;
    }

    public function emettiPerSplit(PrecontoSplit $split, string $paymentMethod, ?int $operatorId): ?IssuedReceiptDto
    {
        $corrispettivo = $this->corrispettivoService->emettiPerSplit($split, $paymentMethod, $operatorId);
        return $corrispettivo ? IssuedReceiptDto::fromMysond($corrispettivo->refresh()) : null;
    }
}
