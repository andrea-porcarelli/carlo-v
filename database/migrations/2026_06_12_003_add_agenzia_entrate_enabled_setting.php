<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set(
            'agenzia_entrate.enabled',
            true,
            'boolean',
            'Collegamento Agenzia delle Entrate attivo (credenziali, verifica e cambio password). Disattivare per istanze che non emettono via SdI/Mysond.'
        );
    }

    public function down(): void
    {
        Setting::where('key', 'agenzia_entrate.enabled')->delete();
    }
};
