<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('material_stocks', function (Blueprint $table) {
            $table->date('purchase_date')->nullable()->after('stock');
            $table->decimal('purchase_price', 10, 2)->nullable()->after('purchase_date');
            $table->text('notes')->nullable()->after('purchase_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_stocks', function (Blueprint $table) {
            $table->dropColumn(['purchase_date', 'purchase_price', 'notes']);
        });
    }
};
