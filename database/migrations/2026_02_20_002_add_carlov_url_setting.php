<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'         => 'carlov_url',
            'value'       => 'https://carlovristorante.share.zrok.io',
            'type'        => 'string',
            'description' => 'URL endpoint Carlov (zrok) — usato dal server web per sincronizzare con il POS',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'carlov_url')->delete();
    }
};
