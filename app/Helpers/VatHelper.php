<?php

namespace App\Helpers;

class VatHelper
{
    /**
     * Normalizza una P.IVA italiana per l'uso in IdFiscaleIVA / filename FatturaPA.
     * Rimuove spazi, eventuale prefisso "IT", e qualunque carattere non numerico.
     * Restituisce solo le cifre. Non valida la lunghezza qui — il chiamante decide.
     */
    public static function sanitize(?string $vat): string
    {
        $vat = trim((string) $vat);
        if (stripos($vat, 'IT') === 0) {
            $vat = substr($vat, 2);
        }
        return preg_replace('/\D+/', '', $vat) ?? '';
    }
}
