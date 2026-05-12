<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existingPrinterId = DB::table('settings')->where('key', 'cash_drawer_printer_id')->value('value');
        $defaultIntegration = ($existingPrinterId && $existingPrinterId !== '0') ? 'printer' : 'none';

        DB::table('settings')->insertOrIgnore([
            'key'         => 'cash_drawer_integration',
            'value'       => $defaultIntegration,
            'type'        => 'string',
            'description' => 'Integrazione cassa automatica (none = disabilitata, printer = stampante con comando ESC/POS)',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'cash_drawer_integration')->delete();
    }
};
