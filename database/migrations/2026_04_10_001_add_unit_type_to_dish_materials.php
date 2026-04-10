<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dish_materials', function (Blueprint $table) {
            $table->string('unit_type', 5)->nullable()->after('quantity');
        });

        // Popola i record esistenti con l'unità base del relativo materiale
        DB::statement("
            UPDATE dish_materials dm
            JOIN materials m ON dm.material_id = m.id
            SET dm.unit_type = m.stock_type
        ");
    }

    public function down(): void
    {
        Schema::table('dish_materials', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });
    }
};