<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('revolut_operator_id')
                ->nullable()
                ->after('revolut_payment_started_at');
            $table->foreign('revolut_operator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            $table->dropForeign(['revolut_operator_id']);
            $table->dropColumn('revolut_operator_id');
        });
    }
};
