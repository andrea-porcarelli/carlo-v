<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_stocks', function (Blueprint $table) {
            $table->dropColumn('external_invoice_line_id');
            $table->foreignId('supplier_invoice_product_id')
                ->nullable()
                ->after('material_id')
                ->constrained('supplier_invoice_products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_invoice_product_id');
            $table->integer('external_invoice_line_id')->nullable()->after('material_id');
        });
    }
};
