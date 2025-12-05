<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestaurantTablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tables = [];

        // Create 20 tables with default positions
        for ($i = 1; $i <= 20; $i++) {
            $tables[] = [
                'table_number' => $i,
                'capacity' => 4,
                'position_x' => null,
                'position_y' => null,
                'status' => 'free',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('restaurant_tables')->insert($tables);
    }
}
