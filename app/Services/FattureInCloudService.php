<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FattureInCloudService
{
    private string $baseUrl = 'https://api-v2.fattureincloud.it';
    private ?string $accessToken;
    private ?string $companyId;

    public function __construct()
    {
        $this->accessToken = Setting::get('fic_access_token');
        $this->companyId = Setting::get('fic_company_id');
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessToken) && !empty($this->companyId);
    }

    /**
     * Create an invoice in Fatture in Cloud
     *
     * @param array $data  Keys: amount, description, customer_name, customer_tax_code
     * @return array|null  API response data or null on failure/not-configured
     */
    public function createInvoice(array $data): ?array
    {
        if (!$this->isConfigured()) {
            Log::info('FattureInCloud: non configurato, creazione fattura saltata', [
                'amount' => $data['amount'] ?? null,
                'description' => $data['description'] ?? null,
            ]);
            return null;
        }

        try {
            $payload = [
                'data' => [
                    'type' => 'b2c',
                    'date' => now()->toDateString(),
                    'currency' => ['id' => 'EUR', 'exchange_rate' => '1.00000'],
                    'items_list' => [
                        [
                            'product_id' => null,
                            'name' => $data['description'] ?? 'Pasto completo',
                            'qty' => 1,
                            'measure' => '',
                            'net_price' => round($data['amount'] / 1.1, 4), // 10% IVA assumed
                            'gross_price' => $data['amount'],
                            'vat' => ['id' => 0], // Use default VAT configured in FIC
                        ],
                    ],
                ],
            ];

            if (!empty($data['customer_name'])) {
                $payload['data']['entity'] = [
                    'name' => $data['customer_name'],
                    'tax_code' => $data['customer_tax_code'] ?? null,
                ];
            }

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/c/{$this->companyId}/issued_documents", $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('FattureInCloud: fattura creata', [
                    'doc_id' => $responseData['data']['id'] ?? null,
                    'amount' => $data['amount'],
                ]);
                return $responseData;
            }

            Log::error('FattureInCloud: errore creazione fattura', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('FattureInCloud: eccezione API', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
