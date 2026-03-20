<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\RestaurantTable;

return new class extends Migration
{
    public function up(): void
    {
        RestaurantTable::where('is_banco', true)->update(['table_number' => 999]);
    }

    public function down(): void
    {
        RestaurantTable::where('is_banco', true)->update(['table_number' => 1]);
    }
};
