<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ditron_daily_closures', function (Blueprint $table) {
            $table->id();

            $table->date('closure_date')->unique();

            $table->enum('source', ['auto', 'manual'])->index();
            $table->enum('status', ['pending', 'sending', 'done', 'failed'])->default('pending')->index();

            $table->unsignedTinyInteger('tipo')->default(2);
            $table->string('idempotency_key', 80)->unique();

            $table->unsignedInteger('receipt_number')->nullable();
            $table->longText('raw_command')->nullable();
            $table->longText('raw_err')->nullable();
            $table->unsignedInteger('elapsed_ms')->nullable();
            $table->string('agent_mode', 16)->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ditron_daily_closures');
    }
};
