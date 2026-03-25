<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'         => 'cash_drawer_printer_id',
            'value'       => null,
            'type'        => 'integer',
            'description' => 'ID della stampante/cassa automatica VNE Automatic Cash',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'cash_drawer_printer_id')->delete();
    }
};
