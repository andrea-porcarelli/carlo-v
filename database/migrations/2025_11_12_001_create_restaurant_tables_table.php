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
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->integer('table_number')->unique();
            $table->integer('capacity')->default(4);
            $table->decimal('position_x', 8, 2)->nullable();
            $table->decimal('position_y', 8, 2)->nullable();
            $table->enum('status', ['free', 'occupied', 'reserved'])->default('free');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
