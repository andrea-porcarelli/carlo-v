<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Dish;
use App\Models\InvoiceMysondLog;
use App\Models\Setting;
use App\Models\TableOrderInvoice;
use App\Services\MysondFatturaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class QuickInvoiceWizard extends Component
{
    public int $step = 1;

    // ── Step 1 – Customer ──────────────────────────────────────────────────
    public string $customerSearch = '';
    public array $customerSearchResults = [];
    public ?int $selectedCustomerId = null;
    public ?array $storedCustomerSnapshot = null; // original DB values for diff display

    public string $userType = 'private';
    public string $fullName = '';
    public string $fiscalCode = '';
    public string $vatNumber = '';
    public string $address = '';
    public string $zipCode = '';
    public string $city = '';
    public string $province = '';
    public string $codiceDestinatario = '';
    public string $pecDestinatario = '';

    public bool $persistCustomerChanges = true;

    // ── Step 2 – Lines ─────────────────────────────────────────────────────
    public string $dishSearch = '';
    public array $dishSearchResults = [];
    public array $lines = []; // [{label, quantity, unit_price, dish_id|null}]
    public float $vatRate = 10.0;
    public float $discount = 0.0;
    public string $description = ''; // optional invoice-level note shown on print
    public string $paymentMethod = 'bonifico';

    // ── Step 3 – Submission result ─────────────────────────────────────────
    public ?array $result = null;
    public bool $submitting = false;

    public function mount(): void
    {
        $this->vatRate = (float) Setting::get('invoice_vat_rate', 10);
    }

    // ────────────────────── Customer search ────────────────────────────────
    public function updatedCustomerSearch(): void
    {
        $term = trim($this->customerSearch);
        if (strlen($term) < 2) {
            $this->customerSearchResults = [];
            return;
        }

        $like = '%' . $term . '%';
        $this->customerSearchResults = Customer::where('full_name', 'like', $like)
            ->orWhere('fiscal_code', 'like', $like)
            ->orWhere('vat_number', 'like', $like)
            ->orderBy('full_name')
            ->limit(10)
            ->get(['id', 'user_type', 'full_name', 'fiscal_code', 'vat_number', 'city'])
            ->toArray();
    }

    public function selectCustomer(int $customerId): void
    {
        $customer = Customer::find($customerId);
        if (!$customer) return;

        $this->selectedCustomerId   = $customer->id;
        $this->storedCustomerSnapshot = $customer->only([
            'user_type', 'full_name', 'fiscal_code', 'vat_number',
            'address', 'zip_code', 'city', 'province',
            'codice_destinatario', 'pec_destinatario',
        ]);

        $this->userType            = $customer->user_type ?? 'private';
        $this->fullName            = (string) ($customer->full_name ?? '');
        $this->fiscalCode          = (string) ($customer->fiscal_code ?? '');
        $this->vatNumber           = (string) ($customer->vat_number ?? '');
        $this->address             = (string) ($customer->address ?? '');
        $this->zipCode             = (string) ($customer->zip_code ?? '');
        $this->city                = (string) ($customer->city ?? '');
        $this->province            = (string) ($customer->province ?? '');
        $this->codiceDestinatario  = (string) ($customer->codice_destinatario ?? '');
        $this->pecDestinatario     = (string) ($customer->pec_destinatario ?? '');

        $this->customerSearch = '';
        $this->customerSearchResults = [];
    }

    public function clearCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->storedCustomerSnapshot = null;
        $this->userType = 'private';
        $this->fullName = '';
        $this->fiscalCode = '';
        $this->vatNumber = '';
        $this->address = '';
        $this->zipCode = '';
        $this->city = '';
        $this->province = '';
        $this->codiceDestinatario = '';
        $this->pecDestinatario = '';
    }

    /**
     * Returns the list of fields where current form value differs from the stored DB snapshot.
     */
    public function getChangedCustomerFieldsProperty(): array
    {
        if (!$this->storedCustomerSnapshot) return [];

        $current = [
            'user_type'           => $this->userType,
            'full_name'           => $this->fullName,
            'fiscal_code'         => $this->fiscalCode,
            'vat_number'          => $this->vatNumber,
            'address'             => $this->address,
            'zip_code'            => $this->zipCode,
            'city'                => $this->city,
            'province'            => $this->province,
            'codice_destinatario' => $this->codiceDestinatario,
            'pec_destinatario'    => $this->pecDestinatario,
        ];

        $changed = [];
        foreach ($current as $k => $v) {
            $stored = (string) ($this->storedCustomerSnapshot[$k] ?? '');
            if ((string) $v !== $stored) $changed[$k] = true;
        }
        return $changed;
    }

    // ────────────────────── Dish search & lines ────────────────────────────
    public function updatedDishSearch(): void
    {
        $term = trim($this->dishSearch);
        if (strlen($term) < 2) {
            $this->dishSearchResults = [];
            return;
        }

        // Include INACTIVE dishes too, as required.
        $this->dishSearchResults = Dish::where('label', 'like', '%' . $term . '%')
            ->orderBy('is_active', 'desc')
            ->orderBy('label')
            ->limit(10)
            ->get(['id', 'label', 'price', 'is_active'])
            ->toArray();
    }

    public function addLineFromDish(int $dishId): void
    {
        $dish = Dish::find($dishId);
        if (!$dish) return;

        $this->lines[] = [
            'label'      => (string) $dish->label,
            'quantity'   => 1,
            'unit_price' => (float) $dish->price,
            'dish_id'    => $dish->id,
        ];

        $this->dishSearch = '';
        $this->dishSearchResults = [];
    }

    public function addCustomLine(): void
    {
        $this->lines[] = [
            'label'      => '',
            'quantity'   => 1,
            'unit_price' => 0.0,
            'dish_id'    => null,
        ];
    }

    public function removeLine(int $index): void
    {
        if (isset($this->lines[$index])) {
            array_splice($this->lines, $index, 1);
        }
    }

    public function incrementQuantity(int $index): void
    {
        if (!isset($this->lines[$index])) return;
        $this->lines[$index]['quantity'] = (float) $this->lines[$index]['quantity'] + 1;
    }

    public function decrementQuantity(int $index): void
    {
        if (!isset($this->lines[$index])) return;
        $q = (float) $this->lines[$index]['quantity'];
        $this->lines[$index]['quantity'] = max(1, $q - 1);
    }

    // ────────────────────── Totals ─────────────────────────────────────────
    public function getSubtotalProperty(): float
    {
        $sum = 0.0;
        foreach ($this->lines as $line) {
            $sum += (float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0);
        }
        return round($sum, 2);
    }

    public function getTotalAmountProperty(): float
    {
        return round(max(0, $this->subtotal - (float) $this->discount), 2);
    }

    public function getImponibileProperty(): float
    {
        $rate = max(0.0, (float) $this->vatRate);
        return round($this->totalAmount / (1 + $rate / 100), 2);
    }

    public function getTaxProperty(): float
    {
        return round($this->totalAmount - $this->imponibile, 2);
    }

    // ────────────────────── Step navigation ────────────────────────────────
    public function goToStep(int $step): void
    {
        // Forward navigation requires validation; backwards is always allowed.
        if ($step > $this->step) {
            if ($this->step === 1) $this->validateCustomerStep();
            if ($this->step === 2) $this->validateLinesStep();
        }
        $this->step = max(1, min(3, $step));
    }

    public function nextStep(): void
    {
        $this->goToStep($this->step + 1);
    }

    public function previousStep(): void
    {
        $this->goToStep($this->step - 1);
    }

    private function validateCustomerStep(): void
    {
        $this->validate([
            'userType'  => 'required|in:private,company,public_company',
            'fullName'  => 'required|string|max:255',
            'fiscalCode'=> 'nullable|string|max:50',
            'vatNumber' => 'nullable|string|max:50',
            'address'   => 'nullable|string|max:255',
            'zipCode'   => 'nullable|string|max:10',
            'city'      => 'nullable|string|max:100',
            'province'  => 'nullable|string|max:5',
            'codiceDestinatario' => 'nullable|string|max:7',
            'pecDestinatario'    => 'nullable|email|max:255',
        ], [], [
            'userType'  => 'tipo soggetto',
            'fullName'  => 'ragione sociale / nome',
            'fiscalCode'=> 'codice fiscale',
            'vatNumber' => 'partita IVA',
            'zipCode'   => 'CAP',
            'codiceDestinatario' => 'codice destinatario',
            'pecDestinatario'    => 'PEC destinatario',
        ]);

        // SDI requires at least one identifier
        if ($this->fiscalCode === '' && $this->vatNumber === '') {
            $this->addError('fiscalCode', 'Indicare codice fiscale o partita IVA.');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fiscalCode' => 'Indicare codice fiscale o partita IVA.',
            ]);
        }
    }

    private function validateLinesStep(): void
    {
        if (count($this->lines) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => 'Aggiungere almeno una riga.',
            ]);
        }

        foreach ($this->lines as $i => $line) {
            $label = trim((string) ($line['label'] ?? ''));
            $qty   = (float) ($line['quantity'] ?? 0);
            $price = (float) ($line['unit_price'] ?? 0);

            if ($label === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.$i.label" => "Riga " . ($i + 1) . ": descrizione obbligatoria.",
                ]);
            }
            if ($qty <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.$i.quantity" => "Riga " . ($i + 1) . ": quantità deve essere > 0.",
                ]);
            }
            if ($price < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.$i.unit_price" => "Riga " . ($i + 1) . ": prezzo non valido.",
                ]);
            }
        }

        if ($this->vatRate < 0 || $this->vatRate > 30) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'vatRate' => 'Aliquota IVA non valida.',
            ]);
        }

        if ($this->totalAmount <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => 'Il totale fattura deve essere maggiore di zero.',
            ]);
        }
    }

    // ────────────────────── Submission ─────────────────────────────────────
    public function submit(): void
    {
        $this->validateCustomerStep();
        $this->validateLinesStep();

        $this->submitting = true;

        $invoice = null;

        try {
            // ── Fase 1: persistenza atomica di customer + fattura ─────────────
            // Customer e fattura devono essere salvati anche se la successiva
            // generazione XML / invio a MySond fallisce.
            $invoice = DB::transaction(function () {
                // 1) Resolve/persist customer
                if ($this->selectedCustomerId) {
                    $customer = Customer::find($this->selectedCustomerId);
                    if (!$customer) {
                        throw new \RuntimeException('Cliente selezionato non più presente.');
                    }
                    if ($this->persistCustomerChanges) {
                        $customer->update($this->customerPayload());
                    } else {
                        // Use form values without persisting them
                        $customer->fill($this->customerPayload());
                    }
                } else {
                    $customer = Customer::create($this->customerPayload());
                }

                // 2) Generate invoice code & progressivo
                $counter     = (int) Setting::get('invoice_counter', 0) + 1;
                Setting::set('invoice_counter', $counter, 'integer');
                $year        = now()->format('Y');
                $invoiceCode = 'ORD-' . $year . '-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
                $invoiceName = TableOrderInvoice::toAlphanumeric($counter);

                // 3) Persist invoice (no table_order_id, multi-line lines payload)
                $persistedLines = array_map(function ($line) {
                    return [
                        'label'      => (string) ($line['label'] ?? ''),
                        'quantity'   => (float) ($line['quantity'] ?? 0),
                        'unit_price' => (float) ($line['unit_price'] ?? 0),
                        'vat_rate'   => (float) $this->vatRate,
                        'dish_id'    => isset($line['dish_id']) ? (int) $line['dish_id'] : null,
                    ];
                }, $this->lines);

                $invoice = TableOrderInvoice::create([
                    'table_order_id'  => null,
                    'customer_id'     => $customer->id,
                    'invoice_code'    => $invoiceCode,
                    'invoice_name'    => $invoiceName,
                    'amount'          => $this->totalAmount,
                    'discount'        => (float) $this->discount,
                    'tax'             => $this->tax,
                    'description'     => $this->description !== '' ? $this->description : null,
                    'lines'           => $persistedLines,
                    'payment_method'  => $this->paymentMethod,
                    'status'          => 'pending',
                ]);

                // Attach in-memory customer so XML factory has access immediately
                $invoice->setRelation('customer', $customer);

                return $invoice;
            });
        } catch (\Throwable $e) {
            Log::error('QuickInvoiceWizard submit error', [
                'error' => $e->getMessage(),
            ]);
            $this->result = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
            $this->step = 3;
            $this->submitting = false;
            return;
        }

        // ── Fase 2: generazione XML e dispatch invio a MySond ─────────────────
        // Eventuali errori non devono annullare il salvataggio della fattura:
        // la marchiamo come `error` con il messaggio e l'utente potrà ritentare.
        $xmlError = null;
        try {
            $mySond = app(MysondFatturaService::class);
            $xmlResult = $mySond->createInvoice($invoice);

            InvoiceMysondLog::logCreateInvoice($invoice->id, $xmlResult);

            $update = [
                'mysond_response' => is_array($xmlResult) ? json_encode($xmlResult) : (string) $xmlResult,
            ];
            if (($xmlResult['response'] ?? '') === 'success') {
                $update['xml_path']    = $xmlResult['path'] ?? null;
                $update['xml_content'] = $xmlResult['content'] ?? null;
            } else {
                $update['status'] = 'error';
                $xmlError = $xmlResult['message'] ?? 'sconosciuto';
            }
            $invoice->update($update);

            if (($xmlResult['response'] ?? '') === 'success') {
                \App\Jobs\SendInvoiceToMysondJob::dispatch($invoice->id);
            }
        } catch (\Throwable $e) {
            Log::error('QuickInvoiceWizard XML generation error', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            InvoiceMysondLog::logCreateInvoice($invoice->id, null, $e);
            $xmlError = $e->getMessage();
            $invoice->update([
                'status'          => 'error',
                'mysond_response' => json_encode([
                    'response' => 'error',
                    'message'  => $xmlError,
                ]),
            ]);
        }

        $this->result = [
            'success'      => $xmlError === null,
            'invoice_id'   => $invoice->id,
            'invoice_code' => $invoice->invoice_code,
            'invoice_name' => $invoice->invoice_name,
            'message'      => $xmlError === null
                ? 'Fattura generata e accodata per invio a MySond.'
                : 'Fattura salvata ma errore nella generazione XML: ' . $xmlError,
        ];
        $this->step = 3;
        $this->submitting = false;
    }

    private function customerPayload(): array
    {
        return [
            'user_type'           => $this->userType,
            'full_name'           => $this->fullName,
            'fiscal_code'         => $this->fiscalCode !== '' ? $this->fiscalCode : null,
            'vat_number'          => $this->vatNumber !== '' ? $this->vatNumber : null,
            'address'             => $this->address !== '' ? $this->address : null,
            'zip_code'            => $this->zipCode !== '' ? $this->zipCode : null,
            'city'                => $this->city !== '' ? $this->city : null,
            'province'            => $this->province !== '' ? $this->province : null,
            'codice_destinatario' => $this->codiceDestinatario !== '' ? $this->codiceDestinatario : null,
            'pec_destinatario'    => $this->pecDestinatario !== '' ? $this->pecDestinatario : null,
        ];
    }

    public function resetWizard(): void
    {
        $this->reset([
            'step', 'customerSearch', 'customerSearchResults', 'selectedCustomerId',
            'storedCustomerSnapshot', 'userType', 'fullName', 'fiscalCode', 'vatNumber',
            'address', 'zipCode', 'city', 'province', 'codiceDestinatario',
            'pecDestinatario', 'persistCustomerChanges',
            'dishSearch', 'dishSearchResults', 'lines',
            'discount', 'description', 'paymentMethod', 'result', 'submitting',
        ]);
        $this->vatRate = (float) Setting::get('invoice_vat_rate', 10);
    }

    public function render()
    {
        return view('livewire.quick-invoice-wizard');
    }
}
