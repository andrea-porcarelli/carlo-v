<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'         => 'coperto_dish_id',
            'value'       => '',
            'type'        => 'integer',
            'description' => 'Prodotto coperto (piatto usato per aggiungere il coperto agli ordini)',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'coperto_dish_id')->delete();
    }
};
