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
        Schema::create('table_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_order_id')->nullable()->constrained('table_orders')->onDelete('cascade');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // create, update, delete, add_item, remove_item, change_status, etc.
            $table->string('entity_type'); // table_order, order_item
            $table->json('data_before')->nullable(); // Dati prima della modifica
            $table->json('data_after')->nullable(); // Dati dopo la modifica
            $table->json('changes')->nullable(); // Solo i campi modificati
            $table->text('notes')->nullable(); // Note aggiuntive
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['table_order_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_order_logs');
    }
};
