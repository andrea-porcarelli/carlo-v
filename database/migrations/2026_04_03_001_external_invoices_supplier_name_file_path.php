<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_invoices', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('import_error');
            $table->string('file_path')->nullable()->after('supplier_name');
        });
    }

    public function down(): void
    {
        Schema::table('external_invoices', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'file_path']);
        });
    }
};
