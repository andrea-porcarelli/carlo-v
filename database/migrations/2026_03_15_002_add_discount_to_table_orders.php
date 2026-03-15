<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            $table->string('discount_type')->nullable()->after('total_amount');    // 'percent' | 'value'
            $table->decimal('discount_amount', 10, 2)->nullable()->after('discount_type'); // raw input (es. 10 per 10% o €10)
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_amount'); // importo € effettivo scontato
        });
    }

    public function down(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_amount', 'discount_value']);
        });
    }
};
