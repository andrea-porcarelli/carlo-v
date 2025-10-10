<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->text('phone')->after('vat_number')->nullable();
            $table->text('email')->after('phone')->nullable();
            $table->text('sdi')->after('email')->nullable();
            $table->text('pec')->after('sdi')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->dropColumn('email');
            $table->dropColumn('sdi');
            $table->dropColumn('pec');
        });
    }
};
