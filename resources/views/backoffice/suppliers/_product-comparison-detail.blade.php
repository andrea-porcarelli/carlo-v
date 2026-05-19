<div style="padding:8px 8px 8px 40px;">
    <div class="table-responsive">
        <table class="table table-condensed table-detail" style="margin:0; border:none; background:transparent;">
            <thead style="background:#eef2f7;">
                <tr>
                    <th style="width:60px;">#ID</th>
                    <th>Nome prodotto</th>
                    <th>Fornitore</th>
                    <th style="width:90px;">Fattura</th>
                    <th class="text-center" style="width:80px;">Data</th>
                    <th class="text-right" style="width:90px;">Prezzo</th>
                    <th class="text-right" style="width:70px;">Qtà</th>
                    <th style="width:160px;">Moltiplicatore</th>
                    <th class="text-right" style="width:110px;">Prezzo/u.b.</th>
                    <th class="text-right" style="width:80px;">Δ min</th>
                    <th style="width:200px;">Materiale</th>
                    <th class="text-center" style="width:60px;">Ignora</th>
                    <th style="width:70px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($allPurchases as $purchase)
                @php
                    $isIgnored = (bool) $purchase->ignore_mapping;
                    $isBest = !$isIgnored && $purchase->price_per_unit == $minPrice;
                    $delta = (!$isIgnored && $minPrice > 0 && $purchase->price_per_unit !== null)
                        ? round(($purchase->price_per_unit - $minPrice) / $minPrice * 100, 1)
                        : null;
                    $purchaseAlert = $delta !== null && $delta > 20;
                @endphp
                <tr class="purchase-row {{ $isBest ? 'best-purchase-row' : '' }} {{ $isIgnored ? 'ignored-row' : '' }} {{ $purchaseAlert ? 'purchase-alert-row' : '' }}"
                    data-id="{{ $purchase->id }}">
                    <td>
                        <code class="text-muted" style="font-size:11px;">#{{ $purchase->id }}</code>
                    </td>
                    <td>
                        <span class="product-name-badge">{{ $purchase->product_name }}</span>
                    </td>
                    <td>
                        @if($isBest)<i class="fa fa-trophy text-success"></i>@endif
                        {{ $purchase->invoice->supplier->company_name ?? '—' }}
                    </td>
                    <td>

                        <small>{{ $purchase->invoice->invoice_number }}</small><br />
                        <a href="{{ route('invoices.pdf', $purchase->invoice->id) }}" target="_blank" title="Apri PDF fattura">
                            <button class="btn btn-xs btn-danger">
                                Apri fattura
                            </button>
                        </a>
                    </td>
                    <td class="text-center">
                        <small>{{ $purchase->invoice->invoice_date?->format('d/m/Y') }}</small>
                    </td>
                    <td class="text-right">
                        € {{ number_format($purchase->price, 2, ',', '.') }}
                    </td>
                    <td>
                        <input type="number"
                               class="form-control input-sm quantity-input"
                               value="{{ $purchase->quantity }}"
                               step="0.001" min="0.001"
                               style="width:80px;">
                    </td>
                    <td>
                        <input type="number"
                               class="form-control input-sm multiplier-input"
                               value="{{ $purchase->quantity_multiplier }}"
                               step="0.0001" min="0.0001"
                               style="width:110px; display:inline-block;">
                    </td>
                    <td class="text-right price-per-unit-cell">
                        @if($purchase->price_per_unit !== null)
                            <strong class="{{ $isBest ? 'text-success' : '' }}">
                                € {{ number_format($purchase->price_per_unit, 4, ',', '.') }}
                            </strong>
                            <small>/{{ $material->stock_type }}</small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($isIgnored)
                            <span class="text-muted">ignorato</span>
                        @elseif($delta === null)
                            <span class="text-muted">—</span>
                        @elseif($delta == 0)
                            <span class="text-success"><i class="fa fa-check"></i></span>
                        @else
                            <span class="{{ $purchaseAlert ? 'text-danger' : 'text-warning' }}">
                                @if($purchaseAlert)<i class="fa fa-exclamation-triangle"></i>@endif
                                +{{ number_format($delta, 1, ',', '.') }}%
                            </span>
                        @endif
                    </td>
                    <td>
                        <select class="form-control input-sm material-select" style="min-width:180px;">
                            @foreach($materials as $mat)
                                <option value="{{ $mat->id }}"
                                    {{ $mat->id == $material->id ? 'selected' : '' }}>
                                    #{{ $mat->id }} {{ $mat->label }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td class="text-center">
                        <input type="checkbox"
                               class="ignore-checkbox"
                               {{ $isIgnored ? 'checked' : '' }}>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-xs btn-primary btn-save-row" title="Salva">
                            <i class="fa fa-save"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
