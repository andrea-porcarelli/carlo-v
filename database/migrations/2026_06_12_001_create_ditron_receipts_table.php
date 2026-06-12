<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ditron_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('table_order_id')->nullable()->constrained('table_orders')->nullOnDelete();
            $table->foreignId('preconto_split_id')->nullable()->constrained('preconto_splits')->nullOnDelete();

            $table->string('idempotency_key', 80)->unique();

            $table->unsignedInteger('receipt_number')->nullable()->index();

            $table->string('payment_method', 32);
            $table->decimal('importo_totale', 10, 2)->default(0);

            $table->enum('status', ['pending', 'sending', 'sent', 'failed'])->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->text('last_error')->nullable();

            $table->json('request_payload')->nullable();
            $table->longText('raw_command')->nullable();
            $table->longText('raw_err')->nullable();
            $table->unsignedInteger('elapsed_ms')->nullable();
            $table->string('agent_url', 255)->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['table_order_id', 'status']);
            $table->index(['preconto_split_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ditron_receipts');
    }
};
