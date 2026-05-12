<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'         => 'restaurant_name',
            'value'       => 'Carlo V',
            'type'        => 'string',
            'description' => 'Nome del ristorante mostrato nelle interfacce (login, sidebar, navbar, titoli pagina)',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'restaurant_name')->delete();
    }
};
