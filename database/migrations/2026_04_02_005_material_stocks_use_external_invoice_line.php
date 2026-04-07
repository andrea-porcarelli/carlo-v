<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_stocks', function (Blueprint $table) {
            // Sostituisce supplier_invoice_product_id con external_invoice_line_id
            $table->dropColumn('supplier_invoice_product_id');
            $table->foreignId('external_invoice_line_id')
                ->nullable()
                ->after('material_id')
                ->constrained('external_invoice_lines')
                ->nullOnDelete();

            // Stock decimale per supportare quantità frazionarie (kg, cl, ecc.)
            $table->decimal('stock', 15, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('material_stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('external_invoice_line_id');
            $table->integer('supplier_invoice_product_id')->nullable()->after('material_id');
            $table->integer('stock')->change();
        });
    }
};
