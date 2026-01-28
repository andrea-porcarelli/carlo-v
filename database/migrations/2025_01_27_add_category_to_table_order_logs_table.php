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
        Schema::table('table_order_logs', function (Blueprint $table) {
            $table->string('category')->nullable()->after('action');
            $table->index(['category', 'created_at']);
        });

        // Popolamento retroattivo dei record esistenti
        $actionCategoryMap = [
            // Categoria 'order' - Gestione ordine/tavolo
            'create_order' => 'order',
            'update_order' => 'order',
            'delete_order' => 'order',
            'close_order' => 'order',
            'reopen_order' => 'order',
            'change_status' => 'order',
            // Categoria 'item' - Gestione piatti
            'add_item' => 'item',
            'update_item' => 'item',
            'remove_item' => 'item',
            'add_item_notes' => 'item',
            'add_item_extras' => 'item',
            'update_item_quantity' => 'item',
            // Categoria 'covers' - Gestione coperti
            'update_covers' => 'covers',
            // Categoria 'print' - Stampe
            'print_marcia' => 'print',
            'print_preconto' => 'print',
        ];

        foreach ($actionCategoryMap as $action => $category) {
            DB::table('table_order_logs')
                ->where('action', $action)
                ->whereNull('category')
                ->update(['category' => $category]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_order_logs', function (Blueprint $table) {
            $table->dropIndex(['category', 'created_at']);
            $table->dropColumn('category');
        });
    }
};
