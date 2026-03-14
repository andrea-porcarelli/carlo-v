<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preconto_splits', function (Blueprint $table) {
            $table->enum('discount_type', ['none', 'value', 'percent'])->default('none')->after('total');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_type');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_amount'); // actual € discounted
        });
    }

    public function down(): void
    {
        Schema::table('preconto_splits', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_amount', 'discount_value']);
        });
    }
};
