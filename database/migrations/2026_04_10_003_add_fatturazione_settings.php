<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key'         => 'company_vat_number',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Partita IVA azienda (cedente)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'company_name',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Ragione sociale azienda (cedente)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'indirizzo_fatturazione',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Indirizzo sede (via e numero civico)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'cap_fatturazione',
                'value'       => '',
                'type'        => 'string',
                'description' => 'CAP sede',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'comune_fatturazione',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Comune sede',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'provincia_fatturazione',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Provincia sede (2 lettere)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'tel_fatturazione',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Telefono azienda per fatturazione',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'email_fatturazione',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Email azienda per fatturazione',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'iban',
                'value'       => '',
                'type'        => 'string',
                'description' => 'IBAN bancario per pagamenti fattura',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'istituto_finanziario',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Nome banca / istituto finanziario',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'invoice_vat_rate',
                'value'       => '10',
                'type'        => 'decimal',
                'description' => 'Aliquota IVA default per fatture (es. 10)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'invoice_counter',
                'value'       => '0',
                'type'        => 'integer',
                'description' => 'Progressivo fatture emesse (auto-incrementato)',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insertOrIgnore($setting);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'company_vat_number',
            'company_name',
            'indirizzo_fatturazione',
            'cap_fatturazione',
            'comune_fatturazione',
            'provincia_fatturazione',
            'tel_fatturazione',
            'email_fatturazione',
            'iban',
            'istituto_finanziario',
            'invoice_vat_rate',
            'invoice_counter',
        ])->delete();
    }
};
