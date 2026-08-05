<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supporto emissione Nota di Credito (TD04) su table_order_invoices.
 *
 * - document_type: TD01 (Fattura, default) o TD04 (Nota di credito). Manteniamo
 *   il codice SDI grezzo per semplificare mapping XML e query.
 * - parent_invoice_id: riferimento self a fattura interna quando la nota credito
 *   nasce da una fattura emessa da questo sistema.
 * - parent_external_ref: JSON con {code, date, total, mirrored_invoice_id?}
 *   quando la nota credito nasce da una fattura emessa altrove ma visibile su
 *   MySond (fatture mirrored) o inserita manualmente.
 *
 * DatiFattureCollegate del XML SDI viene alimentato dall'uno o dall'altro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_order_invoices', function (Blueprint $table) {
            $table->string('document_type', 4)->default('TD01')->after('invoice_name');
            $table->unsignedBigInteger('parent_invoice_id')->nullable()->after('document_type');
            $table->json('parent_external_ref')->nullable()->after('parent_invoice_id');

            $table->foreign('parent_invoice_id')
                ->references('id')->on('table_order_invoices')
                ->nullOnDelete();

            $table->index(['document_type']);
        });
    }

    public function down(): void
    {
        Schema::table('table_order_invoices', function (Blueprint $table) {
            $table->dropForeign(['parent_invoice_id']);
            $table->dropIndex(['document_type']);
            $table->dropColumn(['document_type', 'parent_invoice_id', 'parent_external_ref']);
        });
    }
};
