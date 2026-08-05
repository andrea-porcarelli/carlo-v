<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estende l'enum user_type con i tipi soggetto aggiuntivi previsti dalla
        // legge (Ente Non Commerciale, Ditta Individuale/Libero Prof., Estero).
        DB::statement("ALTER TABLE customers MODIFY user_type ENUM('private','company','public_company','non_profit_entity','sole_trader','foreign') NOT NULL DEFAULT 'private'");

        Schema::table('customers', function (Blueprint $table) {
            $table->string('country', 2)->default('IT')->after('user_type');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('country');
        });

        // Riporta i record incompatibili a 'private' prima di stringere l'enum.
        DB::table('customers')
            ->whereIn('user_type', ['non_profit_entity', 'sole_trader', 'foreign'])
            ->update(['user_type' => 'private']);

        DB::statement("ALTER TABLE customers MODIFY user_type ENUM('private','company','public_company') NOT NULL DEFAULT 'private'");
    }
};
