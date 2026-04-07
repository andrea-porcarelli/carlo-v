<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_invoices', function (Blueprint $table) {
            $table->id();

            // Identificativi SDI
            $table->string('sdi_identifier')->nullable()->index();
            $table->string('file_name')->nullable();
            $table->string('transmission_format', 10)->nullable(); // FPR12, FPA12

            // Documento
            $table->string('document_type', 10)->nullable(); // TD01 ecc
            $table->string('currency', 10)->nullable();
            $table->string('number');
            $table->date('date');

            // Totali
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('rounding', 15, 2)->nullable();

            // Stato SDI / portale
            $table->string('status')->nullable();

            // XML originale
            $table->longText('original_xml');

            $table->timestamps();

            $table->unique(['number', 'date']);
        });

        Schema::create('external_invoice_transmissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_invoice_id')
                ->constrained('external_invoices')
                ->cascadeOnDelete();

            $table->string('country_id', 5)->nullable();
            $table->string('vat_id', 30)->nullable();
            $table->string('progressive_number')->nullable();
            $table->string('recipient_code', 10)->nullable();
            $table->string('recipient_pec')->nullable();

            $table->timestamps();
        });

        Schema::create('external_invoice_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_invoice_id')
                ->constrained('external_invoices')
                ->cascadeOnDelete();

            $table->enum('type', [
                'supplier',
                'customer',
                'fiscal_representative'
            ]);

            $table->string('company_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('vat_number')->nullable()->index();
            $table->string('tax_code')->nullable()->index();

            // Address
            $table->string('address')->nullable();
            $table->string('street_number')->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('province', 5)->nullable();
            $table->string('country', 5)->nullable();

            $table->timestamps();
        });

        Schema::create('external_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_invoice_id')
                ->constrained('external_invoices')
                ->cascadeOnDelete();

            $table->integer('line_number')->nullable();
            $table->text('description')->nullable();

            $table->decimal('quantity', 15, 4)->nullable();
            $table->string('unit_of_measure', 10)->nullable();

            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('total_price', 15, 2)->nullable();

            $table->decimal('vat_rate', 6, 2)->nullable();
            $table->string('vat_nature', 5)->nullable(); // N1, N2 ecc

            $table->timestamps();
        });

        Schema::create('external_invoice_vat_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_invoice_id')
                ->constrained('external_invoices')
                ->cascadeOnDelete();

            $table->decimal('vat_rate', 6, 2)->nullable();
            $table->string('vat_nature', 5)->nullable();

            $table->decimal('taxable_amount', 15, 2)->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();

            $table->timestamps();
        });

        Schema::create('external_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_invoice_id')
                ->constrained('external_invoices')
                ->cascadeOnDelete();

            $table->string('payment_method', 10)->nullable(); // MP01 ecc
            $table->date('due_date')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('iban')->nullable();

            $table->timestamps();
        });

        Schema::create('external_invoice_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_invoice_id')
                ->constrained('external_invoices')
                ->cascadeOnDelete();

            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->longText('base64_content')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {

        Schema::dropIfExists('external_invoice_attachments');
        Schema::dropIfExists('external_invoice_payments');
        Schema::dropIfExists('external_invoice_vat_summaries');
        Schema::dropIfExists('external_invoice_lines');
        Schema::dropIfExists('external_invoice_parties');
        Schema::dropIfExists('external_invoice_transmissions');
        Schema::dropIfExists('external_invoices');
    }
};
