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
                                    <th>Miglior fornitore</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                <tr class="comparison-row" data-material="{{ strtolower($row['material']->label) }}">
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-default btn-expand-row" data-id="{{ $row['material']->id }}" title="Dettaglio acquisti">
                                            <i class="fa fa-chevron-down"></i>
                                        </button>
                                    </td>
                                    <td><strong>{{ $row['material']->label }}</strong></td>
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
                                    <td colspan="8" style="padding:0; background:#f9f9f9;">
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
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($row['purchases'] as $i => $purchase)
                                                <tr class="{{ $i === 0 ? 'best-purchase-row' : '' }}">
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
@endsection
@section('custom-css')
    <style>
        .best-purchase-row td { background: #f0faf5; }
        .detail-row td { border-top: none !important; }
        .comparison-row td { vertical-align: middle !important; }
        .btn-expand-row.expanded i { transform: rotate(180deg); }
        .btn-expand-row i { transition: transform 0.2s; }
    </style>
@endsection
@section('custom-script')
    <script>
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
                // Nascondi anche la riga dettaglio se il parent è nascosto
                var id = $(this).find('.btn-expand-row').data('id');
                if (!show) $('.detail-row-' + id).hide();
                if (show) visible++;
            });
            $('#visibleCount').text(visible);
        });
    </script>
@endsection
