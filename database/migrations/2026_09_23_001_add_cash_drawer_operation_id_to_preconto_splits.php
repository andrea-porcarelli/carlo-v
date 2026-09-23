<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KEEP_UNPAID_ORDER_ID = 2156;

    public function up(): void
    {
        Schema::table('preconto_splits', function (Blueprint $table) {
            $table->string('cash_drawer_operation_id')->nullable()->after('paid_at');
        });

        DB::table('preconto_splits')
            ->where('status', 'paid')
            ->where('payment_method', 'contanti')
            ->where('table_order_id', '!=', self::KEEP_UNPAID_ORDER_ID)
            ->whereNull('cash_drawer_operation_id')
            ->update(['cash_drawer_operation_id' => 'legacy']);

        DB::table('table_orders')
            ->where('status', 'paid')
            ->where('payment_method', 'contanti')
            ->where('id', '!=', self::KEEP_UNPAID_ORDER_ID)
            ->whereNull('cash_drawer_operation_id')
            ->update(['cash_drawer_operation_id' => 'legacy']);
    }

    public function down(): void
    {
        Schema::table('preconto_splits', function (Blueprint $table) {
            $table->dropColumn('cash_drawer_operation_id');
        });
    }
};
