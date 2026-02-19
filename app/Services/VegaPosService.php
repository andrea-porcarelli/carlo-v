<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * VEGA3000 POS terminal integration via local LAN JSONPOS (TCP port 10001).
 *
 * Protocol: open TCP connection → send JSON request + newline → read JSON response.
 * Response codes: "00" or "0" = approved; anything else = declined/error.
 */
class VegaPosService
{
    private string $host;
    private int    $port;
    private int    $timeout;

    public function __construct()
    {
        $this->host    = (string) Setting::get('pos_terminal_ip', '');
        $this->port    = (int)    Setting::get('pos_terminal_port', 10001);
        $this->timeout = 45; // seconds — enough for customer interaction
    }

    /**
     * Returns true if a terminal IP has been configured in settings.
     */
    public function isConfigured(): bool
    {
        return $this->host !== '';
    }

    /**
     * Send a purchase request to the POS terminal.
     *
     * @param  float  $amount     Amount in euros (e.g. 79.00)
     * @param  string $reference  Unique reference string (e.g. "ORD-42")
     * @return array{success: bool, response_code: string, message: string, raw: array}
     */
    public function sendPurchase(float $amount, string $reference = ''): array
    {
        if (!$this->isConfigured()) {
            return $this->result(false, '', 'Terminale POS non configurato');
        }

        $amountCents = (int) round($amount * 100);
        if ($amountCents <= 0) {
            return $this->result(false, '', 'Importo non valido');
        }

        $payload = json_encode([
            'command'       => 'purchase',
            'amount'        => (string) $amountCents,
            'currency_code' => '978', // EUR (ISO 4217)
            'reference'     => $reference ?: ('POS-' . uniqid()),
        ]);

        return $this->sendCommand($payload);
    }

    /**
     * Send a cancel/void request to the POS terminal.
     * Useful if the order was cancelled after sending to POS.
     */
    public function sendCancel(string $reference = ''): array
    {
        if (!$this->isConfigured()) {
            return $this->result(false, '', 'Terminale POS non configurato');
        }

        $payload = json_encode([
            'command'   => 'cancel',
            'reference' => $reference ?: ('CXL-' . uniqid()),
        ]);

        return $this->sendCommand($payload);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function sendCommand(string $jsonPayload): array
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);

        if (!$socket) {
            Log::error("VegaPOS: Cannot connect to {$this->host}:{$this->port} — $errstr ($errno)");
            return $this->result(false, '', "Connessione al POS non riuscita: $errstr");
        }

        stream_set_timeout($socket, $this->timeout);

        fwrite($socket, $jsonPayload . "\n");

        $raw = '';
        $deadline = time() + $this->timeout;

        while (!feof($socket) && time() < $deadline) {
            $chunk = fgets($socket, 4096);
            if ($chunk === false) break;
            $raw .= $chunk;
            // Stop as soon as we have a complete JSON object
            if (json_decode($raw) !== null) break;
        }

        fclose($socket);

        if (empty($raw)) {
            Log::error("VegaPOS: Empty response from {$this->host}:{$this->port}");
            return $this->result(false, '', 'Nessuna risposta dal terminale POS');
        }

        $response = json_decode($raw, true);
        if (!is_array($response)) {
            Log::error("VegaPOS: Invalid JSON response: " . substr($raw, 0, 300));
            return $this->result(false, '', 'Risposta POS non valida');
        }

        // Normalise response code across different terminal firmware versions
        $code     = (string) ($response['response_code'] ?? $response['result'] ?? $response['status'] ?? '');
        $approved = in_array($code, ['0', '00', 'approved', 'APPROVED', 'success', 'SUCCESS'], true);
        $message  = $approved
            ? 'Pagamento approvato'
            : ($response['message'] ?? $response['description'] ?? "Rifiutato (codice: $code)");

        Log::info("VegaPOS: response_code=$code approved=" . ($approved ? 'yes' : 'no'));

        return $this->result($approved, $code, $message, $response);
    }

    private function result(bool $success, string $code, string $message, array $raw = []): array
    {
        return [
            'success'       => $success,
            'response_code' => $code,
            'message'       => $message,
            'raw'           => $raw,
        ];
    }
}
