<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Evita duplicati righe fattura
        Schema::table('external_invoice_lines', function (Blueprint $table) {
            $table->unique(['external_invoice_id', 'line_number'], 'ext_invoice_lines_unique');
        });

        // Evita duplicati riepilogo IVA
        Schema::table('external_invoice_vat_summaries', function (Blueprint $table) {
            $table->unique(['external_invoice_id', 'vat_rate', 'vat_nature'], 'ext_invoice_vat_unique');
        });

        // Evita duplicati pagamenti
        Schema::table('external_invoice_payments', function (Blueprint $table) {
            $table->unique(['external_invoice_id', 'payment_method', 'due_date'], 'ext_invoice_payments_unique');
        });

        // Evita duplicati anagrafiche (supplier/customer/rappresentante)
        Schema::table('external_invoice_parties', function (Blueprint $table) {
            $table->unique(['external_invoice_id', 'type', 'vat_number', 'tax_code'], 'ext_invoice_parties_unique');
        });

        // Evita duplicati fatture principali
        Schema::table('external_invoices', function (Blueprint $table) {
            $table->unique(['number', 'date'], 'ext_invoices_unique');
        });
    }

    public function down(): void
    {
        Schema::table('external_invoice_lines', function (Blueprint $table) {
            $table->dropUnique('ext_invoice_lines_unique');
        });

        Schema::table('external_invoice_vat_summaries', function (Blueprint $table) {
            $table->dropUnique('ext_invoice_vat_unique');
        });

        Schema::table('external_invoice_payments', function (Blueprint $table) {
            $table->dropUnique('ext_invoice_payments_unique');
        });

        Schema::table('external_invoice_parties', function (Blueprint $table) {
            $table->dropUnique('ext_invoice_parties_unique');
        });

        Schema::table('external_invoices', function (Blueprint $table) {
            $table->dropUnique('ext_invoices_unique');
        });
    }
};
