<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = [
            [
                'key'         => 'pos_integration',
                'value'       => 'none',
                'type'        => 'string',
                'description' => 'Integrazione POS Elettronico (none = disabilitato, revolut = Revolut Terminal)',
            ],
            [
                'key'         => 'revolut.environment',
                'value'       => 'sandbox',
                'type'        => 'string',
                'description' => 'Ambiente Revolut (sandbox / production)',
            ],
            [
                'key'         => 'revolut.api_key',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Revolut Merchant API — Secret Key',
            ],
            [
                'key'         => 'revolut.location_id',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Revolut Location ID (necessario per associare ordini ai terminali)',
            ],
            [
                'key'         => 'revolut.webhook_secret',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Revolut Webhook Signing Secret (validazione webhook in entrata)',
            ],
            [
                'key'         => 'revolut.timeout_seconds',
                'value'       => '90',
                'type'        => 'integer',
                'description' => 'Timeout pagamento Revolut (secondi) — oltre questo intervallo il sistema interroga Revolut per scoprire lo stato',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insertOrIgnore(array_merge($setting, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'pos_integration',
            'revolut.environment',
            'revolut.api_key',
            'revolut.location_id',
            'revolut.webhook_secret',
            'revolut.timeout_seconds',
        ])->delete();
    }
};
