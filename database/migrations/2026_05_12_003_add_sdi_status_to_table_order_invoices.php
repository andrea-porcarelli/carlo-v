<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('table_order_invoices', function (Blueprint $table) {
            $table->smallInteger('sdi_status')->nullable()->after('mysond_response');
            $table->string('sdi_status_label')->nullable()->after('sdi_status');
            $table->timestamp('sdi_checked_at')->nullable()->after('sdi_status_label');
            $table->text('sdi_response')->nullable()->after('sdi_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('table_order_invoices', function (Blueprint $table) {
            $table->dropColumn(['sdi_status', 'sdi_status_label', 'sdi_checked_at', 'sdi_response']);
        });
    }
};
