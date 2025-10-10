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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->integer('entity_id')->unsigned()->index();
            $table->text('entity_type');
            $table->text('filename');
            $table->text('folder');
            $table->text('extension');
            $table->text('mime_type');
            $table->text('media_type');
            $table->float('size');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
