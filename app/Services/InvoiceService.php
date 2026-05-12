<?php

namespace App\Services;

use App\Facades\Utils;
use App\Models\Invoice;
use App\Models\TableOrderInvoice;
use Carbon\Carbon;
use Deved\FatturaElettronica\Codifiche\ModalitaPagamento;
use Deved\FatturaElettronica\Codifiche\RegimeFiscale;
use Deved\FatturaElettronica\Codifiche\TipoDocumento;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaBody\DatiBeniServizi\DatiRiepilogo;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaBody\DatiBeniServizi\DettaglioLinee;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaBody\DatiBeniServizi\Linea;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaBody\DatiGenerali;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaBody\DatiGenerali\ScontoMaggiorazione;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaBody\DatiPagamento;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaHeader\Common\DatiAnagrafici;
use Deved\FatturaElettronica\FatturaElettronica\FatturaElettronicaHeader\Common\Sede;
use Deved\FatturaElettronica\FatturaElettronicaFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public static function make_xml(TableOrderInvoice $invoice): array
    {

        $user = $invoice->customer;
        $anagraficaCedente = new DatiAnagrafici(
            Utils::setting('company_vat_number'),
            Utils::setting('company_name'),
            'IT',
            Utils::setting('company_vat_number'),
            RegimeFiscale::Ordinario
        );
        $sedeCedente = new Sede(
            'IT',
            Utils::setting('indirizzo_fatturazione'),
            Utils::setting('cap_fatturazione'),
            Utils::setting('comune_fatturazione'),
            Utils::setting('provincia_fatturazione')
        );


        $fatturaElettronicaFactory = new FatturaElettronicaFactory(
            $anagraficaCedente,
            $sedeCedente,
            Utils::setting('tel_fatturazione'),
            Utils::setting('email_fatturazione')
        );

        $anagraficaCessionario = new DatiAnagrafici($user->fiscal_code, $user->full_name, 'IT', $user->vat_number);

        $sedeCessionario = new Sede('IT', $user->address, $user->zip_code, $user->city, $user->province);

        $fatturaElettronicaFactory->setCessionarioCommittente($anagraficaCessionario, $sedeCessionario, $user->codice_destinatario, $user->pec_destinatario, $user->user_type === 'public_company');

        $datiGenerali = new DatiGenerali(
            TipoDocumento::Fattura,
            substr($invoice->created_at, 0, 10),
            $invoice->invoice_code,
            $invoice->amount,
        );


        if ($invoice->discount > 0) {
            $ScontoMaggiorazione = new ScontoMaggiorazione(ScontoMaggiorazione::SCONTO, null, $invoice->discount);
            $datiGenerali->setScontoMaggiorazione($ScontoMaggiorazione);
        }
        $datiPagamento = new DatiPagamento(
            self::resolveModalitaPagamento($invoice->payment_method, $user->user_type === 'public_company'),
            Carbon::parse($invoice->created_at)->addDays(15)->format('Y-m-d'),
            $user->user_type === 'public_company' ? $invoice->amount - $invoice->tax : $invoice->amount,
            Utils::setting('iban'),
            Utils::setting('istituto_finanziario'),
        );
        $linee = [];
        $imponibileImporto = 0;
        $aliquota = 0;
        foreach ($invoice->rows()->get() as $item) {
            $imponibileImporto += $item->price * $item->quantity;
            $aliquota = $item->tax->tax;
            $label = $item->label;
            if (isset($item->cart_product) && $item->cart_product->invoice_period !== 'forfait') {
                $label .= " | Periodo: " . $item->cart_product->service_period($invoice->id);
            }
            $linee[] = new Linea($label, $item->price, '#' . ($item->cart_product->id ?? ''), $item->quantity, 'pz', $item->tax->tax);
        }

        $dettaglioLinee = new DettaglioLinee($linee);
        $datiRiepilogo = null;
        if ($user->user_type === 'public_company') {
            $datiRiepilogo = new DatiRiepilogo(
                $imponibileImporto,
                $aliquota,
                'S',
            );
        }

        $fattura = $fatturaElettronicaFactory->create(
            $datiGenerali,
            $datiPagamento,
            $dettaglioLinee,
            $invoice->progressivo_invio,
            $datiRiepilogo,
        );

        $file = 'IT' . Utils::setting('company_vat_number') . '_' . $invoice->invoice_name . '.xml';
        try {
            $invoice_path = '/invoice/' . $invoice->year . '/' . $invoice->month . '/';
            Storage::makeDirectory($invoice_path);
            $path = storage_path("/app/private{$invoice_path}{$file}");
            $xml = $fattura->toXml();
            file_put_contents($path, $xml);
            return [
                'path' => $path,
                'content' => $xml,
                'response' => 'success'
            ];
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return [
                'response' => 'error',
                'message' => $e->getMessage()
            ];
        }

    }

    private static function resolveModalitaPagamento(?string $paymentMethod, bool $isPublicCompany): string
    {
        $map = [
            'contanti'           => ModalitaPagamento::Contanti,
            'pos'                => ModalitaPagamento::CartaDiPagamento,
            'carta'              => ModalitaPagamento::CartaDiPagamento,
            'bancomat'           => ModalitaPagamento::CartaDiPagamento,
            'bonifico'           => ModalitaPagamento::Bonifico,
            'assegno'            => ModalitaPagamento::Assegno,
            'bollettino'         => ModalitaPagamento::BollettinoPostale,
            'bollettino_postale' => ModalitaPagamento::BollettinoPostale,
        ];

        $key = strtolower(trim((string) $paymentMethod));
        if ($key !== '' && isset($map[$key])) {
            return $map[$key];
        }

        return $isPublicCompany ? ModalitaPagamento::Bonifico : ModalitaPagamento::BollettinoPostale;
    }
}
