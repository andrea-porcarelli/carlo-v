@php
    $isLocked = $step === 3 && $result;
    $userTypes = [
        'private'        => 'Privato',
        'company'        => 'Azienda',
        'public_company' => 'Pubblica Amministrazione',
    ];
    $changed = $this->changedCustomerFields ?? [];
    $provinces = \App\Helpers\ItalianFiscalHelper::PROVINCES;
    // Requisiti standard per l'emissione della Fattura Elettronica (SDI):
    // servono per guidare l'operatore mostrando in ogni step cosa è obbligatorio.
    $requirementsByType = [
        'private' => [
            'title'    => 'Privato (persona fisica)',
            'required' => 'Nome e cognome, Codice Fiscale (16 caratteri).',
            'notes'    => 'Codice destinatario suggerito: "0000000". Se il privato ha una PEC, indicarla per il recapito SDI.',
        ],
        'company' => [
            'title'    => 'Azienda / professionista (persona giuridica)',
            'required' => 'Ragione sociale, P.IVA (11 cifre), indirizzo sede completo (via, CAP, comune, provincia), Codice destinatario (7 caratteri) OPPURE PEC.',
            'notes'    => 'Se il cliente non ha SdI/PEC di recapito, inserire "0000000" come codice destinatario e obbligatoriamente la sua PEC.',
        ],
        'public_company' => [
            'title'    => 'Pubblica Amministrazione',
            'required' => 'Ragione sociale, P.IVA, indirizzo completo, Codice IPA (6 caratteri) obbligatorio.',
            'notes'    => 'Il codice IPA è recuperabile su indicepa.gov.it. La PA riceve solo tramite codice IPA, non via PEC.',
        ],
    ];
    $req = $requirementsByType[$userType] ?? $requirementsByType['private'];
@endphp

<div class="quick-invoice-wizard">

    {{-- ────────── Stepper ────────── --}}
    <div class="qiw-stepper">
        @foreach (['Cliente', 'Righe fattura', 'Riepilogo & invio'] as $idx => $label)
            @php $n = $idx + 1; @endphp
            <div class="qiw-step {{ $step === $n ? 'active' : '' }} {{ $step > $n ? 'done' : '' }}">
                <div class="qiw-step-num">
                    @if($step > $n)
                        <i class="fa fa-check"></i>
                    @else
                        {{ $n }}
                    @endif
                </div>
                <div class="qiw-step-label">{{ $label }}</div>
            </div>
            @if($n < 3)
                <div class="qiw-step-sep {{ $step > $n ? 'done' : '' }}"></div>
            @endif
        @endforeach
    </div>

    {{-- ═════════════════════════════════════════════════════ STEP 1 ═════════════════════════════════════════════════════ --}}
    @if($step === 1)
        <div class="qiw-card">
            <div class="qiw-card-header">
                <i class="fa fa-user-tag"></i> Step 1 — Cliente
            </div>
            <div class="qiw-card-body">

                {{-- Ricerca cliente esistente --}}
                <div class="form-group">
                    <label>Cerca cliente esistente <small class="text-muted">(nome, codice fiscale, P.IVA)</small></label>
                    <div style="position:relative;">
                        <div class="input-group">
                            <span class="input-group-addon" style="background:#fff;">
                                <i class="fa fa-search text-muted"></i>
                            </span>
                            <input type="text"
                                   class="form-control"
                                   placeholder="Digita almeno 2 caratteri..."
                                   wire:model.live.debounce.350ms="customerSearch"
                                   autocomplete="off">
                            @if($selectedCustomerId)
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" wire:click="clearCustomer" title="Annulla selezione cliente">
                                        <i class="fa fa-times"></i> Annulla
                                    </button>
                                </span>
                            @endif
                        </div>
                        @if(count($customerSearchResults) > 0)
                            <div class="qiw-search-dropdown">
                                @foreach($customerSearchResults as $c)
                                    <div class="qiw-search-item" wire:click="selectCustomer({{ $c['id'] }})">
                                        <div>
                                            <strong>{{ $c['full_name'] }}</strong>
                                            @if(!empty($c['city']))
                                                <small class="text-muted"> — {{ $c['city'] }}</small>
                                            @endif
                                        </div>
                                        <small class="text-muted">
                                            {{ $userTypes[$c['user_type']] ?? $c['user_type'] }}
                                            @if(!empty($c['fiscal_code'])) • CF: {{ $c['fiscal_code'] }} @endif
                                            @if(!empty($c['vat_number'])) • P.IVA: {{ $c['vat_number'] }} @endif
                                        </small>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                @if($selectedCustomerId)
                    <div class="alert alert-info" style="margin-top:8px;">
                        <i class="fa fa-info-circle"></i>
                        <strong>Cliente esistente selezionato (#{{ $selectedCustomerId }})</strong>.
                        Sono mostrati i dati attualmente in archivio: verifica/aggiorna i campi che potrebbero essere cambiati.
                        I campi modificati sono evidenziati in <span style="color:#ec971f;font-weight:600;">arancio</span>.
                    </div>
                @endif

                <hr style="margin: 18px 0;">

                {{-- Dati fiscali --}}
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Tipo soggetto *</label>
                        <select class="form-control" wire:model.live="userType">
                            @foreach($userTypes as $k => $lbl)
                                <option value="{{ $k }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                        @error('userType') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-8 form-group {{ $changed['full_name'] ?? false ? 'qiw-changed' : '' }}">
                        <label>
                            {{ $userType === 'private' ? 'Nome e cognome' : 'Ragione sociale' }} *
                            @if($changed['full_name'] ?? false) <span class="qiw-changed-tag">modificato</span> @endif
                        </label>
                        <input type="text" class="form-control" wire:model="fullName">
                        @error('fullName') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>

                {{-- Guida contestuale: cosa richiede SDI per questo tipo soggetto --}}
                <div class="qiw-guide">
                    <div class="qiw-guide-title">
                        <i class="fa fa-info-circle"></i> {{ $req['title'] }} — requisiti per la Fattura Elettronica
                    </div>
                    <div class="qiw-guide-body">
                        <div><strong>Obbligatori:</strong> {{ $req['required'] }}</div>
                        <div class="text-muted"><em>{{ $req['notes'] }}</em></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group {{ $changed['fiscal_code'] ?? false ? 'qiw-changed' : '' }}">
                        <label>
                            Codice fiscale
                            @if($userType === 'private') * @endif
                            @if($changed['fiscal_code'] ?? false) <span class="qiw-changed-tag">modificato</span> @endif
                        </label>
                        <input type="text" class="form-control"
                               wire:model.live.debounce.500ms="fiscalCode"
                               maxlength="16"
                               placeholder="{{ $userType === 'private' ? '16 caratteri, es. RSSMRA85M01H501U' : '11 cifre (=P.IVA) oppure 16 caratteri (ditta individuale)' }}"
                               style="text-transform:uppercase;">
                        <small class="text-muted">
                            @if($userType === 'private')
                                Persona fisica: 16 caratteri alfanumerici.
                            @else
                                Persona giuridica: coincide di norma con la P.IVA (11 cifre).
                            @endif
                        </small>
                        @error('fiscalCode') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-6 form-group {{ $changed['vat_number'] ?? false ? 'qiw-changed' : '' }}">
                        <label>
                            Partita IVA
                            @if($userType !== 'private') * @endif
                            @if($changed['vat_number'] ?? false) <span class="qiw-changed-tag">modificato</span> @endif
                        </label>
                        <input type="text" class="form-control"
                               wire:model.live.debounce.500ms="vatNumber"
                               maxlength="11"
                               inputmode="numeric"
                               placeholder="11 cifre (senza prefisso IT)">
                        <small class="text-muted">Solo cifre; l'eventuale prefisso "IT" viene rimosso in automatico.</small>
                        @error('vatNumber') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 form-group {{ $changed['address'] ?? false ? 'qiw-changed' : '' }}">
                        <label>
                            Indirizzo
                            @if($userType !== 'private') * @endif
                            @if($changed['address'] ?? false) <span class="qiw-changed-tag">modificato</span> @endif
                        </label>
                        <input type="text" class="form-control" wire:model="address" placeholder="Via / Piazza e numero civico">
                        @error('address') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-4 form-group {{ $changed['zip_code'] ?? false ? 'qiw-changed' : '' }}">
                        <label>
                            CAP
                            @if($userType !== 'private') * @endif
                            @if($changed['zip_code'] ?? false) <span class="qiw-changed-tag">modificato</span> @endif
                        </label>
                        <input type="text" class="form-control" wire:model="zipCode" maxlength="5" inputmode="numeric" placeholder="5 cifre">
                        @error('zipCode') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 form-group {{ $changed['city'] ?? false ? 'qiw-changed' : '' }}">
                        <label>
                            Comune
                            @if($userType !== 'private') * @endif
                            @if($changed['city'] ?? false) <span class="qiw-changed-tag">modificato</span> @endif
                        </label>
                        <input type="text" class="form-control" wire:model="city">
                        @error('city') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-4 form-group {{ $changed['province'] ?? false ? 'qiw-changed' : '' }}">
                        <label>
                            Provincia (sigla)
                            @if($userType !== 'private') * @endif
                            @if($changed['province'] ?? false) <span class="qiw-changed-tag">modificato</span> @endif
                        </label>
                        <input type="text" class="form-control"
                               wire:model.live.debounce.500ms="province"
                               maxlength="2"
                               list="qiw-province-list"
                               placeholder="es. CA"
                               style="text-transform:uppercase;">
                        <datalist id="qiw-province-list">
                            @foreach($provinces as $p)
                                <option value="{{ $p }}"></option>
                            @endforeach
                        </datalist>
                        <small class="text-muted">2 lettere (sigla ISTAT). Usare "EE" per soggetti esteri.</small>
                        @error('province') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group {{ $changed['codice_destinatario'] ?? false ? 'qiw-changed' : '' }}">
                        <label>
                            Codice destinatario SDI
                            @if($userType !== 'private') * @endif
                            @if($changed['codice_destinatario'] ?? false) <span class="qiw-changed-tag">modificato</span> @endif
                        </label>
                        <div class="input-group">
                            <input type="text" class="form-control"
                                   wire:model.live.debounce.500ms="codiceDestinatario"
                                   maxlength="{{ $userType === 'public_company' ? 6 : 7 }}"
                                   placeholder="{{ $userType === 'public_company' ? '6 caratteri (IPA)' : '7 caratteri (o 0000000)' }}"
                                   style="text-transform:uppercase;">
                            @if($userType !== 'public_company')
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default"
                                            wire:click="$set('codiceDestinatario', '0000000')"
                                            title="Imposta 0000000 (default per privati e clienti senza SdI)">
                                        0000000
                                    </button>
                                </span>
                            @endif
                        </div>
                        <small class="text-muted">
                            @if($userType === 'public_company')
                                Codice IPA a 6 caratteri (recuperabile su indicepa.gov.it).
                            @else
                                7 caratteri alfanumerici. Usare "0000000" se il cliente non ha SdI/PEC di recapito — in tal caso è richiesta la PEC.
                            @endif
                        </small>
                        @error('codiceDestinatario') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-8 form-group {{ $changed['pec_destinatario'] ?? false ? 'qiw-changed' : '' }}">
                        <label>
                            PEC destinatario
                            @if($userType === 'company' && $codiceDestinatario === '0000000') * @endif
                            @if($changed['pec_destinatario'] ?? false) <span class="qiw-changed-tag">modificato</span> @endif
                        </label>
                        <input type="email" class="form-control" wire:model="pecDestinatario" placeholder="esempio@pec.it">
                        <small class="text-muted">
                            @if($userType === 'public_company')
                                Non richiesta: la PA riceve solo tramite codice IPA.
                            @else
                                Obbligatoria se il codice destinatario è "0000000".
                            @endif
                        </small>
                        @error('pecDestinatario') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                </div>

                @if($selectedCustomerId && count($changed) > 0)
                    <div class="checkbox" style="margin-top:6px;">
                        <label>
                            <input type="checkbox" wire:model="persistCustomerChanges">
                            <strong>Salva queste modifiche sul cliente in archivio</strong>
                            <small class="text-muted">(disabilita se vuoi usare i nuovi dati solo per questa fattura)</small>
                        </label>
                    </div>
                @endif

            </div>
            <div class="qiw-card-footer">
                <a href="{{ route('accounting.invoices.index') }}" class="btn btn-default">
                    <i class="fa fa-times"></i> Annulla
                </a>
                <button type="button" class="btn btn-primary pull-right" wire:click="nextStep">
                    Avanti <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>
    @endif

    {{-- ═════════════════════════════════════════════════════ STEP 2 ═════════════════════════════════════════════════════ --}}
    @if($step === 2)
        <div class="qiw-card">
            <div class="qiw-card-header">
                <i class="fa fa-list-ul"></i> Step 2 — Righe fattura
            </div>
            <div class="qiw-card-body">

                {{-- Aggiunta righe --}}
                <div class="row">
                    <div class="col-md-9 form-group">
                        <label>Cerca piatto <small class="text-muted">(inclusi piatti non attivi)</small></label>
                        <div style="position:relative;">
                            <div class="input-group">
                                <span class="input-group-addon" style="background:#fff;">
                                    <i class="fa fa-utensils text-muted"></i>
                                </span>
                                <input type="text"
                                       class="form-control"
                                       placeholder="Digita almeno 2 caratteri..."
                                       wire:model.live.debounce.300ms="dishSearch"
                                       autocomplete="off">
                            </div>
                            @if(count($dishSearchResults) > 0)
                                <div class="qiw-search-dropdown">
                                    @foreach($dishSearchResults as $d)
                                        <div class="qiw-search-item" wire:click="addLineFromDish({{ $d['id'] }})">
                                            <div>
                                                <strong>{{ $d['label'] }}</strong>
                                                @if(empty($d['is_active']))
                                                    <span class="label label-warning" style="margin-left:6px;">non attivo</span>
                                                @endif
                                            </div>
                                            <span class="text-muted">€ {{ number_format((float) $d['price'], 2, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3 form-group" style="display:flex;align-items:flex-end;">
                        <button type="button" class="btn btn-default btn-block" wire:click="addCustomLine">
                            <i class="fa fa-plus"></i> Aggiungi riga custom
                        </button>
                    </div>
                </div>

                @error('lines') <div class="alert alert-danger">{{ $message }}</div> @enderror

                {{-- Tabella righe --}}
                @if(count($lines) > 0)
                    <div class="table-responsive" style="margin-top:6px;">
                        <table class="table qiw-lines-table">
                            <thead>
                                <tr>
                                    <th style="width:45%;">Descrizione</th>
                                    <th style="width:18%;" class="text-center">Quantità</th>
                                    <th style="width:15%;" class="text-end">Prezzo unitario</th>
                                    <th style="width:15%;" class="text-end">Totale riga</th>
                                    <th style="width:7%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $i => $line)
                                    @php
                                        $qty   = (float) ($line['quantity'] ?? 0);
                                        $price = (float) ($line['unit_price'] ?? 0);
                                        $total = $qty * $price;
                                    @endphp
                                    <tr wire:key="line-{{ $i }}">
                                        <td>
                                            <input type="text" class="form-control"
                                                   wire:model.lazy="lines.{{ $i }}.label"
                                                   placeholder="Descrizione riga">
                                            @if(!empty($line['dish_id']))
                                                <small class="text-muted"><i class="fa fa-link"></i> Da piatto #{{ $line['dish_id'] }}</small>
                                            @endif
                                            @error("lines.$i.label") <small class="text-danger d-block">{{ $message }}</small> @enderror
                                        </td>
                                        <td>
                                            <div class="qiw-qty-control">
                                                <button type="button" class="btn btn-default btn-sm" wire:click="decrementQuantity({{ $i }})"><i class="fa fa-minus"></i></button>
                                                <input type="number" min="0.01" step="0.01"
                                                       class="form-control text-center"
                                                       wire:model.lazy="lines.{{ $i }}.quantity">
                                                <button type="button" class="btn btn-default btn-sm" wire:click="incrementQuantity({{ $i }})"><i class="fa fa-plus"></i></button>
                                            </div>
                                            @error("lines.$i.quantity") <small class="text-danger d-block">{{ $message }}</small> @enderror
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <span class="input-group-addon">€</span>
                                                <input type="number" min="0" step="0.01"
                                                       class="form-control text-end"
                                                       wire:model.lazy="lines.{{ $i }}.unit_price">
                                            </div>
                                            @error("lines.$i.unit_price") <small class="text-danger d-block">{{ $message }}</small> @enderror
                                        </td>
                                        <td class="text-end" style="vertical-align:middle;">
                                            <strong>€ {{ number_format($total, 2, ',', '.') }}</strong>
                                        </td>
                                        <td class="text-center" style="vertical-align:middle;">
                                            <button type="button" class="btn btn-xs btn-danger" wire:click="removeLine({{ $i }})" title="Rimuovi">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-muted" style="padding:18px;text-align:center;border:1px dashed #ddd;border-radius:4px;">
                        Nessuna riga aggiunta. Cerca un piatto o aggiungi una riga custom.
                    </div>
                @endif

                <hr>

                {{-- Totali / opzioni --}}
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Aliquota IVA (%)</label>
                        <input type="number" min="0" max="30" step="0.01" class="form-control" wire:model.lazy="vatRate">
                        @error('vatRate') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Sconto totale (€)</label>
                        <input type="number" min="0" step="0.01" class="form-control" wire:model.lazy="discount">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Metodo di pagamento</label>
                        <select class="form-control" wire:model="paymentMethod">
                            <option value="bonifico">Bonifico</option>
                            <option value="contanti">Contanti</option>
                            <option value="pos">POS / Carta</option>
                            <option value="assegno">Assegno</option>
                            <option value="bollettino">Bollettino postale</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Nota / descrizione fattura <small class="text-muted">(opzionale)</small></label>
                    <input type="text" class="form-control" wire:model="description" maxlength="255"
                           placeholder="Es. Catering del 12/05/2026">
                </div>

                <div class="qiw-totals">
                    <div><span>Subtotale</span><strong>€ {{ number_format($this->subtotal, 2, ',', '.') }}</strong></div>
                    @if((float) $discount > 0)
                        <div><span>Sconto</span><strong>− € {{ number_format((float) $discount, 2, ',', '.') }}</strong></div>
                    @endif
                    <div><span>Imponibile</span><strong>€ {{ number_format($this->imponibile, 2, ',', '.') }}</strong></div>
                    <div><span>IVA ({{ rtrim(rtrim(number_format($vatRate, 2, ',', '.'), '0'), ',') }}%)</span><strong>€ {{ number_format($this->tax, 2, ',', '.') }}</strong></div>
                    <div class="qiw-totals-final"><span>Totale</span><strong>€ {{ number_format($this->totalAmount, 2, ',', '.') }}</strong></div>
                </div>
            </div>
            <div class="qiw-card-footer">
                <button type="button" class="btn btn-default" wire:click="previousStep">
                    <i class="fa fa-arrow-left"></i> Indietro
                </button>
                <button type="button" class="btn btn-primary pull-right" wire:click="nextStep">
                    Avanti <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>
    @endif

    {{-- ═════════════════════════════════════════════════════ STEP 3 ═════════════════════════════════════════════════════ --}}
    @if($step === 3)
        <div class="qiw-card">
            <div class="qiw-card-header">
                <i class="fa fa-paper-plane"></i>
                Step 3 — Riepilogo {{ $result ? '& esito' : ($this->isEditMode() ? '& re-invio' : '& invio') }}
            </div>
            <div class="qiw-card-body">

                @if($this->isEditMode() && !$result)
                    <div class="alert alert-warning" style="font-size:13px;">
                        <i class="fa fa-pencil"></i>
                        Stai modificando una fattura precedentemente <strong>scartata</strong> o in errore.
                        Verrà rigenerato l'XML e re-inviata a MySond con lo stesso codice file SDI
                        (<strong>{{ \App\Models\TableOrderInvoice::find($invoiceId)->invoice_name ?? '—' }}</strong>).
                    </div>
                    <div class="form-group" style="max-width:320px;">
                        <label for="invoiceCode"><strong>Numero fattura</strong></label>
                        <input type="text" id="invoiceCode" class="form-control" wire:model.live="invoiceCode" maxlength="50">
                        @error('invoiceCode')<span class="text-danger" style="font-size:12px;">{{ $message }}</span>@enderror
                        <small class="text-muted">Editabile. Deve essere univoco rispetto alle altre fatture.</small>
                    </div>
                @endif

                @if($result)
                    {{-- Esito --}}
                    @if($result['success'])
                        <div class="alert alert-success" style="font-size:14px;">
                            <i class="fa fa-check-circle fa-lg"></i>
                            <strong>{{ $result['message'] }}</strong><br>
                            Codice: <strong>{{ $result['invoice_code'] }}</strong> ·
                            Progressivo: <strong>{{ $result['invoice_name'] }}</strong> ·
                            ID fattura: #{{ $result['invoice_id'] }}
                            <div style="margin-top:10px;">
                                <a href="{{ route('accounting.invoices.index') }}" class="btn btn-info btn-sm">
                                    <i class="fa fa-list"></i> Vai all'elenco fatture
                                </a>
                                <a href="{{ route('accounting.invoices.xml', $result['invoice_id']) }}" target="_blank" class="btn btn-default btn-sm">
                                    <i class="fa fa-file-code"></i> XML
                                </a>
                                <button type="button" class="btn btn-default btn-sm" wire:click="resetWizard">
                                    <i class="fa fa-plus"></i> Emetti un'altra fattura
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-danger">
                            <i class="fa fa-exclamation-triangle fa-lg"></i>
                            <strong>Errore durante l'emissione</strong><br>
                            {{ $result['message'] }}
                            <div style="margin-top:10px;">
                                <button type="button" class="btn btn-default btn-sm" wire:click="$set('result', null); $set('step', 2)">
                                    <i class="fa fa-arrow-left"></i> Torna alle righe e riprova
                                </button>
                            </div>
                        </div>
                    @endif
                @endif

                {{-- Riepilogo cliente --}}
                <h4 style="margin-top:0;"><i class="fa fa-user"></i> Cliente</h4>
                <div class="qiw-summary-grid">
                    <div><span>Tipo</span><strong>{{ $userTypes[$userType] ?? $userType }}</strong></div>
                    <div><span>{{ $userType === 'private' ? 'Nome e cognome' : 'Ragione sociale' }}</span><strong>{{ $fullName }}</strong></div>
                    @if($fiscalCode)<div><span>Codice fiscale</span><strong>{{ $fiscalCode }}</strong></div>@endif
                    @if($vatNumber)<div><span>Partita IVA</span><strong>{{ $vatNumber }}</strong></div>@endif
                    @if($address)<div><span>Indirizzo</span><strong>{{ $address }}</strong></div>@endif
                    @if($city || $zipCode || $province)
                        <div><span>Località</span><strong>{{ trim($zipCode . ' ' . $city . ($province ? " ($province)" : '')) }}</strong></div>
                    @endif
                    @if($codiceDestinatario)<div><span>Cod. destinatario</span><strong>{{ $codiceDestinatario }}</strong></div>@endif
                    @if($pecDestinatario)<div><span>PEC</span><strong>{{ $pecDestinatario }}</strong></div>@endif
                </div>

                {{-- Riepilogo righe --}}
                <h4 style="margin-top:18px;"><i class="fa fa-list"></i> Righe</h4>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Descrizione</th>
                                <th class="text-center" style="width:15%;">Quantità</th>
                                <th class="text-end" style="width:18%;">Prezzo unitario</th>
                                <th class="text-end" style="width:18%;">Totale riga</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $line)
                                @php
                                    $qty   = (float) ($line['quantity'] ?? 0);
                                    $price = (float) ($line['unit_price'] ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ $line['label'] ?? '' }}</td>
                                    <td class="text-center">{{ rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="text-end">€ {{ number_format($price, 2, ',', '.') }}</td>
                                    <td class="text-end"><strong>€ {{ number_format($qty * $price, 2, ',', '.') }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($description)
                    <p><strong>Nota fattura:</strong> {{ $description }}</p>
                @endif

                <div class="qiw-totals">
                    <div><span>Subtotale</span><strong>€ {{ number_format($this->subtotal, 2, ',', '.') }}</strong></div>
                    @if((float) $discount > 0)
                        <div><span>Sconto</span><strong>− € {{ number_format((float) $discount, 2, ',', '.') }}</strong></div>
                    @endif
                    <div><span>Imponibile</span><strong>€ {{ number_format($this->imponibile, 2, ',', '.') }}</strong></div>
                    <div><span>IVA ({{ rtrim(rtrim(number_format($vatRate, 2, ',', '.'), '0'), ',') }}%)</span><strong>€ {{ number_format($this->tax, 2, ',', '.') }}</strong></div>
                    <div class="qiw-totals-final"><span>Totale fattura</span><strong>€ {{ number_format($this->totalAmount, 2, ',', '.') }}</strong></div>
                </div>

                <p class="text-muted" style="margin-top:14px;">
                    Metodo di pagamento: <strong>{{ ucfirst($paymentMethod) }}</strong>
                </p>
            </div>
            @if(!$result)
                <div class="qiw-card-footer">
                    <button type="button" class="btn btn-default" wire:click="previousStep" @if($submitting) disabled @endif>
                        <i class="fa fa-arrow-left"></i> Indietro
                    </button>
                    <button type="button" class="btn btn-success pull-right" wire:click="submit" wire:loading.attr="disabled" @if($submitting) disabled @endif>
                        <span wire:loading.remove wire:target="submit">
                            <i class="fa fa-paper-plane"></i>
                            {{ $this->isEditMode() ? 'Salva e re-invia a MySond' : 'Genera ed invia a MySond' }}
                        </span>
                        <span wire:loading wire:target="submit">
                            <i class="fa fa-spinner fa-spin"></i> Invio in corso...
                        </span>
                    </button>
                </div>
            @endif
        </div>
    @endif

<style>
    .quick-invoice-wizard { max-width: 980px; margin: 0 auto; }

    /* Stepper */
    .qiw-stepper { display: flex; align-items: center; gap: 0; margin-bottom: 22px; }
    .qiw-step { display: flex; flex-direction: column; align-items: center; min-width: 110px; }
    .qiw-step-num {
        width: 36px; height: 36px; border-radius: 50%;
        background: #e7eaec; color: #888; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        transition: background .15s, color .15s;
    }
    .qiw-step.active .qiw-step-num { background: #1c84c6; color: #fff; box-shadow: 0 0 0 4px rgba(28, 132, 198, 0.2); }
    .qiw-step.done .qiw-step-num { background: #1ab394; color: #fff; }
    .qiw-step-label { font-size: 12px; color: #888; margin-top: 4px; text-transform: uppercase; letter-spacing: .3px; }
    .qiw-step.active .qiw-step-label { color: #1c84c6; font-weight: 600; }
    .qiw-step.done .qiw-step-label { color: #1ab394; }
    .qiw-step-sep { flex: 1; height: 2px; background: #e7eaec; margin: 0 4px; margin-top: -22px; }
    .qiw-step-sep.done { background: #1ab394; }

    /* Card */
    .qiw-card { background: #fff; border: 1px solid #e7eaec; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    .qiw-card-header {
        padding: 12px 18px; background: #f8f9fa; border-bottom: 1px solid #e7eaec;
        font-weight: 600; font-size: 14px;
    }
    .qiw-card-body { padding: 18px; }
    .qiw-card-footer {
        padding: 12px 18px; background: #fafafa; border-top: 1px solid #e7eaec;
        display: flex; align-items: center; gap: 8px; overflow: hidden;
    }
    .qiw-card-footer .pull-right { margin-left: auto; }

    /* Search dropdown */
    .qiw-search-dropdown {
        position: absolute; top: 100%; left: 0; right: 0;
        background: #fff; border: 1px solid #ddd; border-top: none;
        z-index: 1000; max-height: 320px; overflow-y: auto;
        box-shadow: 0 6px 12px rgba(0,0,0,0.12); border-radius: 0 0 3px 3px;
    }
    .qiw-search-item {
        padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f4f4f4;
        display: flex; justify-content: space-between; align-items: center;
    }
    .qiw-search-item:hover { background: #f4f6f8; }
    .qiw-search-item:last-child { border-bottom: none; }

    /* Changed field highlight */
    .qiw-changed > label { color: #ec971f; }
    .qiw-changed .form-control { border-color: #ec971f; background: #fff8ec; }
    .qiw-changed-tag {
        font-size: 10px; background: #ec971f; color: #fff; padding: 1px 6px;
        border-radius: 8px; margin-left: 6px; vertical-align: middle;
    }

    /* Guida contestuale requisiti SDI */
    .qiw-guide {
        background: #eef7ff; border-left: 4px solid #1c84c6;
        padding: 10px 14px; margin: 12px 0 18px; border-radius: 3px;
        font-size: 13px;
    }
    .qiw-guide-title { font-weight: 600; color: #1c84c6; margin-bottom: 4px; }
    .qiw-guide-body > div { margin: 2px 0; }

    /* Lines table */
    .qiw-lines-table th { font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .3px; color: #676a6c; }
    .qiw-lines-table td { vertical-align: top; }
    .qiw-qty-control { display: flex; gap: 4px; align-items: center; }
    .qiw-qty-control .form-control { padding: 4px 6px; }

    /* Totals box */
    .qiw-totals {
        margin-top: 12px; padding: 14px 18px;
        background: #f8f9fa; border: 1px solid #e7eaec; border-radius: 4px;
        max-width: 380px; margin-left: auto;
    }
    .qiw-totals > div { display: flex; justify-content: space-between; padding: 3px 0; color: #555; }
    .qiw-totals > div strong { color: #333; }
    .qiw-totals-final {
        margin-top: 6px; padding-top: 8px !important;
        border-top: 1px solid #ddd; font-size: 15px;
    }
    .qiw-totals-final span, .qiw-totals-final strong { color: #1c84c6 !important; font-weight: 700; }

    /* Summary grid */
    .qiw-summary-grid {
        display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px 24px;
        background: #f8f9fa; padding: 12px 16px; border-radius: 4px; border: 1px solid #e7eaec;
    }
    .qiw-summary-grid > div { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dotted #eee; }
    .qiw-summary-grid > div:last-child { border-bottom: none; }
    .qiw-summary-grid span { color: #888; font-size: 12px; }
    .qiw-summary-grid strong { font-size: 13px; text-align: right; }

    .text-end { text-align: right; }
    .d-block { display: block; }
</style>
</div>
