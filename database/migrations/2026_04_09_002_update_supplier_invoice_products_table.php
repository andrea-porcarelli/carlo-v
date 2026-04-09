<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoice_products', function (Blueprint $table) {
            $table->string('quantity_unit')->after('quantity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoice_products', function (Blueprint $table) {
            $table->dropColumn('quantity_unit');
        });
    }
};
