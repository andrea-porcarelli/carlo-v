<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set(
            'corrispettivo_enabled',
            true,
            'boolean',
            'Attiva emissione corrispettivo elettronico al pagamento (esclude i metodi fattura).'
        );

        Setting::set(
            'corrispettivo_aliquota_iva_default',
            22.00,
            'decimal',
            'Aliquota IVA di default utilizzata per le righe dello scontrino quando non presente sul prodotto.'
        );

        Setting::set(
            'corrispettivo_mock',
            true,
            'boolean',
            'Se attivo, non effettua la chiamata SOAP a Mysond e restituisce un progressivoSdi finto (sviluppo).'
        );

        Setting::set(
            'corrispettivo_timeout_seconds',
            10,
            'integer',
            'Timeout (secondi) per il tentativo sincrono di invio corrispettivo in fase di incasso.'
        );

        Setting::set(
            'corrispettivo_max_attempts',
            3,
            'integer',
            'Numero massimo di tentativi (sincrono + retry asincroni) prima di marcare il corrispettivo come failed.'
        );

        Setting::set(
            'corrispettivo_printer_id',
            null,
            'integer',
            'ID della stampante usata per lo scontrino. Se vuoto, usa la stampante preconto.'
        );
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'corrispettivo_enabled',
            'corrispettivo_aliquota_iva_default',
            'corrispettivo_mock',
            'corrispettivo_timeout_seconds',
            'corrispettivo_max_attempts',
            'corrispettivo_printer_id',
        ])->delete();
    }
};
