<?php

namespace App\Support;

use App\Models\DitronReceipt;
use App\Models\TableOrderCorrispettivo;

/**
 * DTO normalizzato che rappresenta uno scontrino emesso,
 * agnostico rispetto al provider (Mysond/Ditron).
 *
 * Usato come tipo di ritorno di ReceiptIssuerInterface e come
 * payload per la risposta JSON al frontend (chiave "corrispettivo").
 */
final class IssuedReceiptDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $provider,
        public readonly string $status,
        public readonly ?string $progressivoSdi,
        public readonly ?string $identificativoSdi,
        public readonly ?int $receiptNumber,
        public readonly ?string $warning,
    ) {}

    public static function fromMysond(TableOrderCorrispettivo $c): self
    {
        return new self(
            id: $c->id,
            provider: 'mysond',
            status: $c->status,
            progressivoSdi: $c->progressivo_sdi,
            identificativoSdi: $c->identificativo_sdi,
            receiptNumber: null,
            warning: self::warningFor($c->status),
        );
    }

    public static function fromDitron(DitronReceipt $r): self
    {
        return new self(
            id: $r->id,
            provider: 'ditron',
            status: $r->status,
            progressivoSdi: null,
            identificativoSdi: null,
            receiptNumber: $r->receipt_number,
            warning: self::warningFor($r->status),
        );
    }

    public function toResponseArray(): array
    {
        return [
            'id'                 => $this->id,
            'provider'           => $this->provider,
            'status'             => $this->status,
            'progressivo_sdi'    => $this->progressivoSdi,
            'identificativo_sdi' => $this->identificativoSdi,
            'receipt_number'     => $this->receiptNumber,
            'warning'            => $this->warning,
        ];
    }

    private static function warningFor(string $status): ?string
    {
        return match ($status) {
            'failed'  => 'Scontrino non emesso. Verrà riprovato automaticamente.',
            'pending', 'sending' => 'Scontrino in elaborazione.',
            default   => null,
        };
    }
}
