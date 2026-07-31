<?php

namespace App\Exceptions;

use App\Support\OperationalErrorCode;
use RuntimeException;
use Throwable;

/**
 * Eccezione che veicola un codice errore operativo dall'infrastruttura
 * (PrinterService, DitronReceiptService, VNE cashdrawer, job di stampa)
 * fino al livello che ne fa reporting all'operatore.
 *
 * Il contesto è dati strutturati che finiscono nel record `operational_incidents`
 * e come placeholder nel messaggio operatore (vedi OperationalErrorCode::operatorMessage).
 */
class OperationalException extends RuntimeException
{
    public function __construct(
        public readonly OperationalErrorCode $errorCode,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        $technical = $previous?->getMessage() ?? $errorCode->value;
        parent::__construct($technical, 0, $previous);
    }

    public function operatorMessage(): string
    {
        return $this->errorCode->operatorMessage($this->context);
    }

    public function code(): string
    {
        return $this->errorCode->value;
    }

    public function severity(): string
    {
        return $this->errorCode->severity();
    }
}
