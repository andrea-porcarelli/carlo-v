<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('type')->default('string'); // string, integer, decimal, boolean
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Inserisci il valore di default per il coperto
        DB::table('settings')->insert([
            'key' => 'cover_charge',
            'value' => '2.00',
            'type' => 'decimal',
            'description' => 'Prezzo del coperto per persona',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Inserisci il valore di default per la stampante preconto (ID della stampante)
        DB::table('settings')->insert([
            'key' => 'preconto_printer_id',
            'value' => '',
            'type' => 'integer',
            'description' => 'ID della stampante di default per il preconto',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
