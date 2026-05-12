<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Wrapper sul Merchant API di Revolut per il flusso "push payments to Terminal".
 *
 * Flusso tipico al momento dell'incasso:
 *   1. createOrder(amountMinor, 'EUR', 'tableorder-123')   → ottieni order_id
 *   2. listTerminals()                                     → scegli terminale (o usa default)
 *   3. pushPayment(orderId, terminalId)                    → il terminale chiede la carta
 *   4. webhook in entrata conferma pagamento
 *      (in alternativa, polling con getOrderStatus)
 *   5. cancelPayment(orderId) se il cassiere annulla       → race-handling: lo stato finale
 *      arriva comunque via getOrderStatus
 */
class RevolutTerminalService
{
    // Nota: Revolut sta migrando da path-versioning a header-versioning. I nuovi
    // endpoint Merchant API (incluso /terminals, /orders, /webhooks) vivono su
    // `/api/` senza prefisso versione, e la versione viaggia nell'header
    // `Revolut-Api-Version`. I vecchi endpoint legacy (es. /locations) sono ancora
    // su `/api/1.0/` — ma il service non li usa direttamente: la location_id si
    // configura una tantum dalla dashboard / via Insomnia.
    private const SANDBOX_BASE = 'https://sandbox-merchant.revolut.com/api';
    private const PROD_BASE    = 'https://merchant.revolut.com/api';
    private const API_VERSION  = '2024-09-01';

    public function isConfigured(): bool
    {
        if (Setting::isRevolutMockMode()) {
            return true;
        }
        $cfg = Setting::getRevolutConfig();
        return $cfg['api_key'] !== '' && $cfg['location_id'] !== '';
    }

    public function isMock(): bool
    {
        return Setting::isRevolutMockMode();
    }

    /**
     * Crea un order Revolut. Importo in minor units (es. 12.50 EUR → 1250).
     *
     * @return array{id:string, state:string, raw:array}
     */
    public function createOrder(int $amountMinor, string $currency, string $reference): array
    {
        if ($this->isMock()) {
            $fakeId = 'mock_' . bin2hex(random_bytes(8));
            Log::info('Revolut MOCK createOrder', ['id' => $fakeId, 'amount' => $amountMinor, 'reference' => $reference]);
            return ['id' => $fakeId, 'state' => 'pending', 'raw' => ['mock' => true]];
        }

        $cfg = $this->requireConfig();

        $payload = [
            'amount'      => $amountMinor,
            'currency'    => $currency,
            'description' => $reference,
            'capture_mode' => 'automatic',
            'location_id'  => $cfg['location_id'],
        ];

        $response = $this->client()->post('/orders', $payload);
        $this->throwOnError($response, 'createOrder');
        $data = $response->json();

        return [
            'id'    => (string) ($data['id'] ?? ''),
            'state' => (string) ($data['state'] ?? ''),
            'raw'   => $data,
        ];
    }

    /**
     * Spinge l'order al terminale fisico. Il terminale inizia a chiedere la carta.
     */
    public function pushPayment(string $orderId, string $terminalId): array
    {
        if ($this->isMock()) {
            Log::info('Revolut MOCK pushPayment', ['order_id' => $orderId, 'terminal_id' => $terminalId]);
            return ['mock' => true];
        }

        $response = $this->client()->post("/orders/{$orderId}/payments", [
            'payment_method' => [
                'type'        => 'card_present',
                'terminal_id' => $terminalId,
            ],
        ]);
        $this->throwOnError($response, 'pushPayment');
        return $response->json();
    }

    /**
     * Annulla un pagamento in corso. Race condition: se la carta è già stata
     * approvata, Revolut risponde con stato terminale (completed) — il chiamante
     * deve verificare `getOrderStatus` per decidere il comportamento UX.
     */
    public function cancelPayment(string $orderId): array
    {
        if ($this->isMock()) {
            Log::info('Revolut MOCK cancelPayment', ['order_id' => $orderId]);
            return ['mock' => true, 'state' => 'cancelled'];
        }

        $response = $this->client()->post("/orders/{$orderId}/cancel");
        $this->throwOnError($response, 'cancelPayment');
        return $response->json();
    }

    /**
     * Stato corrente dell'order. Usato sia per polling di sicurezza
     * (fallback in caso di webhook non ricevuti) che per disambiguare
     * la race condition di cancelPayment.
     *
     * In mock mode lo stato resta 'pending' finché il cassiere non clicca
     * "Simula pagamento OK" (vedi posPayMockComplete) o "Annulla transazione".
     *
     * @return array{id:string, state:string, raw:array}
     */
    public function getOrderStatus(string $orderId): array
    {
        if ($this->isMock()) {
            return ['id' => $orderId, 'state' => 'pending', 'raw' => ['mock' => true]];
        }

        $response = $this->client()->get("/orders/{$orderId}");
        $this->throwOnError($response, 'getOrderStatus');
        $data = $response->json();

        return [
            'id'    => (string) ($data['id'] ?? ''),
            'state' => (string) ($data['state'] ?? ''),
            'raw'   => $data,
        ];
    }

    /**
     * Elenca i terminali della location configurata.
     *
     * Revolut può rispondere con:
     *   - array piatto: [{...}, {...}]
     *   - wrapper paginato: {"items": [...]} oppure {"data": [...]}
     * Qui li normalizziamo in un array piatto, indicizzato 0..N-1.
     *
     * @return array<int, array{id:string, name:string, state:string}>
     */
    public function listTerminals(): array
    {
        if ($this->isMock()) {
            return [['id' => 'mock-terminal-001', 'name' => 'Mock Terminal', 'state' => 'ONLINE']];
        }

        $cfg = $this->requireConfig();
        $response = $this->client()->get('/terminals', [
            'location_id' => $cfg['location_id'],
        ]);
        $this->throwOnError($response, 'listTerminals');

        $body = $response->json();

        // Estrai l'array di terminali da qualsiasi shape di response
        $items = match (true) {
            is_array($body) && array_is_list($body) => $body,
            is_array($body) && isset($body['terminals']) && is_array($body['terminals']) => $body['terminals'],
            is_array($body) && isset($body['items'])     && is_array($body['items'])     => $body['items'],
            is_array($body) && isset($body['data'])      && is_array($body['data'])      => $body['data'],
            default => null,
        };

        if ($items === null) {
            Log::warning('RevolutTerminalService::listTerminals shape inattesa', [
                'body_sample' => is_array($body) ? array_keys($body) : gettype($body),
            ]);
            return [];
        }

        return array_values(array_map(
            fn ($t) => [
                'id'    => (string) ($t['id'] ?? ''),
                'name'  => (string) ($t['name'] ?? ''),
                'state' => (string) ($t['state'] ?? ''),
            ],
            $items
        ));
    }

    /**
     * Verifica la firma di un webhook in entrata secondo lo schema Revolut:
     *  - payload da firmare: "v1.{timestamp}.{rawBody}"
     *  - HMAC SHA-256 col webhook_secret, espresso come "v1=HEX"
     *  - l'header `Revolut-Signature` può contenere più firme separate da virgola
     *    (succede durante la rotation della secret), basta che UNA combaci.
     */
    public function verifyWebhookSignature(string $rawBody, string $signatureHeader, string $timestampHeader): bool
    {
        $cfg = Setting::getRevolutConfig();
        if ($cfg['webhook_secret'] === '' || $signatureHeader === '' || $timestampHeader === '') {
            return false;
        }

        $payloadToSign = 'v1.' . $timestampHeader . '.' . $rawBody;
        $expected      = 'v1=' . hash_hmac('sha256', $payloadToSign, $cfg['webhook_secret']);

        foreach (array_map('trim', explode(',', $signatureHeader)) as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }
        return false;
    }

    private function client(): PendingRequest
    {
        $cfg = $this->requireConfig();
        $base = $cfg['environment'] === 'production' ? self::PROD_BASE : self::SANDBOX_BASE;

        return Http::baseUrl($base)
            ->withToken($cfg['api_key'])
            ->withHeaders([
                'Accept'              => 'application/json',
                'Revolut-Api-Version' => self::API_VERSION,
            ])
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * @return array{environment:string, api_key:string, location_id:string, webhook_secret:string, timeout_seconds:int}
     */
    private function requireConfig(): array
    {
        $cfg = Setting::getRevolutConfig();
        if ($cfg['mock_mode'] && $cfg['environment'] === 'sandbox') {
            return $cfg;
        }
        if ($cfg['api_key'] === '') {
            throw new RuntimeException('Revolut API key non configurata in Settings');
        }
        if ($cfg['location_id'] === '') {
            throw new RuntimeException('Revolut location_id non configurato in Settings');
        }
        return $cfg;
    }

    private function throwOnError(\Illuminate\Http\Client\Response $response, string $op): void
    {
        if ($response->successful()) {
            return;
        }
        Log::error("RevolutTerminalService::{$op} failed", [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);
        throw new RuntimeException("Revolut {$op} error: HTTP {$response->status()}");
    }
}
