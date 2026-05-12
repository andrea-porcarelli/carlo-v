<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE table_orders MODIFY status ENUM('open', 'pending_payment', 'paid', 'cancelled') NOT NULL DEFAULT 'open'");

        Schema::table('table_orders', function (Blueprint $table) {
            $table->string('revolut_order_id', 64)->nullable()->after('payment_method');
            $table->string('revolut_payment_state', 40)->nullable()->after('revolut_order_id');
            $table->timestamp('revolut_payment_started_at')->nullable()->after('revolut_payment_state');
            $table->index('revolut_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            $table->dropIndex(['revolut_order_id']);
            $table->dropColumn(['revolut_order_id', 'revolut_payment_state', 'revolut_payment_started_at']);
        });

        DB::table('table_orders')->where('status', 'pending_payment')->update(['status' => 'open']);
        DB::statement("ALTER TABLE table_orders MODIFY status ENUM('open', 'paid', 'cancelled') NOT NULL DEFAULT 'open'");
    }
};
