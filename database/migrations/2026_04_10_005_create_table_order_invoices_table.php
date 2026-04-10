<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_order_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_order_id')->constrained('table_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('invoice_code')->nullable();
            $table->integer('progressivo_invio')->nullable();
            $table->string('invoice_name')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->string('description')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('xml_path')->nullable();
            $table->longText('xml_content')->nullable();
            $table->text('mysond_response')->nullable();
            $table->enum('status', ['pending', 'sent', 'error'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_order_invoices');
    }
};
