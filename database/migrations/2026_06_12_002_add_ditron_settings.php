<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set(
            'corrispettivo_provider',
            'mysond',
            'string',
            'Provider per emissione scontrini: "mysond" (default, via SOAP/SdI) oppure "ditron" (via agent + cassa RT locale).'
        );

        Setting::set(
            'ditron_agent_url',
            'http://192.168.1.149:9090',
            'string',
            'Base URL dell\'agent DitronAgent in LAN (es. http://192.168.1.149:9090). Vuoto disabilita le chiamate.'
        );

        Setting::set(
            'ditron_agent_token',
            '',
            'string',
            'Bearer token opzionale per autenticare le chiamate a DitronAgent. Deve coincidere con AuthToken in appsettings dell\'agent.'
        );

        Setting::set(
            'ditron_agent_timeout_seconds',
            20,
            'integer',
            'Timeout (secondi) per la chiamata HTTP all\'agent (deve essere maggiore di ErrPollingTimeoutMs dell\'agent).'
        );

        Setting::set(
            'ditron_default_reparto',
            1,
            'integer',
            'Reparto cassa Ditron usato per default per ogni riga (rep=N) quando non specificato sul piatto.'
        );

        Setting::set(
            'ditron_default_tender',
            5,
            'integer',
            'Tender (T=N) usato nella chiusura scontrino fiscale. T=5 replica il setup attuale di RistoQuick.'
        );

        Setting::set(
            'ditron_cover_label',
            'COPERTO',
            'string',
            'Descrizione usata per la riga del coperto sullo scontrino Ditron.'
        );
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'corrispettivo_provider',
            'ditron_agent_url',
            'ditron_agent_token',
            'ditron_agent_timeout_seconds',
            'ditron_default_reparto',
            'ditron_default_tender',
            'ditron_cover_label',
        ])->delete();
    }
};
