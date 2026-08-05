<?php

namespace App\Helpers;

class ItalianFiscalHelper
{
    /**
     * Sigle ufficiali delle 107 province italiane (ISTAT).
     * Usate per validare il campo "Provincia" nelle anagrafiche destinate
     * all'emissione di Fattura Elettronica: SDI richiede 2 lettere maiuscole
     * corrispondenti a una provincia esistente (oppure "EE" per l'estero).
     */
    public const PROVINCES = [
        'AG', 'AL', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AT', 'AV',
        'BA', 'BG', 'BI', 'BL', 'BN', 'BO', 'BR', 'BS', 'BT', 'BZ',
        'CA', 'CB', 'CE', 'CH', 'CL', 'CN', 'CO', 'CR', 'CS', 'CT', 'CZ',
        'EN',
        'FC', 'FE', 'FG', 'FI', 'FM', 'FR',
        'GE', 'GO', 'GR',
        'IM', 'IS',
        'KR',
        'LC', 'LE', 'LI', 'LO', 'LT', 'LU',
        'MB', 'MC', 'ME', 'MI', 'MN', 'MO', 'MS', 'MT',
        'NA', 'NO', 'NU',
        'OR',
        'PA', 'PC', 'PD', 'PE', 'PG', 'PI', 'PN', 'PO', 'PR', 'PT', 'PU', 'PV', 'PZ',
        'RA', 'RC', 'RE', 'RG', 'RI', 'RM', 'RN', 'RO',
        'SA', 'SI', 'SO', 'SP', 'SR', 'SS', 'SU', 'SV',
        'TA', 'TE', 'TN', 'TO', 'TP', 'TR', 'TS', 'TV',
        'UD',
        'VA', 'VB', 'VC', 'VE', 'VI', 'VR', 'VT', 'VV',
        // Estero (per soggetti non residenti) — Sede della FatturaPA con Nazione != IT.
        'EE',
    ];

    public static function isValidProvince(string $code): bool
    {
        return in_array(strtoupper(trim($code)), self::PROVINCES, true);
    }

    /**
     * Valida un codice fiscale italiano per persona fisica: 16 caratteri
     * alfanumerici nel formato ufficiale (6 lettere + 2 cifre + 1 lettera
     * + 2 cifre + 1 lettera + 3 alfanumerici + 1 lettera).
     */
    public static function isValidPersonalFiscalCode(string $code): bool
    {
        $code = strtoupper(trim($code));
        return (bool) preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9A-Z]{3}[A-Z]$/', $code);
    }

    /**
     * Valida una partita IVA italiana: 11 cifre con checksum Luhn-modificato.
     */
    public static function isValidVatNumber(string $vat): bool
    {
        $vat = VatHelper::sanitize($vat);
        if (!preg_match('/^[0-9]{11}$/', $vat)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 11; $i++) {
            $n = (int) $vat[$i];
            if ($i % 2 === 1) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
        }
        return $sum % 10 === 0;
    }

    /**
     * Un codice fiscale di persona giuridica coincide con la P.IVA (11 cifre).
     */
    public static function isValidLegalFiscalCode(string $code): bool
    {
        return self::isValidVatNumber($code);
    }

    public static function isValidCap(string $cap): bool
    {
        return (bool) preg_match('/^[0-9]{5}$/', trim($cap));
    }
}
