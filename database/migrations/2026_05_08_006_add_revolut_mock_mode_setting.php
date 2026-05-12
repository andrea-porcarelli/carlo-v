<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'         => 'revolut.mock_mode',
            'value'       => '0',
            'type'        => 'boolean',
            'description' => 'Modalità mock Revolut (solo sandbox): finge le chiamate API e abilita il bottone "Simula pagamento OK" nell\'overlay del cassiere',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'revolut.mock_mode')->delete();
    }
};
