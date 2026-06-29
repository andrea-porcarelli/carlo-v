<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preconto_splits', function (Blueprint $table) {
            $table->json('items')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('preconto_splits', function (Blueprint $table) {
            $table->json('items')->nullable(false)->change();
        });
    }
};
