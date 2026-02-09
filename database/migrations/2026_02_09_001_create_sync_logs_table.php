<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction'); // pull_from_web, pull_from_local
            $table->string('peer_role'); // web, local
            $table->string('table_name');
            $table->string('status')->default('running'); // running, success, partial, failed
            $table->unsignedInteger('records_exported')->default(0);
            $table->unsignedInteger('records_imported')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_deleted')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('details')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('triggered_by')->default('manual'); // manual, schedule
            $table->timestamps();

            $table->index(['table_name', 'status']);
            $table->index('created_at');
        });

        Schema::create('sync_watermarks', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->unique();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_watermarks');
        Schema::dropIfExists('sync_logs');
    }
};
