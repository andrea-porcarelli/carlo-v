<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_order_corrispettivi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('table_order_id')->nullable()->constrained('table_orders')->nullOnDelete();
            $table->foreignId('preconto_split_id')->nullable()->constrained('preconto_splits')->nullOnDelete();

            $table->enum('tipo', ['emissione', 'annullo'])->default('emissione');
            $table->foreignId('annulla_corrispettivo_id')->nullable()->constrained('table_order_corrispettivi')->nullOnDelete();

            $table->string('progressivo_sdi', 50)->nullable()->index();
            $table->string('identificativo_sdi', 50)->nullable();

            $table->string('payment_method', 32);
            $table->decimal('importo_totale', 10, 2)->default(0);
            $table->decimal('imponibile', 10, 2)->default(0);
            $table->decimal('iva', 10, 2)->default(0);
            $table->decimal('aliquota_iva', 5, 2)->default(22.00);

            $table->enum('status', ['pending', 'sending', 'sent', 'failed', 'cancelled'])->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->text('last_error')->nullable();

            $table->longText('soap_request')->nullable();
            $table->longText('soap_response')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['table_order_id', 'status']);
            $table->index(['preconto_split_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_order_corrispettivi');
    }
};
