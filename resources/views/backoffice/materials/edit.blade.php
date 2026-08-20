@extends('backoffice.layout')

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'title' => 'Modifica Ingrediente',
        'level_1' => ['label' => 'Ingredienti', 'href' => route('restaurant.materials.index')],
        'level_2' => ['label' => 'Modifica Ingrediente: ' . $object->label],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element" id="update-or-create-element">
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'label', 'label' => 'Ingrediente (Es. spaghetti) *', 'col' => 12])
                        </div>
                        <div class="row">
                            @include('backoffice.components.form.input',['form' => 'update-or-create-element', 'name' => 'stock', 'label' => 'Quantità *', 'col' => 2])
                            @include('backoffice.components.form.select',['form' => 'update-or-create-element', 'name' => 'stock_type', 'label' => 'Unità di misura *', 'col' => 2, 'options' => $stock_types])
                            @include('backoffice.components.form.input',['form' => 'update-or-create-element', 'name' => 'alert_threshold', 'label' => 'Soglia minima giacenza *', 'col' => 2])

                            <div class="col-xs-12 col-sm-3 m-t-sm">
                                <label>Traccia giacenza</label><br />
                                @include('backoffice.components.form.switch', [
                                    'field' => true,
                                    'name'  => 'track_stock',
                                    'value' => $object->track_stock,
                                ])
                                <small class="text-muted d-block">
                                    Se disattivato, questo ingrediente viene escluso dagli avvisi di scorta bassa e dalla notifica Telegram.
                                </small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-12 col-sm-2 text-center m-t-sm">
                                @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Modifica Ingrediente', 'dataset' => ['route' => 'restaurant/materials', 'id' => $object->id ]])
                                <div class="col-xs-12 object-response"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-sm-3">
                            <div class="panel panel-success">
                                <div class="panel-body text-center">
                                    <div class="h3" style="margin:0">{{ number_format($stockSummary['imported'], 2, ',', '.') }} {{ $object->stock_type }}</div>
                                    <div class="text-muted">Importato</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="panel panel-warning">
                                <div class="panel-body text-center">
                                    <div class="h3" style="margin:0">{{ number_format($stockSummary['consumed'], 2, ',', '.') }} {{ $object->stock_type }}</div>
                                    <div class="text-muted">Consumato</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="panel panel-{{ $stockSummary['current'] < 0 ? 'danger' : 'info' }}">
                                <div class="panel-body text-center">
                                    <div class="h3" style="margin:0">{{ number_format($stockSummary['current'], 2, ',', '.') }} {{ $object->stock_type }}</div>
                                    <div class="text-muted">Giacenza Attuale</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="panel panel-{{ $stockSummary['is_low'] ? 'danger' : 'default' }}">
                                <div class="panel-body text-center">
                                    <div class="h3" style="margin:0">
                                        @if(!$object->track_stock)
                                            <span class="text-muted"><i class="fa fa-bell-slash"></i> Non tracciato</span>
                                        @elseif($stockSummary['is_low'])
                                            <span class="text-danger"><i class="fa fa-exclamation-triangle"></i> BASSO</span>
                                        @else
                                            <span class="text-success"><i class="fa fa-check"></i> OK</span>
                                        @endif
                                    </div>
                                    <div class="text-muted">Stato</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <ul class="nav nav-tabs" id="material-detail-tabs">
                        <li class="active">
                            <a href="#tab-movimentazioni" data-toggle="tab">
                                <i class="fa fa-exchange"></i> Movimentazioni
                                <span class="label label-default">{{ $movements->count() }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#tab-acquisti" data-toggle="tab">
                                <i class="fa fa-shopping-cart"></i> Acquisti (mappature)
                                <span class="label label-default">{{ $purchases->count() }}</span>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content" style="padding-top:20px;">
                        <div class="tab-pane active" id="tab-movimentazioni">
                            <button
                                class="btn btn-sm btn-warning btn-add-stock m-b-sm"
                                title="Aggiungi giacenza"
                                data-id="{{ $object->id }}"
                                data-label="{{ $object->label }}"
                                data-stock-type="{{ $object->stock_type }}"
                            >
                                <span class="fa fa-plus-circle"></span> Aggiungi quantità
                            </button>
                            <button
                                class="btn btn-sm btn-danger btn-remove-stock m-b-sm"
                                title="Rimuovi giacenza"
                                data-id="{{ $object->id }}"
                                data-label="{{ $object->label }}"
                                data-stock-type="{{ $object->stock_type }}"
                            >
                                <span class="fa fa-minus-circle"></span> Rimuovi quantità
                            </button>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover">
                                    <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th class="text-right">Quantità</th>
                                        <th>Dettaglio</th>
                                        <th>Note</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($movements as $mov)
                                        <tr class="{{ $mov->type === 'consumption' ? 'warning' : ($mov->type === 'adjustment' ? 'danger' : 'success') }}">
                                            <td>{{ \Carbon\Carbon::parse($mov->date)->format('d/m/Y H:i') }}</td>
                                            <td>
                                                @if($mov->type === 'load')
                                                    <span class="label label-success"><i class="fa fa-arrow-down"></i> Carico</span>
                                                @elseif($mov->type === 'adjustment')
                                                    <span class="label label-danger"><i class="fa fa-minus-circle"></i> Rettifica</span>
                                                @else
                                                    <span class="label label-warning"><i class="fa fa-arrow-up"></i> Consumo</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                @if($mov->type === 'load')
                                                    <strong class="text-success">+{{ number_format($mov->quantity, 2, ',', '.') }}</strong>
                                                @elseif($mov->type === 'adjustment')
                                                    <strong class="text-danger">-{{ number_format(abs($mov->quantity), 2, ',', '.') }}</strong>
                                                @else
                                                    <strong class="text-danger">-{{ number_format($mov->quantity, 2, ',', '.') }}</strong>
                                                @endif
                                                {{ $object->stock_type }}
                                            </td>
                                            <td>
                                                @if($mov->type === 'load')
                                                    @if($mov->invoice_product)
                                                        <i class="fa fa-file-text-o"></i> Fattura: {{ $mov->invoice_product }}
                                                    @else
                                                        <i class="fa fa-pencil"></i> Inserimento manuale
                                                    @endif
                                                    @if($mov->purchase_price)
                                                        - € {{ number_format($mov->purchase_price, 2, ',', '.') }}
                                                    @endif
                                                @elseif($mov->type === 'adjustment')
                                                    <i class="fa fa-pencil"></i> Rettifica manuale
                                                @else
                                                    <i class="fa fa-cutlery"></i> {{ $mov->dish_name }} (x{{ $mov->dish_qty }}) - Tavolo: {{ $mov->table_name }}
                                                @endif
                                            </td>
                                            <td>
                                                @if($mov->type === 'consumption' && $mov->is_autoconsumo)
                                                    <span class="label label-info"><i class="fa fa-info-circle"></i> Autoconsumo</span>
                                                @endif
                                                {{ $mov->notes ?? '' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">Nessuna movimentazione trovata</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane" id="tab-acquisti">
                            @if($purchases->isEmpty())
                                <div class="alert alert-info">
                                    Nessun acquisto trovato per questo ingrediente. Le mappature si creano dalle fatture fornitori.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered table-hover table-condensed">
                                        <thead>
                                            <tr>
                                                <th style="width:60px;">#ID</th>
                                                <th>Nome prodotto</th>
                                                <th>Fornitore</th>
                                                <th style="width:120px;">Fattura</th>
                                                <th class="text-center" style="width:90px;">Data</th>
                                                <th class="text-right" style="width:90px;">Prezzo</th>
                                                <th class="text-right" style="width:90px;">Qtà</th>
                                                <th style="width:120px;">Moltiplicatore</th>
                                                <th class="text-right" style="width:110px;">Prezzo/u.b.</th>
                                                <th class="text-right" style="width:80px;">Δ min</th>
                                                <th style="width:220px;">Materiale</th>
                                                <th class="text-center" style="width:60px;">Ignora</th>
                                                <th style="width:60px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($purchases as $purchase)
                                                @php
                                                    $isIgnored = (bool) $purchase->ignore_mapping;
                                                    $isBest = !$isIgnored && $minPrice > 0 && $purchase->price_per_unit == $minPrice;
                                                    $delta = (!$isIgnored && $minPrice > 0 && $purchase->price_per_unit !== null)
                                                        ? round(($purchase->price_per_unit - $minPrice) / $minPrice * 100, 1)
                                                        : null;
                                                    $purchaseAlert = $delta !== null && $delta > 20;
                                                @endphp
                                                <tr class="purchase-row {{ $isBest ? 'best-purchase-row' : '' }} {{ $isIgnored ? 'ignored-row' : '' }} {{ $purchaseAlert ? 'purchase-alert-row' : '' }}"
                                                    data-id="{{ $purchase->id }}">
                                                    <td><code class="text-muted" style="font-size:11px;">#{{ $purchase->id }}</code></td>
                                                    <td><span class="product-name-badge">{{ $purchase->product_name }}</span></td>
                                                    <td>
                                                        @if($isBest)<i class="fa fa-trophy text-success"></i>@endif
                                                        {{ $purchase->invoice->supplier->company_name ?? '—' }}
                                                    </td>
                                                    <td>
                                                        <small>{{ $purchase->invoice->invoice_number }}</small><br />
                                                        <a href="{{ route('invoices.pdf', $purchase->invoice->id) }}" target="_blank" title="Apri PDF fattura">
                                                            <button class="btn btn-xs btn-danger">Apri fattura</button>
                                                        </a>
                                                    </td>
                                                    <td class="text-center"><small>{{ $purchase->invoice->invoice_date?->format('d/m/Y') }}</small></td>
                                                    <td class="text-right price-cell" data-price="{{ $purchase->price }}">
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
                                                               style="width:110px;">
                                                    </td>
                                                    <td class="text-right price-per-unit-cell">
                                                        @if($purchase->price_per_unit !== null)
                                                            <strong class="{{ $isBest ? 'text-success' : '' }}">
                                                                € {{ number_format($purchase->price_per_unit, 4, ',', '.') }}
                                                            </strong>
                                                            <small>/{{ $object->stock_type }}</small>
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
                                                        <select class="form-control input-sm material-select" style="min-width:200px;">
                                                            @foreach($materials as $mat)
                                                                <option value="{{ $mat->id }}" {{ $mat->id == $object->id ? 'selected' : '' }}>
                                                                    #{{ $mat->id }} {{ $mat->label }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="checkbox" class="ignore-checkbox" {{ $isIgnored ? 'checked' : '' }}>
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
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Aggiungi Giacenza -->
    <x-load-material-modal />

    <!-- Modal Rimuovi Giacenza -->
    <x-remove-material-modal />
@endsection

@section('custom-css')
    <style>
        .best-purchase-row td { background: #f0faf5; }
        .purchase-alert-row td { background: #fff8f8; }
        .ignored-row td { opacity: 0.55; }
        .product-name-badge {
            font-family: monospace;
            font-size: 11px;
            background: #eee;
            border-radius: 3px;
            padding: 1px 4px;
            display: inline-block;
            white-space: nowrap;
        }
        .btn-save-row.saving { opacity: 0.6; pointer-events: none; }
        .btn-save-row.saved { background-color: #5cb85c; border-color: #4cae4c; }
        #tab-acquisti td { vertical-align: middle !important; }
    </style>
@endsection

@section('custom-script')
    <script>
        var updateUrl = '{{ rtrim(route('suppliers.invoice-products.update', ['id' => 0]), '0') }}';

        // Inizializza select2 la prima volta che si apre il tab Acquisti
        var acquistiInit = false;
        $('#material-detail-tabs a[href="#tab-acquisti"]').on('shown.bs.tab', function () {
            if (acquistiInit) return;
            $('#tab-acquisti .material-select').each(function () {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({ width: '220px', dropdownAutoWidth: true });
                }
            });
            acquistiInit = true;
        });

        // Salva singola riga mappatura
        $(document).on('click', '#tab-acquisti .btn-save-row', function () {
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
                var price = parseFloat($row.find('.price-cell').data('price'));
                var mult  = parseFloat(payload.quantity_multiplier);
                if (mult > 0 && !isNaN(price)) {
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
