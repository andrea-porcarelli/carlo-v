<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set(
            'ditron_agent_close_day_timeout_seconds',
            90,
            'integer',
            'Timeout HTTP (secondi) per la chiamata POST /close-day sull\'agent Ditron. Deve essere maggiore di CloseDayTimeoutMs dell\'agent (default 60s) più margine.'
        );
    }

    public function down(): void
    {
        Setting::where('key', 'ditron_agent_close_day_timeout_seconds')->delete();
    }
};
