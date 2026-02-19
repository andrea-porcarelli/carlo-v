<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key'         => 'pos_terminal_ip',
                'value'       => '',
                'type'        => 'string',
                'description' => 'IP Terminale POS (VEGA3000) — lascia vuoto per disabilitare',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'pos_terminal_port',
                'value'       => '10001',
                'type'        => 'integer',
                'description' => 'Porta TCP Terminale POS (default 10001)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insertOrIgnore($setting);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['pos_terminal_ip', 'pos_terminal_port'])->delete();
    }
};
