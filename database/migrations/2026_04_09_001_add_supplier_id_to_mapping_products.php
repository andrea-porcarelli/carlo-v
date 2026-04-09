<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapping_products', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->after('material_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->unique(['product_name', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::table('mapping_products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropUnique(['product_name', 'supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
