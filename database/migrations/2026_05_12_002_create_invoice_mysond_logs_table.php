<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_mysond_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_order_invoice_id')
                ->constrained('table_order_invoices')
                ->cascadeOnDelete();
            $table->string('operation', 64);
            $table->string('outcome', 16);
            $table->integer('esito')->nullable();
            $table->string('codice')->nullable();
            $table->text('descrizione')->nullable();
            $table->longText('request_xml')->nullable();
            $table->longText('response_xml')->nullable();
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->longText('exception_trace')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['table_order_invoice_id', 'created_at'], 'imsl_invoice_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_mysond_logs');
    }
};
