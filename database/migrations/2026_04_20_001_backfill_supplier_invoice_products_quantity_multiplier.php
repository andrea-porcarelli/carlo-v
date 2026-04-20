<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Back-fill supplier_invoice_products.quantity_multiplier con il default dal mapping
        // per le righe dove è NULL e il mapping (supplier_id, product_name) esiste.
        DB::statement("
            UPDATE supplier_invoice_products sip
            INNER JOIN supplier_invoices si ON si.id = sip.supplier_invoice_id
            INNER JOIN mapping_products mp
                ON mp.supplier_id = si.supplier_id
                AND mp.product_name = sip.product_name
            SET sip.quantity_multiplier = mp.quantity_multiplier
            WHERE sip.quantity_multiplier IS NULL
              AND mp.quantity_multiplier IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Non reversibile — i valori originali erano NULL e non sono tracciati.
    }
};
