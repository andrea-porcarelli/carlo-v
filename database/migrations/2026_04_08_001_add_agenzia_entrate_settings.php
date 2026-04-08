<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key'         => 'agenzia_entrate.username',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Agenzia delle Entrate — username',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'agenzia_entrate.password',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Agenzia delle Entrate — password',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'agenzia_entrate.pincode',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Agenzia delle Entrate — pincode',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'agenzia_entrate.utenza',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Agenzia delle Entrate — utenza',
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
        DB::table('settings')->whereIn('key', [
            'agenzia_entrate.username',
            'agenzia_entrate.password',
            'agenzia_entrate.pincode',
            'agenzia_entrate.utenza',
        ])->delete();
    }
};
