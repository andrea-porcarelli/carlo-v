<?php

namespace App\Exceptions;

use App\Models\MirroredInvoice;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Lanciata dal MysondInvoiceMirror quando ci sono scartate SDI non
 * riconosciute sull'Azienda MySond condivisa con altri progetti. I
 * chiamanti devono catturarla, mostrare l'elenco all'operatore e rifiutare
 * l'emissione finché l'ack non viene fatto da /backoffice/accounting.
 */
class PendingSdiRejectionsException extends RuntimeException
{
    /**
     * @param Collection<int, MirroredInvoice> $rejections
     */
    public function __construct(public readonly Collection $rejections)
    {
        parent::__construct(sprintf(
            'Emissione bloccata: %d scartata/e SDI non risolta/e su MySond.',
            $rejections->count()
        ));
    }
}
