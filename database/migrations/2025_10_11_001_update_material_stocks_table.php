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
            $table->integer('supplier_invoice_product_id')->after('material_id')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_stocks', function (Blueprint $table) {
            $table->dropColumn('supplier_invoice_product_id');
        });
    }
};
