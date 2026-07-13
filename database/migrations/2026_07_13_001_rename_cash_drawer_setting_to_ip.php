<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $old = DB::table('settings')->where('key', 'cash_drawer_printer_id')->first();

        $newValue = '';
        if ($old && $old->value !== null && $old->value !== '') {
            // Legacy: field held a printer ID → resolve to the printer's IP.
            // If it's already an IP-like string, keep it as-is.
            if (ctype_digit((string) $old->value)) {
                $newValue = (string) DB::table('printers')->where('id', (int) $old->value)->value('ip');
            } else {
                $newValue = (string) $old->value;
            }
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'cash_drawer_ip'],
            [
                'value'       => $newValue,
                'type'        => 'string',
                'description' => 'Indirizzo IP della cassa automatica VNE Automatic Cash (es. 192.168.1.150)',
                'updated_at'  => now(),
                'created_at'  => now(),
            ]
        );

        DB::table('settings')->where('key', 'cash_drawer_printer_id')->delete();
    }

    public function down(): void
    {
        $current = (string) DB::table('settings')->where('key', 'cash_drawer_ip')->value('value');

        DB::table('settings')->updateOrInsert(
            ['key' => 'cash_drawer_printer_id'],
            [
                'value'       => $current,
                'type'        => 'string',
                'description' => 'ID della stampante/cassa automatica VNE Automatic Cash',
                'updated_at'  => now(),
                'created_at'  => now(),
            ]
        );

        DB::table('settings')->where('key', 'cash_drawer_ip')->delete();
    }
};
