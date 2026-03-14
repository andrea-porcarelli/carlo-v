<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preconto_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_order_id')->constrained()->onDelete('cascade');
            $table->string('label', 100)->nullable();
            $table->json('items'); // [{order_item_id, dish_name, quantity, unit_price, subtotal}]
            $table->unsignedSmallInteger('covers')->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->string('payment_method', 50)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preconto_splits');
    }
};
