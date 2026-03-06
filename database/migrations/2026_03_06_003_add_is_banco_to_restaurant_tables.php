<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\RestaurantTable;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->boolean('is_banco')->default(false)->after('is_active');
        });

        // Crea il tavolo banco se non esiste già
        if (!RestaurantTable::where('is_banco', true)->exists()) {
            RestaurantTable::create([
                'table_number' => 0,
                'capacity' => 0,
                'status' => 'free',
                'is_active' => true,
                'is_banco' => true,
            ]);
        }
    }

    public function down(): void
    {
        RestaurantTable::where('is_banco', true)->delete();

        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropColumn('is_banco');
        });
    }
};
