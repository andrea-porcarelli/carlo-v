<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'deploy_git_user')->delete();
    }

    public function down(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'         => 'deploy_git_user',
            'value'       => '',
            'type'        => 'string',
            'description' => 'Utente che esegue git pull (deploy) — lascia vuoto per usare www-data',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
};
