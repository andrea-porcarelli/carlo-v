<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawer_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('table_order_id')->nullable()->index();
            $table->string('operation_id')->nullable()->index();
            $table->string('event_type'); // start, poll, cancel, completed, error
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('table_order_id')->references('id')->on('table_orders')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_logs');
    }
};
