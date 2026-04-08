<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapping_products', function (Blueprint $table) {
            $table->decimal('quantity_multiplier', 15, 6)->nullable()->after('material_id');
        });
    }

    public function down(): void
    {
        Schema::table('mapping_products', function (Blueprint $table) {
            $table->dropColumn('quantity_multiplier');
        });
    }
};
