<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Allow standalone invoices (not tied to a table order)
        //    The original FK was created with cascadeOnDelete: drop it before changing nullability.
        Schema::table('table_order_invoices', function (Blueprint $table) {
            $table->dropForeign(['table_order_id']);
        });

        Schema::table('table_order_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('table_order_id')->nullable()->change();
            $table->foreign('table_order_id')
                ->references('id')->on('table_orders')
                ->nullOnDelete();
        });

        // 2) Persist invoice lines for multi-line invoices created from backoffice
        Schema::table('table_order_invoices', function (Blueprint $table) {
            $table->json('lines')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('table_order_invoices', function (Blueprint $table) {
            $table->dropColumn('lines');
        });

        // Restore original FK with cascade
        Schema::table('table_order_invoices', function (Blueprint $table) {
            $table->dropForeign(['table_order_id']);
        });

        // Cannot revert nullability if any standalone invoice exists; only flip the FK back.
        Schema::table('table_order_invoices', function (Blueprint $table) {
            $table->foreign('table_order_id')
                ->references('id')->on('table_orders')
                ->cascadeOnDelete();
        });
    }
};
