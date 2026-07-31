<?php

namespace App\Support;

/**
 * Catalogo dei codici errore operativi visibili all'operatore.
 *
 * Ogni caso porta:
 *  - il codice stabile esposto ad API/frontend (case value)
 *  - la severity (info/warn/error/critical) usata per stile UI e priorità Telegram
 *  - un messaggio operatore in italiano (con eventuali placeholder {chiave})
 *  - un flag notifyTelegram che decide se scattare l'alert automatico
 *
 * Aggiungere qui i nuovi codici mantiene un unico punto di verità sfruttabile
 * sia dai job async che dai controller sincroni.
 */
enum OperationalErrorCode: string
{
    // Stampanti termiche
    case PRINT_KITCHEN_UNREACHABLE = 'PRINT.KITCHEN.UNREACHABLE';
    case PRINT_KITCHEN_TIMEOUT     = 'PRINT.KITCHEN.TIMEOUT';
    case PRINT_KITCHEN_FAILED      = 'PRINT.KITCHEN.FAILED';
    case PRINT_BAR_UNREACHABLE     = 'PRINT.BAR.UNREACHABLE';
    case PRINT_BAR_TIMEOUT         = 'PRINT.BAR.TIMEOUT';
    case PRINT_BAR_FAILED          = 'PRINT.BAR.FAILED';
    case PRINT_RECEIPT_UNREACHABLE = 'PRINT.RECEIPT.UNREACHABLE';
    case PRINT_RECEIPT_FAILED      = 'PRINT.RECEIPT.FAILED';
    case PRINT_PRECONTO_FAILED     = 'PRINT.PRECONTO.FAILED';
    case PRINT_COMUNICA_FAILED     = 'PRINT.COMUNICA.FAILED';

    // Ditron (cassa fiscale)
    case DITRON_AGENT_DOWN         = 'DITRON.AGENT.DOWN';
    case DITRON_CASSA_PAPER        = 'DITRON.CASSA.PAPER';
    case DITRON_CASSA_BUSY         = 'DITRON.CASSA.BUSY';
    case DITRON_RECEIPT_FAILED     = 'DITRON.RECEIPT.FAILED';
    case DITRON_TENDER_INVALID     = 'DITRON.TENDER.INVALID';
    case DITRON_CLOSE_DAY_FAILED   = 'DITRON.CLOSE_DAY.FAILED';

    // Cassetto contanti VNE
    case CASHDRAWER_VNE_UNREACHABLE = 'CASHDRAWER.VNE.UNREACHABLE';
    case CASHDRAWER_VNE_TIMEOUT     = 'CASHDRAWER.VNE.TIMEOUT';
    case CASHDRAWER_VNE_BUSY        = 'CASHDRAWER.VNE.BUSY';
    case CASHDRAWER_VNE_REJECTED    = 'CASHDRAWER.VNE.REJECTED';

    public function severity(): string
    {
        return match ($this) {
            self::DITRON_AGENT_DOWN,
            self::CASHDRAWER_VNE_UNREACHABLE => 'critical',

            self::PRINT_PRECONTO_FAILED,
            self::PRINT_COMUNICA_FAILED,
            self::DITRON_CASSA_BUSY,
            self::CASHDRAWER_VNE_BUSY,
            self::CASHDRAWER_VNE_REJECTED => 'warn',

            default => 'error',
        };
    }

    public function operatorMessage(array $context = []): string
    {
        $template = match ($this) {
            self::PRINT_KITCHEN_UNREACHABLE => 'Stampante cucina non raggiungibile — verifica cavo, rete e alimentazione.',
            self::PRINT_KITCHEN_TIMEOUT     => 'Timeout stampa cucina — l\'ordine potrebbe non essere stato ricevuto: controlla in cucina.',
            self::PRINT_KITCHEN_FAILED      => 'Stampa cucina fallita ({motivo}) — riprova o avvisa la cucina a voce.',
            self::PRINT_BAR_UNREACHABLE     => 'Stampante bar non raggiungibile — verifica cavo, rete e alimentazione.',
            self::PRINT_BAR_TIMEOUT         => 'Timeout stampa bar — l\'ordine potrebbe non essere stato ricevuto.',
            self::PRINT_BAR_FAILED          => 'Stampa bar fallita ({motivo}) — riprova o avvisa il bar a voce.',
            self::PRINT_RECEIPT_UNREACHABLE => 'Stampante cassa non raggiungibile — verifica cavo e alimentazione.',
            self::PRINT_RECEIPT_FAILED      => 'Stampa ricevuta cassa fallita ({motivo}).',
            self::PRINT_PRECONTO_FAILED     => 'Preconto non stampato ({motivo}) — riprova.',
            self::PRINT_COMUNICA_FAILED     => 'Comunicazione al reparto non stampata ({motivo}).',

            self::DITRON_AGENT_DOWN         => 'Servizio cassa fiscale Ditron non risponde — chiama assistenza tecnica.',
            self::DITRON_CASSA_PAPER        => 'Carta esaurita nella cassa fiscale — sostituire il rotolo prima di riprovare.',
            self::DITRON_CASSA_BUSY         => 'Cassa fiscale occupata — attendi qualche secondo e riprova.',
            self::DITRON_RECEIPT_FAILED     => 'Emissione scontrino fiscale fallita ({motivo}).',
            self::DITRON_TENDER_INVALID     => 'Metodo di pagamento non configurato in cassa — verifica impostazioni Ditron.',
            self::DITRON_CLOSE_DAY_FAILED   => 'Chiusura giornaliera Ditron fallita ({motivo}) — ritenta manualmente.',

            self::CASHDRAWER_VNE_UNREACHABLE => 'Cassa contanti non raggiungibile — pagamento in sospeso, controlla il dispositivo.',
            self::CASHDRAWER_VNE_TIMEOUT     => 'Cassa contanti non risponde in tempo — verifica lo stato del dispositivo.',
            self::CASHDRAWER_VNE_BUSY        => 'Cassa contanti occupata da un\'altra operazione — attendi e riprova.',
            self::CASHDRAWER_VNE_REJECTED    => 'Cassa contanti ha rifiutato l\'operazione ({motivo}).',
        };

        return self::interpolate($template, $context);
    }

    public function notifyTelegram(): bool
    {
        return match ($this) {
            self::DITRON_CASSA_BUSY,
            self::CASHDRAWER_VNE_BUSY => false,

            default => true,
        };
    }

    /**
     * Categoria tecnica per raggruppamenti (dashboard, statistiche).
     */
    public function category(): string
    {
        return match (true) {
            str_starts_with($this->value, 'PRINT.')       => 'print',
            str_starts_with($this->value, 'DITRON.')      => 'ditron',
            str_starts_with($this->value, 'CASHDRAWER.')  => 'cashdrawer',
            default                                       => 'other',
        };
    }

    /**
     * Icona da usare nelle notifiche Telegram.
     */
    public function telegramEmoji(): string
    {
        return match ($this->severity()) {
            'critical' => '🚨',
            'error'    => '❌',
            'warn'     => '⚠️',
            default    => 'ℹ️',
        };
    }

    private static function interpolate(string $template, array $context): string
    {
        if ($context === []) {
            return preg_replace('/\s*\(\{[a-z_]+\}\)/u', '', $template) ?? $template;
        }

        return preg_replace_callback('/\{([a-z_]+)\}/u', function (array $m) use ($context) {
            $key = $m[1];
            if (isset($context[$key]) && is_scalar($context[$key])) {
                return (string) $context[$key];
            }
            return $m[0];
        }, $template) ?? $template;
    }
}
