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
        Schema::create('supplier_invoice_products', function (Blueprint $table) {
            $table->id();
            $table->integer('supplier_invoice_id');
            $table->string('product_name');
            $table->integer('quantity');
            $table->integer('iva');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::dropIfExists('supplier_invoice_products');
    }
};
