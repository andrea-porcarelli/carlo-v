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

                                {{-- Riga espandibile: il dettaglio viene caricato via AJAX al primo click su "espandi" --}}
                                <tr class="detail-row detail-row-{{ $row['material']->id }}" style="display:none;">
                                    <td colspan="9" style="padding:0; background:#f9f9f9;">
                                        <div class="detail-content" data-loaded="0"></div>
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
            white-space: nowrap;
        }
        .btn-save-row.saving { opacity: 0.6; pointer-events: none; }
        .btn-save-row.saved { background-color: #5cb85c; border-color: #4cae4c; }
    </style>
@endsection
@section('custom-script')
    <script>
        var updateUrl = '{{ rtrim(route('suppliers.invoice-products.update', ['id' => 0]), '0') }}';
        var detailUrlTpl = '{{ route('suppliers.product-comparison.detail', ['material' => '__ID__']) }}';

        // Espandi / chiudi dettaglio (lazy-load al primo click) + inizializza select2
        $(document).on('click', '.btn-expand-row', function () {
            var id = $(this).data('id');
            var $detail = $('.detail-row-' + id);
            var $content = $detail.find('.detail-content');
            var visible = $detail.is(':visible');
            $detail.toggle(!visible);
            $(this).toggleClass('expanded', !visible);

            if (visible) return;

            if ($content.data('loaded') == 1) {
                return;
            }

            $content.html('<div style="padding:16px;"><i class="fa fa-spinner fa-spin"></i> Caricamento...</div>');
            $.get(detailUrlTpl.replace('__ID__', id))
                .done(function (html) {
                    $content.html(html);
                    $content.data('loaded', 1);
                    $content.find('.material-select').each(function () {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({ width: '220px', dropdownAutoWidth: true });
                        }
                    });
                })
                .fail(function () {
                    $content.html('<div class="alert alert-danger" style="margin:8px;">Errore nel caricamento del dettaglio.</div>');
                });
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
