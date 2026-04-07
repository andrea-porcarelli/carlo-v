<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_invoice_lines', function (Blueprint $table) {
            $table->boolean('ignore_mapping')->default(false)->after('vat_nature');
            $table->float('quantity_multiplier')->default(1)->after('ignore_mapping');
        });
    }

    public function down(): void
    {
        Schema::table('external_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['ignore_mapping', 'quantity_multiplier']);
        });
    }
};
