<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignId('supplier_invoice_product_id')->nullable()->constrained('supplier_invoice_products')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();

            // 'auto_mapped'     = mappatura trovata e stock aggiornato automaticamente
            // 'partial_mapping' = materiale noto ma multiplier non impostato, stock NON aggiornato
            // 'to_map'          = nessuna mappatura nota, richiede intervento manuale
            // 'ignored'         = prodotto ignorato dalla mappatura
            $table->enum('status', ['auto_mapped', 'partial_mapping', 'to_map', 'ignored']);

            $table->string('product_name');
            $table->string('material_name')->nullable();
            $table->decimal('qty_invoice', 15, 4)->nullable();
            $table->decimal('quantity_multiplier', 15, 6)->nullable();
            $table->decimal('stock_added', 15, 4)->nullable();
            $table->string('stock_unit', 10)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_import_logs');
    }
};
