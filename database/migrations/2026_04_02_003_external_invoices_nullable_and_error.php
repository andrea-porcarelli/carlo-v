<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_invoices', function (Blueprint $table) {
            $table->string('number')->nullable()->change();
            $table->date('date')->nullable()->change();
            $table->longText('original_xml')->nullable()->change();
            $table->text('import_error')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('external_invoices', function (Blueprint $table) {
            $table->dropColumn('import_error');
            $table->longText('original_xml')->nullable(false)->change();
            $table->date('date')->nullable(false)->change();
            $table->string('number')->nullable(false)->change();
        });
    }
};
