<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->enum('user_type', ['private', 'company', 'public_company'])->default('private');
            $table->string('full_name');
            $table->string('fiscal_code')->nullable()->index();
            $table->string('vat_number')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('province', 5)->nullable();
            $table->string('codice_destinatario', 7)->nullable();
            $table->string('pec_destinatario')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
