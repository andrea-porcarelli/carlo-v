<?php

namespace App\Livewire;

use App\Helpers\ItalianFiscalHelper;
use App\Helpers\VatHelper;
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

    // ── Edit mode ──────────────────────────────────────────────────────────
    public ?int $invoiceId = null;
    public string $invoiceCode = '';

    // ── Nota di credito (TD04) ─────────────────────────────────────────────
    // documentType = TD01 (Fattura, default) | TD04 (Nota di credito).
    // parentInvoiceId → fattura interna di riferimento (nullable).
    // parentExternalRef → riferimento a fattura esterna (mirrored o manuale):
    //   array {code, date, total?, mirrored_invoice_id?}. Nullable.
    // parentSummary → stringa human-readable per il banner in UI (nullable).
    public string $documentType = TableOrderInvoice::DOCUMENT_TYPE_INVOICE;
    public ?int $parentInvoiceId = null;
    public ?array $parentExternalRef = null;
    public ?string $parentSummary = null;

    // ── Step 1 – Customer ──────────────────────────────────────────────────
    public string $customerSearch = '';
    public array $customerSearchResults = [];
    public ?int $selectedCustomerId = null;
    public ?array $storedCustomerSnapshot = null; // original DB values for diff display

    public string $userType = 'private';
    public string $country = 'IT';
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

    public function mount(
        ?int $invoiceId = null,
        ?string $documentType = null,
        ?int $parentInvoiceId = null,
        ?array $parentExternalRef = null,
        ?string $parentSummary = null,
        ?array $prefillCustomer = null,
        ?array $prefillLines = null,
    ): void {
        $this->vatRate = (float) Setting::get('invoice_vat_rate', 10);

        if ($invoiceId !== null) {
            $this->loadInvoiceForEdit($invoiceId);
            return;
        }

        if ($documentType !== null) {
            $this->documentType = $documentType;
        }
        $this->parentInvoiceId   = $parentInvoiceId;
        $this->parentExternalRef = $parentExternalRef;
        $this->parentSummary     = $parentSummary;

        if (is_array($prefillCustomer)) {
            if (!empty($prefillCustomer['id'])) {
                $this->selectCustomer((int) $prefillCustomer['id']);
            } elseif (!empty($prefillCustomer['data']) && is_array($prefillCustomer['data'])) {
                // Nota di credito da fattura esterna: nessun Customer locale
                // matching, popoliamo direttamente i campi del wizard con
                // l'anagrafica estratta dal CessionarioCommittente dell'XML.
                // Il submit creerà un nuovo Customer con questi dati.
                $this->applyPrefillCustomerData($prefillCustomer['data']);
            }
        }

        if (is_array($prefillLines) && count($prefillLines) > 0) {
            $this->lines = array_map(fn ($l) => [
                'label'      => (string) ($l['label'] ?? ''),
                'quantity'   => (float) ($l['quantity'] ?? 1),
                'unit_price' => (float) ($l['unit_price'] ?? 0),
                'dish_id'    => isset($l['dish_id']) ? (int) $l['dish_id'] : null,
            ], $prefillLines);

            // Se la sorgente ha vat_rate esplicito e coerente su tutte le righe,
            // adottalo. Altrimenti manteniamo il default da Setting.
            $vatRates = collect($prefillLines)->pluck('vat_rate')->filter(fn ($v) => $v !== null)->unique();
            if ($vatRates->count() === 1) {
                $this->vatRate = (float) $vatRates->first();
            }
        }
    }

    public function isEditMode(): bool
    {
        return $this->invoiceId !== null;
    }

    public function isCreditNote(): bool
    {
        return $this->documentType === TableOrderInvoice::DOCUMENT_TYPE_CREDIT_NOTE;
    }

    private function loadInvoiceForEdit(int $invoiceId): void
    {
        $invoice = TableOrderInvoice::with('customer')->findOrFail($invoiceId);
        if (!$invoice->isEditable()) {
            abort(403, 'Fattura non modificabile.');
        }

        $this->invoiceId   = $invoice->id;
        $this->invoiceCode = (string) $invoice->invoice_code;

        $customer = $invoice->customer;
        if ($customer) {
            $this->selectCustomer($customer->id);
        }

        $this->discount       = (float) $invoice->discount;
        $this->description    = (string) ($invoice->description ?? '');
        $this->paymentMethod  = (string) ($invoice->payment_method ?? 'bonifico');

        $persistedLines = is_array($invoice->lines) ? $invoice->lines : [];
        $this->lines = array_map(function ($line) {
            return [
                'label'      => (string) ($line['label'] ?? ''),
                'quantity'   => (float) ($line['quantity'] ?? 0),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'dish_id'    => isset($line['dish_id']) ? (int) $line['dish_id'] : null,
            ];
        }, $persistedLines);

        if (!empty($persistedLines) && isset($persistedLines[0]['vat_rate'])) {
            $this->vatRate = (float) $persistedLines[0]['vat_rate'];
        }
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
            'user_type', 'country', 'full_name', 'fiscal_code', 'vat_number',
            'address', 'zip_code', 'city', 'province',
            'codice_destinatario', 'pec_destinatario',
        ]);

        $this->userType            = $customer->user_type ?? 'private';
        $this->country             = (string) ($customer->country ?? 'IT');
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

    private function applyPrefillCustomerData(array $data): void
    {
        $this->selectedCustomerId     = null;
        $this->storedCustomerSnapshot = null;
        $this->userType               = (string) ($data['user_type'] ?? 'private');
        $this->country                = (string) ($data['country'] ?? 'IT');
        $this->fullName               = (string) ($data['full_name'] ?? '');
        $this->fiscalCode             = (string) ($data['fiscal_code'] ?? '');
        $this->vatNumber              = (string) ($data['vat_number'] ?? '');
        $this->address                = (string) ($data['address'] ?? '');
        $this->zipCode                = (string) ($data['zip_code'] ?? '');
        $this->city                   = (string) ($data['city'] ?? '');
        $this->province               = (string) ($data['province'] ?? '');
        $this->codiceDestinatario     = (string) ($data['codice_destinatario'] ?? '');
        $this->pecDestinatario        = (string) ($data['pec_destinatario'] ?? '');
    }

    public function clearCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->storedCustomerSnapshot = null;
        $this->userType = 'private';
        $this->country  = 'IT';
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
            'country'             => $this->country,
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

    // ────────────────────── Normalizzazione live campi ────────────────────
    // Le sigle provincia sui documenti fiscali sono sempre in maiuscolo (SDI
    // richiede 2 lettere maiuscole). Normalizziamo mentre l'operatore scrive.
    public function updatedProvince(): void
    {
        $this->province = strtoupper(trim($this->province));
    }

    public function updatedFiscalCode(): void
    {
        $this->fiscalCode = strtoupper(trim($this->fiscalCode));
    }

    public function updatedCodiceDestinatario(): void
    {
        $this->codiceDestinatario = strtoupper(trim($this->codiceDestinatario));
    }

    public function updatedVatNumber(): void
    {
        // Rimuoviamo prefisso "IT" e caratteri non numerici per uniformare la
        // digitazione (SDI vuole solo le 11 cifre in IdCodice).
        $this->vatNumber = VatHelper::sanitize($this->vatNumber);
    }

    public function updatedUserType(): void
    {
        // Cambio di tipo soggetto → azzeriamo il codice destinatario se non è
        // coerente con la nuova casistica, così l'operatore vede subito quale
        // valore inserire (0000000 privato/azienda, IPA 6 char per PA,
        // XXXXXXX per soggetto estero).
        $len = strlen($this->codiceDestinatario);
        if ($this->userType === 'public_company' && $len !== 6) {
            $this->codiceDestinatario = '';
        }
        if ($this->userType !== 'public_company' && $len !== 0 && $len !== 7) {
            $this->codiceDestinatario = '';
        }

        // Nazione + provincia devono riflettere il nuovo tipo:
        //  - foreign → forza country vuoto perché operatore scelga la nazione,
        //    e imposta provincia "EE" (convenzione SDI per esteri).
        //  - torna a tipo italiano → riporta country a "IT" e sblocca provincia.
        if ($this->userType === 'foreign') {
            if ($this->country === 'IT') {
                $this->country = '';
            }
            $this->province = 'EE';
        } else {
            $this->country = 'IT';
            if ($this->province === 'EE') {
                $this->province = '';
            }
        }
    }

    public function updatedCountry(): void
    {
        $this->country = strtoupper(trim($this->country));
    }

    /**
     * Applica le regole standard italiane per la Fattura Elettronica (SDI):
     *   • Privato    → nome+cognome, codice fiscale (16 alfanumerici), indirizzo completo
     *   • Azienda    → ragione sociale, P.IVA (11 cifre), sede completa,
     *                  codice destinatario (7 char) OPPURE PEC
     *   • Pubblica A. → ragione sociale, P.IVA, sede completa,
     *                  codice destinatario IPA (6 char) obbligatorio
     * Provincia: sempre 2 lettere maiuscole appartenenti alle sigle ISTAT (+"EE"
     * per estero); CAP: 5 cifre.
     */
    private function validateCustomerStep(): void
    {
        $this->validate([
            'userType'  => 'required|in:private,company,public_company,non_profit_entity,sole_trader,foreign',
            'country'   => 'required|string|size:2',
            'fullName'  => 'required|string|max:255',
            'fiscalCode'=> 'nullable|string|max:16',
            'vatNumber' => 'nullable|string|max:20',
            'address'   => 'nullable|string|max:255',
            'zipCode'   => 'nullable|string|max:10',
            'city'      => 'nullable|string|max:100',
            'province'  => 'nullable|string|size:2',
            'codiceDestinatario' => 'nullable|string|max:7',
            'pecDestinatario'    => 'nullable|email|max:255',
        ], [
            'province.size' => 'La provincia deve essere di 2 lettere (sigla, es. CA, MI, RM).',
            'country.size'  => 'La nazione deve essere il codice ISO a 2 lettere (es. IT, FR, DE).',
        ], [
            'userType'  => 'tipo soggetto',
            'country'   => 'nazione',
            'fullName'  => 'ragione sociale / nome',
            'fiscalCode'=> 'codice fiscale',
            'vatNumber' => 'partita IVA',
            'zipCode'   => 'CAP',
            'codiceDestinatario' => 'codice destinatario',
            'pecDestinatario'    => 'PEC destinatario',
        ]);

        $errors = [];

        $isForeign = $this->userType === 'foreign';

        // Nazione: soggetti italiani = IT; soggetti esteri = qualunque ISO ≠ IT.
        if (!$isForeign && strtoupper($this->country) !== 'IT') {
            $errors['country'] = 'Per soggetti italiani la nazione deve essere "IT". Se il cliente è estero, seleziona "Soggetto Estero" come tipo.';
        }
        if ($isForeign && strtoupper($this->country) === 'IT') {
            $errors['country'] = 'Per un Soggetto Estero la nazione deve essere diversa da "IT".';
        }

        // Provincia: per soggetti italiani deve essere una sigla ISTAT valida
        // (o "EE" se per qualche motivo si tratta di sede estera con anagrafica
        // italiana). Per soggetti esteri accettiamo "EE" convenzionale.
        if ($this->province !== '' && !ItalianFiscalHelper::isValidProvince($this->province)) {
            $errors['province'] = 'Sigla provincia non valida. Usare una sigla ISTAT di 2 lettere (es. CA, MI, RM; "EE" per estero).';
        }

        // CAP: per soggetti italiani deve essere 5 cifre; per esteri accettiamo
        // formati stranieri (Regno Unito, Canada, ...), max 10 caratteri.
        if (!$isForeign && $this->zipCode !== '' && !ItalianFiscalHelper::isValidCap($this->zipCode)) {
            $errors['zipCode'] = 'Il CAP deve essere composto da 5 cifre.';
        }

        // Almeno un identificativo (CF o P.IVA) è sempre richiesto tranne per
        // il soggetto estero, dove la P.IVA italiana non è applicabile e il CF
        // può essere un identificativo fiscale estero non standard.
        if (!$isForeign && $this->fiscalCode === '' && $this->vatNumber === '') {
            $errors['fiscalCode'] = 'Indicare codice fiscale o partita IVA.';
        }

        // P.IVA italiana: validazione checksum solo per soggetti italiani.
        if (!$isForeign && $this->vatNumber !== '' && !ItalianFiscalHelper::isValidVatNumber($this->vatNumber)) {
            $errors['vatNumber'] = 'Partita IVA non valida (devono essere 11 cifre con checksum corretto).';
        }

        // PEC: campo aggiuntivo di validazione (Laravel valida già il formato email).
        // In caso di privato / azienda con codice destinatario "0000000", SDI
        // richiede la PEC per il recapito.

        // Regole per tipo soggetto ---------------------------------------------
        if ($this->userType === 'private') {
            // Privato → CF alfanumerico 16 caratteri (persona fisica)
            if ($this->fiscalCode !== '' && !ItalianFiscalHelper::isValidPersonalFiscalCode($this->fiscalCode)) {
                $errors['fiscalCode'] = 'Codice fiscale non valido: attesi 16 caratteri alfanumerici (persona fisica).';
            }
            // Sede completa consigliata: SDI accetta anche senza indirizzo per
            // privati, ma il rifiuto è frequente. Segnaliamo come warning solo
            // nella view.
            if ($this->codiceDestinatario !== '' && strlen($this->codiceDestinatario) !== 7) {
                $errors['codiceDestinatario'] = 'Codice destinatario privato: 7 caratteri (usare "0000000" se non fornito).';
            }
        }

        if ($this->userType === 'company') {
            // Azienda → P.IVA obbligatoria
            if ($this->vatNumber === '') {
                $errors['vatNumber'] = 'La partita IVA è obbligatoria per le aziende.';
            }
            // Se anche il CF è compilato per una società, deve essere numerico
            // di 11 cifre (persona giuridica) oppure 16 caratteri (ditta indiv.).
            if ($this->fiscalCode !== ''
                && !ItalianFiscalHelper::isValidLegalFiscalCode($this->fiscalCode)
                && !ItalianFiscalHelper::isValidPersonalFiscalCode($this->fiscalCode)
            ) {
                $errors['fiscalCode'] = 'Codice fiscale non valido: attesi 11 cifre (società) o 16 caratteri (ditta individuale).';
            }
            // Sede legale completa: SDI la richiede per le persone giuridiche.
            $sedeFields = [
                'address'  => ['label' => 'Indirizzo sede', 'article' => 'o'],
                'zipCode'  => ['label' => 'CAP',            'article' => 'o'],
                'city'     => ['label' => 'Comune',         'article' => 'o'],
                'province' => ['label' => 'Provincia',      'article' => 'a'],
            ];
            foreach ($sedeFields as $field => $meta) {
                if ($this->{$field} === '') {
                    $errors[$field] = $meta['label'] . ' obbligatori' . $meta['article'] . " per l'anagrafica azienda.";
                }
            }
            // Codice destinatario: 7 caratteri; "0000000" ammesso ma solo se
            // viene fornita la PEC per il recapito.
            if ($this->codiceDestinatario === '') {
                $errors['codiceDestinatario'] = 'Codice destinatario obbligatorio (7 caratteri). Usare "0000000" se il cliente non lo fornisce, indicando anche la PEC.';
            } elseif (strlen($this->codiceDestinatario) !== 7) {
                $errors['codiceDestinatario'] = 'Codice destinatario azienda: esattamente 7 caratteri alfanumerici.';
            } elseif ($this->codiceDestinatario === '0000000' && $this->pecDestinatario === '') {
                $errors['pecDestinatario'] = 'Con codice destinatario "0000000" è necessario indicare la PEC del cliente per il recapito SDI.';
            }
        }

        if ($this->userType === 'public_company') {
            // PA → codice IPA di 6 caratteri
            if ($this->vatNumber === '') {
                $errors['vatNumber'] = 'La partita IVA è obbligatoria per la Pubblica Amministrazione.';
            }
            if ($this->codiceDestinatario === '') {
                $errors['codiceDestinatario'] = 'Il codice IPA (6 caratteri) è obbligatorio per la Pubblica Amministrazione.';
            } elseif (strlen($this->codiceDestinatario) !== 6) {
                $errors['codiceDestinatario'] = 'Il codice IPA della Pubblica Amministrazione è di esattamente 6 caratteri.';
            }
            $sedeFieldsPA = [
                'address'  => ['label' => 'Indirizzo sede', 'article' => 'o'],
                'zipCode'  => ['label' => 'CAP',            'article' => 'o'],
                'city'     => ['label' => 'Comune',         'article' => 'o'],
                'province' => ['label' => 'Provincia',      'article' => 'a'],
            ];
            foreach ($sedeFieldsPA as $field => $meta) {
                if ($this->{$field} === '') {
                    $errors[$field] = $meta['label'] . ' obbligatori' . $meta['article'] . " per l'anagrafica PA.";
                }
            }
        }

        if ($this->userType === 'non_profit_entity') {
            // Ente non commerciale (associazioni, ETS, fondazioni, ONLUS):
            // Codice fiscale obbligatorio; ammesso 11 cifre O 16 alfanumerici
            // (retaggio storico). P.IVA opzionale (solo se ente commerciale).
            if ($this->fiscalCode === '') {
                $errors['fiscalCode'] = 'Il codice fiscale è obbligatorio per gli enti non commerciali.';
            } elseif (!ItalianFiscalHelper::isValidEntityFiscalCode($this->fiscalCode)) {
                $errors['fiscalCode'] = 'Codice fiscale non valido: attesi 11 cifre (con checksum) oppure 16 caratteri alfanumerici.';
            }
            $sedeFieldsEnte = [
                'address'  => ['label' => 'Indirizzo sede', 'article' => 'o'],
                'zipCode'  => ['label' => 'CAP',            'article' => 'o'],
                'city'     => ['label' => 'Comune',         'article' => 'o'],
                'province' => ['label' => 'Provincia',      'article' => 'a'],
            ];
            foreach ($sedeFieldsEnte as $field => $meta) {
                if ($this->{$field} === '') {
                    $errors[$field] = $meta['label'] . ' obbligatori' . $meta['article'] . " per l'anagrafica ente.";
                }
            }
            if ($this->codiceDestinatario === '') {
                $errors['codiceDestinatario'] = 'Codice destinatario obbligatorio (7 caratteri). Usare "0000000" se l\'ente non lo fornisce, indicando anche la PEC.';
            } elseif (strlen($this->codiceDestinatario) !== 7) {
                $errors['codiceDestinatario'] = 'Codice destinatario ente: esattamente 7 caratteri alfanumerici.';
            } elseif ($this->codiceDestinatario === '0000000' && $this->pecDestinatario === '') {
                $errors['pecDestinatario'] = 'Con codice destinatario "0000000" è necessario indicare la PEC dell\'ente per il recapito SDI.';
            }
        }

        if ($this->userType === 'sole_trader') {
            // Ditta individuale / libero professionista: persona fisica con
            // P.IVA. Servono CF personale (16 caratteri) E P.IVA (11 cifre).
            if ($this->fiscalCode === '') {
                $errors['fiscalCode'] = 'Il codice fiscale personale è obbligatorio per ditte individuali e liberi professionisti.';
            } elseif (!ItalianFiscalHelper::isValidPersonalFiscalCode($this->fiscalCode)) {
                $errors['fiscalCode'] = 'Codice fiscale non valido: attesi 16 caratteri alfanumerici (persona fisica).';
            }
            if ($this->vatNumber === '') {
                $errors['vatNumber'] = 'La partita IVA è obbligatoria per ditte individuali e liberi professionisti.';
            }
            $sedeFieldsSole = [
                'address'  => ['label' => 'Indirizzo sede attività', 'article' => 'o'],
                'zipCode'  => ['label' => 'CAP',                     'article' => 'o'],
                'city'     => ['label' => 'Comune',                  'article' => 'o'],
                'province' => ['label' => 'Provincia',               'article' => 'a'],
            ];
            foreach ($sedeFieldsSole as $field => $meta) {
                if ($this->{$field} === '') {
                    $errors[$field] = $meta['label'] . ' obbligatori' . $meta['article'] . " per l'anagrafica ditta individuale.";
                }
            }
            if ($this->codiceDestinatario === '') {
                $errors['codiceDestinatario'] = 'Codice destinatario obbligatorio (7 caratteri). Usare "0000000" se non fornito, indicando anche la PEC.';
            } elseif (strlen($this->codiceDestinatario) !== 7) {
                $errors['codiceDestinatario'] = 'Codice destinatario: esattamente 7 caratteri alfanumerici.';
            } elseif ($this->codiceDestinatario === '0000000' && $this->pecDestinatario === '') {
                $errors['pecDestinatario'] = 'Con codice destinatario "0000000" è necessario indicare la PEC per il recapito SDI.';
            }
        }

        if ($this->userType === 'foreign') {
            // Soggetto estero (non residente): SDI accetta CF/P.IVA non italiani
            // in <IdCodice>; convenzionalmente <CodiceDestinatario>=XXXXXXX.
            // Sede: indirizzo + città obbligatori; provincia forzata a "EE";
            // CAP libero per adattarsi ai formati esteri.
            if ($this->codiceDestinatario === '') {
                $errors['codiceDestinatario'] = 'Per il Soggetto Estero il codice destinatario è convenzionalmente "XXXXXXX".';
            } elseif (strlen($this->codiceDestinatario) !== 7) {
                $errors['codiceDestinatario'] = 'Codice destinatario estero: 7 caratteri (usare "XXXXXXX").';
            }
            if ($this->address === '') {
                $errors['address'] = 'Indirizzo sede obbligatorio per l\'anagrafica estera.';
            }
            if ($this->city === '') {
                $errors['city'] = 'Comune / città obbligatori per l\'anagrafica estera.';
            }
        }

        if (!empty($errors)) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
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
        if ($this->isEditMode()) {
            $this->validateInvoiceCode();
        }

        $this->submitting = true;

        $invoice = null;

        // Pre-emissione: sync mirror MySond (allinea contatore + scartate SDI)
        // e blocca se ci sono rifiuti non riconosciuti. Saltato in edit mode:
        // stiamo proprio rettificando una scartata.
        if (!$this->isEditMode()) {
            try {
                app(\App\Services\MysondInvoiceMirror::class)->runOrThrow();
            } catch (\App\Exceptions\PendingSdiRejectionsException $e) {
                $this->result = [
                    'success' => false,
                    'code'    => 'sdi_rejections_pending',
                    'message' => sprintf(
                        'Emissione bloccata: %d scartata/e SDI da riconoscere prima di emettere nuove fatture. Vai a Backoffice → SDI scartate.',
                        $e->rejections->count()
                    ),
                    'rejections' => $e->rejections->map(fn ($r) => [
                        'file_name'   => $r->file_name,
                        'mysond_code' => $r->mysond_code,
                        'stato_label' => $r->stato_label,
                    ])->values()->all(),
                ];
                $this->step = 3;
                $this->submitting = false;
                return;
            }
        }

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

                $persistedLines = array_map(function ($line) {
                    return [
                        'label'      => (string) ($line['label'] ?? ''),
                        'quantity'   => (float) ($line['quantity'] ?? 0),
                        'unit_price' => (float) ($line['unit_price'] ?? 0),
                        'vat_rate'   => (float) $this->vatRate,
                        'dish_id'    => isset($line['dish_id']) ? (int) $line['dish_id'] : null,
                    ];
                }, $this->lines);

                if ($this->isEditMode()) {
                    $invoice = TableOrderInvoice::findOrFail($this->invoiceId);
                    if (!$invoice->isEditable()) {
                        throw new \RuntimeException('Fattura non più modificabile.');
                    }
                    $invoice->update([
                        'customer_id'     => $customer->id,
                        'invoice_code'    => trim($this->invoiceCode),
                        'amount'          => $this->totalAmount,
                        'discount'        => (float) $this->discount,
                        'tax'             => $this->tax,
                        'description'     => $this->description !== '' ? $this->description : null,
                        'lines'           => $persistedLines,
                        'payment_method'  => $this->paymentMethod,
                        'status'          => 'pending',
                        'sdi_status'      => null,
                        'sdi_status_label'=> null,
                        'sdi_checked_at'  => null,
                        'sdi_response'    => null,
                        'mysond_response' => null,
                        'xml_content'     => null,
                        'sent_at'         => null,
                    ]);
                } else {
                    $counter     = (int) Setting::get('invoice_counter', 0) + 1;
                    Setting::set('invoice_counter', $counter, 'integer');
                    $year        = now()->format('Y');
                    $invoiceCode = $year . '-' . str_pad((string) $counter, 5, '0', STR_PAD_LEFT);
                    $invoiceName = TableOrderInvoice::toAlphanumeric($counter);

                    $invoice = TableOrderInvoice::create([
                        'table_order_id'      => null,
                        'customer_id'         => $customer->id,
                        'invoice_code'        => $invoiceCode,
                        'invoice_name'        => $invoiceName,
                        'document_type'       => $this->documentType,
                        'parent_invoice_id'   => $this->parentInvoiceId,
                        'parent_external_ref' => $this->parentExternalRef,
                        'amount'              => $this->totalAmount,
                        'discount'            => (float) $this->discount,
                        'tax'                 => $this->tax,
                        'description'         => $this->description !== '' ? $this->description : null,
                        'lines'               => $persistedLines,
                        'payment_method'      => $this->paymentMethod,
                        'status'              => 'pending',
                    ]);
                }

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

    private function validateInvoiceCode(): void
    {
        $code = trim($this->invoiceCode);
        if ($code === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'invoiceCode' => 'Il numero fattura è obbligatorio.',
            ]);
        }
        $exists = TableOrderInvoice::where('invoice_code', $code)
            ->where('id', '!=', $this->invoiceId)
            ->exists();
        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'invoiceCode' => 'Numero fattura già usato da un\'altra fattura.',
            ]);
        }
    }

    private function customerPayload(): array
    {
        return [
            'user_type'           => $this->userType,
            'country'             => $this->country !== '' ? strtoupper($this->country) : 'IT',
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
            'storedCustomerSnapshot', 'userType', 'country', 'fullName', 'fiscalCode', 'vatNumber',
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
