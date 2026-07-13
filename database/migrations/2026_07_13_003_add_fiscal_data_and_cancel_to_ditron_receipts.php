<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ditron_receipts', function (Blueprint $table) {
            // Dati fiscali restituiti dalla cassa dopo l'emissione (GETP prop 1/10/12).
            $table->string('fiscal_number', 32)->nullable()->after('receipt_number');
            $table->date('fiscal_date')->nullable()->after('fiscal_number');
            $table->unsignedInteger('z_number')->nullable()->after('fiscal_date');
            $table->string('matricola', 32)->nullable()->after('z_number');

            // Tipo record + collegamento sale ↔ cancel.
            $table->enum('type', ['sale', 'cancel'])->default('sale')->after('matricola');

            $table->foreignId('cancels_receipt_id')
                ->nullable()
                ->after('type')
                ->constrained('ditron_receipts')
                ->nullOnDelete();

            $table->foreignId('cancelled_by_receipt_id')
                ->nullable()
                ->after('cancels_receipt_id')
                ->constrained('ditron_receipts')
                ->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by_receipt_id');

            $table->text('cancel_reason')->nullable()->after('cancelled_at');

            $table->foreignId('cancelled_by_user_id')
                ->nullable()
                ->after('cancel_reason')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['type', 'status']);
            $table->index(['fiscal_number', 'fiscal_date']);
        });
    }

    public function down(): void
    {
        Schema::table('ditron_receipts', function (Blueprint $table) {
            $table->dropForeign(['cancels_receipt_id']);
            $table->dropForeign(['cancelled_by_receipt_id']);
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropIndex(['type', 'status']);
            $table->dropIndex(['fiscal_number', 'fiscal_date']);
            $table->dropColumn([
                'fiscal_number',
                'fiscal_date',
                'z_number',
                'matricola',
                'type',
                'cancels_receipt_id',
                'cancelled_by_receipt_id',
                'cancelled_at',
                'cancel_reason',
                'cancelled_by_user_id',
            ]);
        });
    }
};
