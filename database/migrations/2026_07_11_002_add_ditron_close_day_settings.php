<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set(
            'ditron_close_day_tipo',
            2,
            'integer',
            'Tipo di azzeramento inviato con "azzgio tipo=N": 1=Lungo, 2=Breve, 3=Medio. Default 2 (come RistoQuick).'
        );
    }

    public function down(): void
    {
        Setting::where('key', 'ditron_close_day_tipo')->delete();
    }
};
