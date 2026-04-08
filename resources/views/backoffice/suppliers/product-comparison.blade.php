@extends('backoffice.layout', ['title' => 'Comparazione prodotti'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fornitori', 'href' => route('suppliers.index')],
        'level_2' => ['label' => 'Comparazione prodotti'],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row g-1" style="margin-bottom:16px;">
                        <div class="col-lg-4">
                            <input type="text"
                                   id="filterMaterial"
                                   class="form-control"
                                   placeholder="Filtra per nome materiale...">
                        </div>
                        <div class="col-lg-2">
                            <p class="form-control-static text-muted">
                                <span id="visibleCount">{{ $rows->count() }}</span> materiali
                            </p>
                        </div>
                    </div>

                    @if($rows->isEmpty())
                        <div class="alert alert-info">
                            Nessun materiale con acquisti disponibili. Importa e mappa le fatture per vedere la comparazione.
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover" id="comparisonTable">
                            <thead>
                                <tr>
                                    <th style="width:30px;"></th>
                                    <th>Materiale</th>
                                    <th class="text-center" style="width:50px;">U.M.</th>
                                    <th class="text-center" style="width:80px;">Acquisti</th>
                                    <th class="text-right" style="width:120px;">Prezzo medio/u.b.</th>
                                    <th class="text-right" style="width:110px;">Min</th>
                                    <th class="text-right" style="width:110px;">Max</th>
                                    <th class="text-center" style="width:90px;">Variazione</th>
                                    <th>Miglior fornitore</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                @php
                                    $hasAlert = $row['variation'] > 20;
                                    $variationClass = $row['variation'] > 20 ? 'danger' : ($row['variation'] > 10 ? 'warning' : 'success');
                                @endphp
                                <tr class="comparison-row {{ $hasAlert ? 'has-price-alert' : '' }}"
                                    data-material="{{ strtolower($row['material']->label) }}">
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-default btn-expand-row"
                                                data-id="{{ $row['material']->id }}"
                                                title="Dettaglio acquisti">
                                            <i class="fa fa-chevron-down"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <strong>{{ $row['material']->label }}</strong>
                                        <small class="text-muted" style="margin-left:4px;">#{{ $row['material']->id }}</small>
                                        @if($hasAlert)
                                            <i class="fa fa-exclamation-triangle text-danger" style="margin-left:6px;" title="Variazione prezzo &gt;20%"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="label label-default">{{ $row['material']->stock_type }}</span>
                                    </td>
                                    <td class="text-center">{{ $row['purchases_count'] }}</td>
                                    <td class="text-right">
                                        € {{ number_format($row['avg_price'], 4, ',', '.') }}
                                        <small class="text-muted">/{{ $row['material']->stock_type }}</small>
                                    </td>
                                    <td class="text-right text-success">
                                        <strong>€ {{ number_format($row['min_price'], 4, ',', '.') }}</strong>
                                    </td>
                                    <td class="text-right text-danger">
                                        € {{ number_format($row['max_price'], 4, ',', '.') }}
                                    </td>
                                    <td class="text-center">
                                        @if($row['purchases_count'] > 1)
                                            <span class="label label-{{ $variationClass }}">
                                                {{ number_format($row['variation'], 1, ',', '.') }}%
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="label label-success">
                                            <i class="fa fa-trophy"></i>
                                            {{ $row['best']['supplier_name'] }}
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            {{ $row['best']['invoice_number'] }} — {{ $row['best']['invoice_date'] }}
                                            &nbsp;·&nbsp;
                                            <strong>€ {{ number_format($row['best']['price_per_unit'], 4, ',', '.') }}/{{ $row['material']->stock_type }}</strong>
                                        </small>
                                    </td>
                                </tr>

                                {{-- Riga espandibile con storico acquisti e editing inline --}}
                                <tr class="detail-row detail-row-{{ $row['material']->id }}" style="display:none;">
                                    <td colspan="9" style="padding:0; background:#f9f9f9;">
                                        <div style="padding:8px 8px 8px 40px;">
                                            <div class="table-responsive">
                                            <table class="table table-condensed table-detail" style="margin:0; border:none; background:transparent;">
                                                <thead style="background:#eef2f7;">
                                                    <tr>
                                                        <th style="width:60px;">#ID</th>
                                                        <th style="width:180px;">Nome prodotto</th>
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
                                                    @foreach($row['purchases'] as $purchase)
                                                    @php
                                                        $isIgnored = (bool) $purchase->ignore_mapping;
                                                        $isBest = !$isIgnored && $purchase->price_per_unit == $row['min_price'];
                                                        $delta = (!$isIgnored && $row['min_price'] > 0 && $purchase->price_per_unit !== null)
                                                            ? round(($purchase->price_per_unit - $row['min_price']) / $row['min_price'] * 100, 1)
                                                            : null;
                                                        $purchaseAlert = $delta !== null && $delta > 20;
                                                    @endphp
                                                    <tr class="purchase-row {{ $isBest ? 'best-purchase-row' : '' }} {{ $isIgnored ? 'ignored-row' : '' }} {{ $purchaseAlert ? 'purchase-alert-row' : '' }}"
                                                        data-id="{{ $purchase->id }}">
                                                        <td>
                                                            <code class="text-muted" style="font-size:11px;">#{{ $purchase->id }}</code>
                                                        </td>
                                                        <td>
                                                            <span class="product-name-badge" title="{{ $purchase->product_name }}">
                                                                {{ Str::limit($purchase->product_name, 30) }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @if($isBest)<i class="fa fa-trophy text-success"></i>@endif
                                                            {{ $purchase->invoice->supplier->company_name ?? '—' }}
                                                        </td>
                                                        <td>

                                                            <small>{{ $purchase->invoice->invoice_number }}</small>
                                                            <a href="{{ route('invoices.pdf', $purchase->invoice->id) }}" target="_blank" title="Apri PDF fattura">
                                                                <button class="btn btn-xs btn-danger">
                                                                    <i class="fa fa-file-pdf-o"></i>
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
                                                                <small>/{{ $row['material']->stock_type }}</small>
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
                                                                        {{ $mat->id == $row['material']->id ? 'selected' : '' }}>
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
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@section('custom-css')
    <style>
        .best-purchase-row td { background: #f0faf5; }
        .purchase-alert-row td { background: #fff8f8; }
        .ignored-row td { opacity: 0.55; }
        .has-price-alert > td:nth-child(2) { border-left: 3px solid #d9534f; padding-left: 6px; }
        .detail-row td { border-top: none !important; }
        .comparison-row td { vertical-align: middle !important; }
        .btn-expand-row.expanded i { transform: rotate(180deg); }
        .btn-expand-row i { transition: transform 0.2s; }
        .table-detail td { vertical-align: middle !important; }
        .product-name-badge {
            font-family: monospace;
            font-size: 11px;
            background: #eee;
            border-radius: 3px;
            padding: 1px 4px;
            display: inline-block;
            max-width: 175px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .btn-save-row.saving { opacity: 0.6; pointer-events: none; }
        .btn-save-row.saved { background-color: #5cb85c; border-color: #4cae4c; }
    </style>
@endsection
@section('custom-script')
    <script>
        var updateUrl = '{{ rtrim(route('suppliers.invoice-products.update', ['id' => 0]), '0') }}';

        // Espandi / chiudi dettaglio + inizializza select2
        $(document).on('click', '.btn-expand-row', function () {
            var id = $(this).data('id');
            var $detail = $('.detail-row-' + id);
            var visible = $detail.is(':visible');
            $detail.toggle(!visible);
            $(this).toggleClass('expanded', !visible);
            if (!visible) {
                $detail.find('.material-select').each(function () {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2({ width: '220px', dropdownAutoWidth: true });
                    }
                });
            }
        });

        // Filtro per nome materiale
        $('#filterMaterial').on('input', function () {
            var q = $(this).val().toLowerCase().trim();
            var visible = 0;
            $('.comparison-row').each(function () {
                var name = $(this).data('material');
                var show = !q || name.includes(q);
                $(this).toggle(show);
                var id = $(this).find('.btn-expand-row').data('id');
                if (!show) $('.detail-row-' + id).hide();
                if (show) visible++;
            });
            $('#visibleCount').text(visible);
        });

        // Salva singola riga
        $(document).on('click', '.btn-save-row', function () {
            var $btn = $(this);
            var $row = $btn.closest('.purchase-row');
            var id = $row.data('id');

            var payload = {
                _token:              '{{ csrf_token() }}',
                quantity:            $row.find('.quantity-input').val(),
                quantity_multiplier: $row.find('.multiplier-input').val(),
                ignore_mapping:      $row.find('.ignore-checkbox').is(':checked') ? 1 : 0,
                material_id:         $row.find('.material-select').val(),
            };

            $btn.addClass('saving').html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url:    updateUrl + id,
                method: 'PUT',
                data:   payload,
            })
            .done(function () {
                $btn.removeClass('saving').addClass('saved').html('<i class="fa fa-check"></i>');
                // Aggiorna prezzo/u.b. live
                var price = parseFloat($row.find('td:nth-child(6)').text().replace('€','').replace('.','').replace(',','.'));
                var mult  = parseFloat(payload.quantity_multiplier);
                if (mult > 0) {
                    var ppu = (price / mult).toFixed(4).replace('.', ',');
                    $row.find('.price-per-unit-cell strong').text('€ ' + ppu);
                }
                $row.toggleClass('ignored-row', payload.ignore_mapping == 1);
                setTimeout(function () {
                    $btn.removeClass('saved').html('<i class="fa fa-save"></i>');
                }, 2000);
            })
            .fail(function (xhr) {
                $btn.removeClass('saving').html('<i class="fa fa-save"></i>');
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Errore nel salvataggio.';
                alert(msg);
            });
        });
    </script>
@endsection
