<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_incidents', function (Blueprint $table) {
            $table->id();

            $table->string('code', 80)->index();
            $table->string('severity', 16)->index();
            $table->string('category', 32)->index();

            $table->text('operator_message');
            $table->text('technical_detail')->nullable();
            $table->json('context')->nullable();

            $table->foreignId('table_order_id')->nullable()->constrained('table_orders')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 64)->nullable();

            $table->timestamp('telegram_notified_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['acknowledged_at', 'created_at']);
            $table->index(['category', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_incidents');
    }
};
