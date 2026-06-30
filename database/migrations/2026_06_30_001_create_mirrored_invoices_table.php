<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mirrored_invoices
 *
 * Cache locale di TUTTE le fatture visibili sull'Azienda MySond (incluse
 * quelle emesse da altri progetti che condividono le credenziali). MySond
 * è l'unica fonte certa: questa tabella è una proiezione, popolata on-demand
 * (carlo-v: apertura sezione Fatture / misuraca: prima di ogni emissione).
 *
 * Le scartate non riconosciute (stato ∈ {1,6,10} AND acknowledged_at IS NULL)
 * sono il sottoinsieme che blocca le nuove emissioni.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mirrored_invoices', function (Blueprint $table) {
            $table->id();
            // Identificativo MySond del file (es. "IT01234567890_A0042").
            $table->string('file_name')->unique();
            // Campi anagrafici fattura, snapshot da MySond.
            $table->string('mysond_code')->nullable();
            $table->date('mysond_date')->nullable();
            $table->decimal('mysond_total', 12, 2)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_vat')->nullable();
            $table->string('customer_cf')->nullable();
            // Stato SDI numerico (vedi TableOrderInvoice::SDI_STATUS_LABELS).
            $table->unsignedTinyInteger('stato')->nullable();
            $table->string('stato_label')->nullable();
            // XML del documento, lazy-loaded on click "Vedi XML".
            $table->longText('xml_content')->nullable();
            $table->timestamp('xml_fetched_at')->nullable();
            // FK soft alla fattura locale matching (se è stata emessa da
            // questo progetto). Null se la fattura è esterna.
            $table->unsignedBigInteger('local_invoice_id')->nullable()->index();
            // Ack per scartate: NULL = bloccante.
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->text('acknowledged_note')->nullable();
            $table->timestamp('first_synced_at');
            $table->timestamp('last_synced_at');
            $table->timestamps();

            $table->index(['stato', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mirrored_invoices');
    }
};
