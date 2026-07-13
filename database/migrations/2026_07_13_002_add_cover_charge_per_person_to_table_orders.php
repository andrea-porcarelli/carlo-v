<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            // Override per-ordine del valore coperto. NULL = usa il default globale (Setting::getCoverCharge).
            $table->decimal('cover_charge_per_person', 10, 2)->nullable()->after('covers');
        });
    }

    public function down(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            $table->dropColumn('cover_charge_per_person');
        });
    }
};
