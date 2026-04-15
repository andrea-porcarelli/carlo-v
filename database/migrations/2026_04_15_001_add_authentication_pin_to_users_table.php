<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('authentication_pin')->nullable()->unique()->after('backoffice_password');
            $table->index('authentication_pin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_authentication_pin_index');
            $table->dropUnique('users_authentication_pin_unique');
            $table->dropColumn('authentication_pin');
        });
    }
};