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
        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('printer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('print_type'); // order, marcia, preconto
            $table->string('operation')->nullable(); // add, update, remove (for order type)
            $table->string('pdf_path')->nullable();
            $table->text('print_content')->nullable(); // Raw content for preview
            $table->json('print_data')->nullable(); // Additional data (items, split_count, etc.)
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['table_order_id', 'created_at']);
            $table->index('print_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_logs');
    }
};
