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
                                    data-material="{{ strtolower($row['material']->label) }}"
                                    data-material-id="{{ $row['material']->id }}"
                                    data-material-label="{{ $row['material']->label }}"
                                    data-mappings="{{ json_encode($row['mappings']) }}">
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-default btn-expand-row" data-id="{{ $row['material']->id }}" title="Dettaglio acquisti">
                                            <i class="fa fa-chevron-down"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <strong>{{ $row['material']->label }}</strong>
                                        <small class="text-muted" style="margin-left:4px;">#{{ $row['material']->id }}</small>
                                        @if($hasAlert)
                                            <i class="fa fa-exclamation-triangle text-danger" style="margin-left:6px;" title="Variazione prezzo &gt;20%"></i>
                                        @endif
                                        <button class="btn btn-xs btn-default btn-edit-mapping"
                                                style="margin-left:6px;"
                                                title="Modifica mappature"
                                                data-toggle="modal"
                                                data-target="#mappingModal">
                                            <i class="fa fa-pencil"></i>
                                        </button>
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
                                {{-- Riga espandibile con storico acquisti --}}
                                <tr class="detail-row detail-row-{{ $row['material']->id }}" style="display:none;">
                                    <td colspan="9" style="padding:0; background:#f9f9f9;">
                                        <table class="table table-condensed" style="margin:0; border:none;">
                                            <thead style="background:#eef2f7;">
                                                <tr>
                                                    <th style="padding-left:40px;">Fornitore</th>
                                                    <th>Fattura</th>
                                                    <th class="text-center">Data</th>
                                                    <th class="text-right">Prezzo fattura</th>
                                                    <th class="text-right">Quantità</th>
                                                    <th class="text-right">Moltiplicatore</th>
                                                    <th class="text-right">Prezzo/u.b.</th>
                                                    <th class="text-right">Δ rispetto al min</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($row['purchases'] as $i => $purchase)
                                                @php
                                                    $delta = $row['min_price'] > 0
                                                        ? round(($purchase->price_per_unit - $row['min_price']) / $row['min_price'] * 100, 1)
                                                        : 0;
                                                    $purchaseAlert = $delta > 20;
                                                @endphp
                                                <tr class="{{ $i === 0 ? 'best-purchase-row' : '' }} {{ $purchaseAlert ? 'purchase-alert-row' : '' }}">
                                                    <td style="padding-left:40px;">
                                                        @if($i === 0)
                                                            <i class="fa fa-trophy text-success"></i>
                                                        @endif
                                                        {{ $purchase->invoice->supplier->company_name ?? '—' }}
                                                    </td>
                                                    <td>
                                                        <small>{{ $purchase->invoice->invoice_number }}</small>
                                                    </td>
                                                    <td class="text-center">
                                                        <small>{{ $purchase->invoice->invoice_date?->format('d/m/Y') }}</small>
                                                    </td>
                                                    <td class="text-right">
                                                        € {{ number_format($purchase->price, 2, ',', '.') }}
                                                    </td>
                                                    <td class="text-right">{{ $purchase->quantity }}</td>
                                                    <td class="text-right text-muted">
                                                        <small>×{{ number_format($purchase->quantity_multiplier, 4, ',', '.') }}</small>
                                                    </td>
                                                    <td class="text-right {{ $i === 0 ? 'text-success' : '' }}">
                                                        <strong>€ {{ number_format($purchase->price_per_unit, 4, ',', '.') }}</strong>
                                                        <small>/{{ $row['material']->stock_type }}</small>
                                                    </td>
                                                    <td class="text-right">
                                                        @if($i === 0)
                                                            <span class="text-success"><i class="fa fa-check"></i> miglior prezzo</span>
                                                        @else
                                                            <span class="{{ $purchaseAlert ? 'text-danger' : 'text-warning' }}">
                                                                @if($purchaseAlert)<i class="fa fa-exclamation-triangle"></i>@endif
                                                                +{{ number_format($delta, 1, ',', '.') }}%
                                                            </span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
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

    {{-- Modal modifica mappature --}}
    <div class="modal fade" id="mappingModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">
                        Mappature — <span id="mappingModalMaterial"></span>
                        <small class="text-muted" id="mappingModalMaterialId"></small>
                    </h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted" style="font-size:12px;">
                        Modifica il materiale associato a ciascun nome prodotto. La modifica si applica a tutti gli acquisti futuri con quel nome.
                    </p>
                    <table class="table table-condensed" id="mappingModalTable">
                        <thead>
                            <tr>
                                <th>Nome prodotto</th>
                                <th>Materiale</th>
                            </tr>
                        </thead>
                        <tbody id="mappingModalBody"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="saveMappingsBtn">
                        <i class="fa fa-save"></i> Salva
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('custom-css')
    <style>
        .best-purchase-row td { background: #f0faf5; }
        .purchase-alert-row td { background: #fff8f8; }
        .has-price-alert > td:first-child { border-left: 3px solid #d9534f; }
        .detail-row td { border-top: none !important; }
        .comparison-row td { vertical-align: middle !important; }
        .btn-expand-row.expanded i { transform: rotate(180deg); }
        .btn-expand-row i { transition: transform 0.2s; }
        .btn-edit-mapping { padding: 1px 5px; }
    </style>
@endsection
@section('custom-script')
    <script>
        var allMaterials = @json($materials->map(fn($m) => ['id' => $m->id, 'label' => $m->label]));
        var updateMappingUrl = '{{ route('suppliers.mappings.update', ['id' => '__ID__']) }}';

        // Espandi / chiudi dettaglio
        $(document).on('click', '.btn-expand-row', function () {
            var id = $(this).data('id');
            var $detail = $('.detail-row-' + id);
            var visible = $detail.is(':visible');
            $detail.toggle(!visible);
            $(this).toggleClass('expanded', !visible);
        });

        // Filtro per nome materiale (client-side)
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

        // Apri modal mappature
        $(document).on('click', '.btn-edit-mapping', function () {
            var $row = $(this).closest('.comparison-row');
            var label = $row.data('material-label');
            var matId = $row.data('material-id');
            var mappings = $row.data('mappings');

            $('#mappingModalMaterial').text(label);
            $('#mappingModalMaterialId').text('#' + matId);

            var tbody = $('#mappingModalBody').empty();
            $.each(mappings, function (_, m) {
                var options = allMaterials.map(function (mat) {
                    var sel = mat.id === matId ? ' selected' : '';
                    return '<option value="' + mat.id + '"' + sel + '>' + mat.label + ' #' + mat.id + '</option>';
                }).join('');
                tbody.append(
                    '<tr>' +
                    '<td><code>' + m.product_name + '</code></td>' +
                    '<td>' +
                    '<select class="form-control input-sm mapping-select" data-mapping-id="' + m.id + '">' +
                    options +
                    '</select>' +
                    '</td>' +
                    '</tr>'
                );
            });
        });

        // Salva mappature
        $('#saveMappingsBtn').on('click', function () {
            var $btn = $(this).prop('disabled', true).text('Salvataggio...');
            var requests = [];

            $('#mappingModalBody .mapping-select').each(function () {
                var mappingId = $(this).data('mapping-id');
                var materialId = $(this).val();
                var url = updateMappingUrl.replace('__ID__', mappingId);
                requests.push(
                    $.ajax({
                        url: url,
                        method: 'PUT',
                        data: { _token: '{{ csrf_token() }}', material_id: materialId },
                    })
                );
            });

            $.when.apply($, requests)
                .done(function () {
                    $('#mappingModal').modal('hide');
                    location.reload();
                })
                .fail(function () {
                    alert('Errore durante il salvataggio. Riprova.');
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Salva');
                });
        });
    </script>
@endsection
