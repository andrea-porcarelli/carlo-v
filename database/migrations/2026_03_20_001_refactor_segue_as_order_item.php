<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Add sort_order to control item position within an order
            $table->integer('sort_order')->default(0)->after('table_order_id');
            // Make dish_id nullable to allow segue-only items (separators)
            $table->foreignId('dish_id')->nullable()->change();
        });

        // Initialize sort_order equal to id to preserve existing order
        DB::statement('UPDATE order_items SET sort_order = id');
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('sort_order');
            $table->foreignId('dish_id')->nullable(false)->change();
        });
    }
};
