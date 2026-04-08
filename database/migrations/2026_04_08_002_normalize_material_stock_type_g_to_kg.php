<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converte tutti i materiali con stock_type='g' a 'kg'.
 * Divide per 1000: stock, alert_threshold, dish_materials.quantity, material_stocks.stock.
 *
 * Motivazione: MaterialStock deve usare un'unica unità base per tipo
 * (kg per il peso, cl per il volume, pz per i pezzi).
 * La conversione g→kg avviene già nel frontend durante il mapping fattura;
 * questa migration allinea i dati storici alla stessa convenzione.
 */
return new class extends Migration
{
    public function up(): void
    {
        $materials = DB::table('materials')->where('stock_type', 'g')->get();

        foreach ($materials as $material) {
            // Converti stock e soglia minima
            DB::table('materials')->where('id', $material->id)->update([
                'stock_type'      => 'kg',
                'stock'           => $material->stock / 1000,
                'alert_threshold' => $material->alert_threshold !== null
                    ? $material->alert_threshold / 1000
                    : null,
            ]);

            // Converti i carichi di magazzino storici
            DB::table('material_stocks')
                ->where('material_id', $material->id)
                ->update(['stock' => DB::raw('stock / 1000')]);

            // Converti le quantità nei dish_materials
            DB::table('dish_materials')
                ->where('material_id', $material->id)
                ->update(['quantity' => DB::raw('quantity / 1000')]);
        }
    }

    public function down(): void
    {
        // Non è possibile sapere quali materiali erano originariamente 'g',
        // quindi il rollback non è implementabile in modo sicuro.
        // Eseguire un restore da backup se necessario.
    }
};
