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
        });

        // Unique index separato con prefix length per la colonna TEXT
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE mapping_products ADD UNIQUE INDEX mapping_products_product_name_supplier_id_unique (product_name(191), supplier_id)'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE mapping_products DROP INDEX mapping_products_product_name_supplier_id_unique'
        );

        Schema::table('mapping_products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
