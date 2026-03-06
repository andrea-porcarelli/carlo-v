<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuOptionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('menu_options')->insert([
            // Extra (con prezzo)
            ['type' => 'extra', 'label' => 'Parmigiano extra',     'price' => 1.00, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'extra', 'label' => 'Burro extra',          'price' => 0.50, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'extra', 'label' => 'Aggiunta verdure',     'price' => 1.50, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'extra', 'label' => 'Salsa tartara',        'price' => 0.50, 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'extra', 'label' => 'Porzione doppia',      'price' => 3.00, 'is_active' => true, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],

            // Rimozioni (senza prezzo)
            ['type' => 'removal', 'label' => 'Senza aglio',        'price' => null, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'removal', 'label' => 'Senza cipolla',      'price' => null, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'removal', 'label' => 'Senza glutine',      'price' => null, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'removal', 'label' => 'Senza lattosio',     'price' => null, 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'removal', 'label' => 'Senza peperoncino',  'price' => null, 'is_active' => true, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
