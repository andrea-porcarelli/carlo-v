<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\RestaurantTable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            $table->string('cash_drawer_operation_id')->nullable()->after('waiter_id');
        });
    }

    public function down(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            $table->dropColumn(['cash_drawer_operation_id']);
        });
    }
};
