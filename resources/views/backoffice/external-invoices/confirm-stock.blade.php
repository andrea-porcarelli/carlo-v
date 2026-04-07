@extends('backoffice.layout', ['title' => 'Conferma carichi magazzino'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture ricevute', 'href' => route('external-invoices.index')],
        'level_2' => ['label' => 'Conferma carichi magazzino'],
    ])
@endsection

@section('main-content')
<div class="row">
    <div class="col-lg-12">

        {{-- Info fattura --}}
        <div class="panel panel-default">
            <div class="panel-body" style="padding:10px 20px;">
                <div class="row">
                    <div class="col-sm-4">
                        <strong>Fattura n°</strong> {{ $invoice->number ?? '-' }}
                        &nbsp;&middot;&nbsp;
                        <strong>Data</strong> {{ $invoice->date ? $invoice->date->format('d/m/Y') : '-' }}
                    </div>
                    <div class="col-sm-4">
                        @if($supplier)
                            <strong>Fornitore:</strong>
                            {{ $supplier->company_name ?: trim($supplier->first_name.' '.$supplier->last_name) }}
                            @if($supplier->vat_number)
                                <small class="text-muted">({{ $supplier->vat_number }})</small>
                            @endif
                        @endif
                    </div>
                    <div class="col-sm-4 text-right">
                        <strong>Totale fattura:</strong>
                        {{ number_format($invoice->total_amount ?? 0, 2, ',', '.') }} €
                    </div>
                </div>
            </div>
        </div>

        @if($unmappedLines->isNotEmpty())
            <div class="alert alert-warning">
                <span class="glyphicon glyphicon-exclamation-sign"></span>
                <strong>Attenzione:</strong> {{ $unmappedLines->count() }} riga/e non sono ancora mappate e non verranno caricate.
                <a href="{{ route('external-invoices.mapping', $invoice->id) }}" class="alert-link">Completa il mapping</a>
            </div>
        @endif

        {{-- Righe da caricare --}}
        <form id="confirmStockForm">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <span class="glyphicon glyphicon-upload"></span>
                    Righe da caricare a magazzino ({{ $mappedLines->count() }})
                </h3>
            </div>
            <div class="panel-body">
                @if($mappedLines->isEmpty())
                    <div class="alert alert-info" style="margin:0;">
                        Nessuna riga mappata da caricare.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="confirmTable">
                            <thead>
                            <tr>
                                <th>Descrizione XML</th>
                                <th class="text-center">Ingrediente</th>
                                <th class="text-center" width="110px">Qtà fattura</th>
                                <th class="text-center" width="130px">Moltiplicatore</th>
                                <th class="text-center" width="130px">Carico netto</th>
                                <th class="text-right" width="90px">Prezzo unit.</th>
                                <th class="text-center" width="60px">IVA</th>
                                <th class="text-center" width="80px">Stato</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($mappedLines as $line)
                                @php
                                    $realQty = ($line->quantity ?? 1) * ($line->quantity_multiplier ?? 1);
                                    $unit    = $line->material->stock_type ?? '';
                                    $already = $line->stock !== null;
                                @endphp
                                <tr class="{{ $already ? 'success' : '' }}" data-line-id="{{ $line->id }}">
                                    <td>
                                        <strong>{{ $line->description }}</strong>
                                        <br><small class="text-muted">{{ $line->quantity }} {{ $line->unit_of_measure }}</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="label label-default">{{ $line->material->label }}</span>
                                    </td>
                                    <td class="text-center">
                                        {{ number_format($line->quantity ?? 0, 4, ',', '.') }}
                                    </td>
                                    <td class="text-center">
                                        @if($already)
                                            <span class="text-muted">{{ number_format($line->quantity_multiplier ?? 1, 4, ',', '.') }}</span>
                                        @else
                                            <input type="number"
                                                   name="multipliers[{{ $line->id }}]"
                                                   class="form-control input-sm multiplier-input"
                                                   data-line-id="{{ $line->id }}"
                                                   data-base-qty="{{ $line->quantity ?? 1 }}"
                                                   data-unit="{{ $unit }}"
                                                   value="{{ number_format($line->quantity_multiplier ?? 1, 4, '.', '') }}"
                                                   min="0.001"
                                                   step="any"
                                                   style="width:100%; text-align:center;">
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <strong class="carico-netto-{{ $line->id }}">
                                            {{ number_format($realQty, 4, ',', '.') }}
                                        </strong>
                                        <span class="text-muted">{{ $unit }}</span>
                                    </td>
                                    <td class="text-right">
                                        {{ $line->unit_price !== null ? '€ '.number_format($line->unit_price, 2, ',', '.') : '-' }}
                                    </td>
                                    <td class="text-center">
                                        {{ $line->vat_rate !== null ? number_format($line->vat_rate, 0).'%' : '-' }}
                                    </td>
                                    <td class="text-center">
                                        @if($already)
                                            <span class="label label-success">
                                                <span class="glyphicon glyphicon-ok"></span> Caricato
                                            </span>
                                        @else
                                            <span class="label label-primary">
                                                <span class="glyphicon glyphicon-time"></span> Da caricare
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Riepilogo IVA --}}
                    @php
                        $riepilogoIva = $mappedLines->groupBy(fn($l) => (int)($l->vat_rate ?? 0));
                    @endphp
                    <div class="row" style="margin-top:15px;">
                        <div class="col-sm-6 col-sm-offset-6">
                            <table class="table table-condensed table-bordered" style="margin:0;">
                                <thead>
                                    <tr class="active"><th colspan="2">Riepilogo IVA (su righe mappate)</th></tr>
                                </thead>
                                <tbody>
                                @foreach($riepilogoIva as $aliquota => $righe)
                                    @php
                                        $imponibile = $righe->sum(fn($l) => ($l->unit_price ?? 0) * ($l->quantity ?? 0));
                                        $imposta    = $imponibile * ($aliquota / 100);
                                    @endphp
                                    <tr>
                                        <td>IVA {{ $aliquota }}%</td>
                                        <td class="text-right">
                                            Imponibile: <strong>{{ number_format($imponibile, 2, ',', '.') }} €</strong>
                                            &nbsp;·&nbsp;
                                            Imposta: <strong>{{ number_format($imposta, 2, ',', '.') }} €</strong>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Righe ignorate --}}
        @if($ignoredLines->isNotEmpty())
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title" style="cursor:pointer;" data-toggle="collapse" data-target="#ignoredLines">
                        <span class="glyphicon glyphicon-minus-sign"></span>
                        Righe ignorate ({{ $ignoredLines->count() }})
                        <span class="glyphicon glyphicon-chevron-down pull-right"></span>
                    </h3>
                </div>
                <div id="ignoredLines" class="collapse">
                    <div class="panel-body">
                        <table class="table table-condensed table-striped">
                            <thead>
                                <tr><th>Descrizione</th><th class="text-center">Qtà</th><th class="text-right">Prezzo unit.</th></tr>
                            </thead>
                            <tbody>
                            @foreach($ignoredLines as $line)
                                <tr>
                                    <td>{{ $line->description }}</td>
                                    <td class="text-center">{{ $line->quantity }} {{ $line->unit_of_measure }}</td>
                                    <td class="text-right">{{ $line->unit_price !== null ? '€ '.number_format($line->unit_price, 2, ',', '.') : '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Bottoni --}}
        <div class="row" style="margin-bottom:30px;">
            <div class="col-xs-12">
                <div class="clearfix">
                    <div class="pull-left">
                        <a href="{{ route('external-invoices.mapping', $invoice->id) }}" class="btn btn-default">
                            <span class="glyphicon glyphicon-arrow-left"></span> Modifica mapping
                        </a>
                    </div>
                    <div class="pull-right">
                        @if($mappedLines->filter(fn($l) => $l->stock === null)->isNotEmpty())
                            <button type="button" id="btnConfirmStock" class="btn btn-success btn-lg">
                                <span class="glyphicon glyphicon-ok-circle"></span>
                                Conferma {{ $mappedLines->filter(fn($l) => $l->stock === null)->count() }} carichi
                            </button>
                        @else
                            <span class="text-success">
                                <span class="glyphicon glyphicon-ok-circle"></span>
                                Tutti i carichi sono già stati confermati.
                            </span>
                            <a href="{{ route('external-invoices.index') }}" class="btn btn-default">
                                <span class="glyphicon glyphicon-list"></span> Torna alla lista
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        </form>

    </div>
</div>
@endsection

@section('custom-script')
<script>
document.addEventListener('DOMContentLoaded', function() {

    var confirmUrl = '{{ route("external-invoices.store-stock", $invoice->id) }}';

    // ── Ricalcola carico netto al cambio moltiplicatore ──────────────────────
    $(document).on('input', '.multiplier-input', function() {
        var lineId   = $(this).data('line-id');
        var baseQty  = parseFloat($(this).data('base-qty')) || 1;
        var mult     = parseFloat($(this).val()) || 1;
        var realQty  = Math.round(baseQty * mult * 10000) / 10000;
        $('.carico-netto-'+lineId).text(realQty.toLocaleString('it-IT', { minimumFractionDigits: 4, maximumFractionDigits: 4 }));
    });

    // ── Conferma carichi ─────────────────────────────────────────────────────
    $('#btnConfirmStock').on('click', function() {
        if (!confirm('Confermi il carico a magazzino di tutte le righe mappate?')) return;

        var btn  = $(this).prop('disabled', true).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Conferma in corso...');
        var data = $('#confirmStockForm').serializeArray();
        data.push({ name: '_token', value: '{{ csrf_token() }}' });

        $.ajax({
            url: confirmUrl, method: 'POST',
            data: $.param(data),
            success: function(r) {
                if (r.success) {
                    window.location.href = '{{ route("external-invoices.index") }}';
                } else {
                    alert(r.message || 'Errore durante la conferma.');
                    btn.prop('disabled', false).html('<span class="glyphicon glyphicon-ok-circle"></span> Conferma carichi');
                }
            },
            error: function() {
                alert('Errore di rete.');
                btn.prop('disabled', false).html('<span class="glyphicon glyphicon-ok-circle"></span> Conferma carichi');
            }
        });
    });
});
</script>
@endsection

@section('custom-css')
<style>
    tr.success td { background-color: #dff0d8 !important; }
    .panel-heading[data-toggle="collapse"] { cursor: pointer; }
</style>
@endsection
