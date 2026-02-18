@extends('backoffice.layout')

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'title' => 'Dashboard',
        'level_1' => ['label' => 'Dashboard'],
    ])
@endsection

@section('main-content')

{{-- ══════════════════════════════════════════════════════════
     SEZIONE FINANZIARIA
══════════════════════════════════════════════════════════ --}}
<div class="row m-b-sm">
    <div class="col-lg-12" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <h3 class="m-t-none m-b-none">
            <i class="fa fa-line-chart"></i> Andamento Finanziario
            <small class="text-muted" style="font-size:13px;">
                <i class="fa fa-clock-o"></i> Aggiornato ogni 5 minuti &mdash; Autoconsumo escluso
            </small>
        </h3>
        <button id="btn-clear-cache" class="btn btn-sm btn-default" title="Invalida la cache e ricarica tutti i dati in tempo reale">
            <i class="fa fa-refresh"></i> Aggiorna dati ora
        </button>
    </div>
</div>

{{-- KPI Revenue --}}
<div class="row">
    <div class="col-lg-3 col-md-6">
        <div class="panel panel-primary">
            <div class="panel-body text-center">
                <div class="h2 m-t-none m-b-xs">€ {{ number_format($revenueToday, 2, ',', '.') }}</div>
                <div class="text-muted">Fatturato Oggi</div>
                <hr style="margin: 8px 0">
                <small><i class="fa fa-shopping-cart"></i> {{ $ordersToday }} ordini</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="panel panel-info">
            <div class="panel-body text-center">
                <div class="h2 m-t-none m-b-xs">€ {{ number_format($revenueWeek, 2, ',', '.') }}</div>
                <div class="text-muted">Fatturato Settimana</div>
                <hr style="margin: 8px 0">
                <small><i class="fa fa-shopping-cart"></i> {{ $ordersWeek }} ordini</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="panel panel-success">
            <div class="panel-body text-center">
                <div class="h2 m-t-none m-b-xs">€ {{ number_format($revenueMonth, 2, ',', '.') }}</div>
                <div class="text-muted">Fatturato Mese</div>
                <hr style="margin: 8px 0">
                <small>
                    <i class="fa fa-shopping-cart"></i> {{ $ordersMonth }} ordini
                    &mdash; media € {{ number_format($avgTicketMonth, 2, ',', '.') }}
                </small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <div class="h2 m-t-none m-b-xs">€ {{ number_format($revenueYear, 2, ',', '.') }}</div>
                <div class="text-muted">Fatturato Anno {{ date('Y') }}</div>
                <hr style="margin: 8px 0">
                <small><i class="fa fa-calendar"></i> Anno in corso</small>
            </div>
        </div>
    </div>
</div>

{{-- Miglior scontrino (banner) --}}
@if($bestReceipt)
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-warning" style="border-left: 4px solid #f8ac59;">
            <div class="panel-body" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div>
                    <i class="fa fa-trophy text-warning" style="font-size:20px;"></i>
                    <strong style="font-size:15px; margin-left:8px;">Miglior Scontrino di Sempre</strong>
                    <span class="text-muted" style="margin-left:15px;">
                        Tavolo <strong>{{ $bestReceipt->restaurantTable?->table_number ?? 'N/A' }}</strong>
                        &mdash; {{ $bestReceipt->closed_at?->format('d/m/Y H:i') ?? '-' }}
                        @if($bestReceipt->waiter)
                            &mdash; <i class="fa fa-user"></i> {{ $bestReceipt->waiter->name }}
                        @endif
                        &mdash; <i class="fa fa-cutlery"></i> {{ $bestReceipt->items->count() }} prodotti
                    </span>
                </div>
                <div class="h2 m-none text-warning">
                    € {{ number_format($bestReceipt->total_amount, 2, ',', '.') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Grafici trend --}}
<div class="row">
    <div class="col-lg-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="fa fa-bar-chart"></i> Andamento Ultime 8 Settimane
            </div>
            <div class="panel-body">
                <canvas id="weeklyChart" style="max-height:260px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="fa fa-bar-chart"></i> Andamento Ultimi 12 Mesi
            </div>
            <div class="panel-body">
                <canvas id="monthlyChart" style="max-height:260px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Top dishes + Classifica operatori --}}
<div class="row">
    <div class="col-lg-7">
        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="fa fa-cutlery"></i> Top 10 Piatti Più Venduti
            </div>
            <div class="panel-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="table table-striped table-hover m-b-none">
                        <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Piatto</th>
                            <th class="text-right">Quantità</th>
                            <th class="text-right">Ricavo Totale</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($topDishes as $i => $item)
                            <tr>
                                <td>
                                    @if($i === 0)
                                        <span class="label label-warning"><i class="fa fa-trophy"></i> 1°</span>
                                    @elseif($i === 1)
                                        <span class="label label-default">2°</span>
                                    @elseif($i === 2)
                                        <span class="label label-default">3°</span>
                                    @else
                                        <span class="text-muted">{{ $i + 1 }}°</span>
                                    @endif
                                </td>
                                <td>{{ $item->dish?->label ?? 'N/A' }}</td>
                                <td class="text-right">
                                    <strong>{{ number_format($item->total_qty, 0, ',', '.') }}</strong> pz
                                </td>
                                <td class="text-right">€ {{ number_format($item->total_revenue, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">Nessun dato disponibile</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="fa fa-users"></i> Classifica Operatori per Venduto
            </div>
            <div class="panel-body" style="padding: 5px 0 0;">
                @php $maxRev = $topOperators->first()?->total_revenue ?? 1; @endphp
                @forelse($topOperators as $i => $op)
                @php
                    $pct      = $maxRev > 0 ? round(($op->total_revenue / $maxRev) * 100) : 0;
                    $barColor = ['progress-bar-warning', 'progress-bar-info', 'progress-bar-success'][$i] ?? 'progress-bar-info';
                @endphp
                <div style="padding: 8px 15px; {{ $i < $topOperators->count() - 1 ? 'border-bottom:1px solid #f5f5f5;' : '' }}">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                        <div>
                            @if($i === 0)
                                <span class="label label-warning"><i class="fa fa-trophy"></i> 1°</span>
                            @elseif($i === 1)
                                <span class="label label-default">2°</span>
                            @elseif($i === 2)
                                <span class="label label-default">3°</span>
                            @else
                                <span class="text-muted" style="font-size:12px;">{{ $i + 1 }}°</span>
                            @endif
                            <strong style="margin-left:6px;">{{ $op->waiter?->name ?? 'N/A' }}</strong>
                        </div>
                        <div class="text-right">
                            <strong>€ {{ number_format($op->total_revenue, 2, ',', '.') }}</strong>
                            <small class="text-muted" style="display:block;">
                                {{ $op->orders_count }} scontrini &mdash; media € {{ number_format($op->avg_ticket, 2, ',', '.') }}
                            </small>
                        </div>
                    </div>
                    <div class="progress progress-mini m-b-none">
                        <div class="progress-bar {{ $barColor }}" style="width:{{ $pct }}%;"></div>
                    </div>
                </div>
                @empty
                    <div class="text-center text-muted" style="padding:20px;">Nessun dato disponibile</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     SEZIONE OPERATIVA GIORNALIERA
══════════════════════════════════════════════════════════ --}}
<div class="row m-t-md m-b-sm">
    <div class="col-lg-12" style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
        <h3 class="m-t-none m-b-none">
            <i class="fa fa-calendar-check-o"></i> Riepilogo Giornaliero
        </h3>
        <div style="display:flex; align-items:center; gap:8px;">
            <input type="date" id="daily-date-picker" class="form-control input-sm"
                   value="{{ date('Y-m-d') }}" style="width:160px;">
            <button id="btn-load-daily" class="btn btn-sm btn-primary">
                <i class="fa fa-refresh"></i> Aggiorna
            </button>
        </div>
        <small class="text-muted" id="daily-cache-note" style="display:none;">
            <i class="fa fa-clock-o"></i> Dati aggiornati alle <span id="daily-loaded-at"></span>
        </small>
    </div>
</div>

{{-- KPI giornalieri --}}
<div class="row" id="daily-kpi-row">
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel panel-primary">
            <div class="panel-body text-center">
                <div class="h3 m-t-none m-b-xs" id="d-totale">—</div>
                <div class="text-muted small">Totale Scontrinato</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel panel-info">
            <div class="panel-body text-center">
                <div class="h3 m-t-none m-b-xs" id="d-media">—</div>
                <div class="text-muted small">Media Scontrini</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <div class="h3 m-t-none m-b-xs" id="d-ordini">—</div>
                <div class="text-muted small">N. Scontrini</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel panel-danger">
            <div class="panel-body text-center">
                <div class="h3 m-t-none m-b-xs" id="d-cancellazioni">—</div>
                <div class="text-muted small">Articoli Cancellati</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel panel-warning">
            <div class="panel-body text-center">
                <div class="h3 m-t-none m-b-xs" id="d-prezzi">—</div>
                <div class="text-muted small">Modifiche Prezzo</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <div class="h3 m-t-none m-b-xs" id="d-modifiche">—</div>
                <div class="text-muted small">Modifiche Operatore</div>
            </div>
        </div>
    </div>
</div>

{{-- Tabelle di dettaglio --}}
<div class="row" id="daily-details-row">
    {{-- Articoli cancellati --}}
    <div class="col-lg-4">
        <div class="panel panel-danger">
            <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#tbl-cancellazioni">
                <i class="fa fa-trash"></i> Articoli Cancellati
                <span class="badge pull-right" id="badge-cancellazioni">0</span>
            </div>
            <div class="panel-body collapse in" id="tbl-cancellazioni" style="padding:0;">
                <div class="table-responsive">
                    <table class="table table-condensed table-hover m-b-none" id="table-cancellazioni">
                        <thead>
                        <tr>
                            <th>Ora</th>
                            <th>Operatore</th>
                            <th>Tav.</th>
                            <th>Prodotto</th>
                            <th class="text-center">Qtà</th>
                            <th class="text-right">Prezzo</th>
                        </tr>
                        </thead>
                        <tbody><tr><td colspan="6" class="text-center text-muted">—</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Modifiche prezzo --}}
    <div class="col-lg-4">
        <div class="panel panel-warning">
            <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#tbl-prezzi">
                <i class="fa fa-tag"></i> Modifiche di Prezzo
                <span class="badge pull-right" id="badge-prezzi">0</span>
            </div>
            <div class="panel-body collapse in" id="tbl-prezzi" style="padding:0;">
                <div class="table-responsive">
                    <table class="table table-condensed table-hover m-b-none" id="table-prezzi">
                        <thead>
                        <tr>
                            <th>Ora</th>
                            <th>Operatore</th>
                            <th>Tav.</th>
                            <th>Prodotto</th>
                            <th class="text-right">Prima</th>
                            <th class="text-right">Dopo</th>
                        </tr>
                        </thead>
                        <tbody><tr><td colspan="6" class="text-center text-muted">—</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Modifiche operatore --}}
    <div class="col-lg-4">
        <div class="panel panel-default">
            <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#tbl-modifiche">
                <i class="fa fa-pencil"></i> Modifiche Operatore
                <span class="badge pull-right" id="badge-modifiche">0</span>
            </div>
            <div class="panel-body collapse in" id="tbl-modifiche" style="padding:0;">
                <div class="table-responsive">
                    <table class="table table-condensed table-hover m-b-none" id="table-modifiche">
                        <thead>
                        <tr>
                            <th>Ora</th>
                            <th>Operatore</th>
                            <th>Tav.</th>
                            <th>Azione</th>
                        </tr>
                        </thead>
                        <tbody><tr><td colspan="4" class="text-center text-muted">—</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     SEZIONE MAGAZZINO
══════════════════════════════════════════════════════════ --}}
<div class="row m-t-md m-b-sm">
    <div class="col-lg-12">
        <h3 class="m-t-none">
            <i class="fa fa-cubes"></i> Andamento Magazzino
            <small class="text-muted" style="font-size:13px;">
                <i class="fa fa-clock-o"></i> Aggiornato ogni 5 minuti
            </small>
        </h3>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-md-6">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <div class="h1 m-t-none m-b-xs">{{ $stocks->count() }}</div>
                <div class="text-muted">Materiali Totali</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="panel panel-{{ $lowStockCount > 0 ? 'danger' : 'success' }}">
            <div class="panel-body text-center">
                <div class="h1 m-t-none m-b-xs">{{ $lowStockCount }}</div>
                <div class="text-muted">Sotto Soglia</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover m-b-none">
                        <thead>
                        <tr>
                            <th>Materiale</th>
                            <th class="text-center">Unità</th>
                            <th class="text-right">Importato</th>
                            <th class="text-right">Consumato</th>
                            <th class="text-right">Giacenza</th>
                            <th class="text-center">Stato</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($stocks as $stock)
                            <tr class="{{ $stock['is_low'] ? 'danger' : '' }}">
                                <td>
                                    <a href="{{ route('restaurant.materials.show', $stock['material']->id) }}" target="_blank">
                                        {{ $stock['material']->label }}
                                    </a>
                                </td>
                                <td class="text-center">{{ $stock['material']->stock_type_label }}</td>
                                <td class="text-right">{{ number_format($stock['imported'], 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format($stock['consumed'], 2, ',', '.') }}</td>
                                <td class="text-right">
                                    <strong class="{{ $stock['current'] < 0 ? 'text-danger' : '' }}">
                                        {{ number_format($stock['current'], 2, ',', '.') }}
                                    </strong>
                                </td>
                                <td class="text-center">
                                    @if($stock['material']->alert_threshold === null)
                                        <span class="label label-default">N/D</span>
                                    @elseif($stock['is_low'])
                                        <span class="label label-danger">
                                            <i class="fa fa-exclamation-triangle"></i> BASSO
                                        </span>
                                    @else
                                        <span class="label label-success">
                                            <i class="fa fa-check"></i> OK
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">Nessun materiale trovato</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('custom-script')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const weeklyLabels   = @json(array_column($weeklyTrend, 'label'));
    const weeklyRevenues = @json(array_column($weeklyTrend, 'revenue'));
    const weeklyOrders   = @json(array_column($weeklyTrend, 'orders'));

    const monthlyLabels   = @json(array_column($monthlyTrend, 'label'));
    const monthlyRevenues = @json(array_column($monthlyTrend, 'revenue'));
    const monthlyOrders   = @json(array_column($monthlyTrend, 'orders'));

    const commonOptions = (color1, color2) => ({
        responsive: true,
        interaction: { mode: 'index' },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.yAxisID === 'y'
                        ? ' € ' + ctx.raw.toLocaleString('it-IT', { minimumFractionDigits: 2 })
                        : ' ' + ctx.dataset.label + ': ' + ctx.raw
                }
            }
        },
        scales: {
            y: {
                type: 'linear',
                position: 'left',
                ticks: { callback: v => '€ ' + v.toLocaleString('it-IT') }
            },
            y1: {
                type: 'linear',
                position: 'right',
                grid: { drawOnChartArea: false },
                ticks: { stepSize: 1, precision: 0 }
            }
        }
    });

    const makeDatasets = (revenues, orders, barColor, lineColor) => ([
        {
            label: 'Fatturato (€)',
            data: revenues,
            backgroundColor: barColor,
            borderColor: barColor.replace('0.6', '1'),
            borderWidth: 1,
            yAxisID: 'y',
        },
        {
            label: 'Ordini',
            data: orders,
            type: 'line',
            borderColor: lineColor,
            backgroundColor: lineColor.replace('1)', '0.15)'),
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.3,
            yAxisID: 'y1',
        }
    ]);

    new Chart(document.getElementById('weeklyChart'), {
        type: 'bar',
        data: {
            labels: weeklyLabels,
            datasets: makeDatasets(weeklyRevenues, weeklyOrders,
                'rgba(54, 162, 235, 0.6)',
                'rgba(255, 99, 132, 1)'
            )
        },
        options: commonOptions()
    });

    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: monthlyLabels,
            datasets: makeDatasets(monthlyRevenues, monthlyOrders,
                'rgba(75, 192, 192, 0.6)',
                'rgba(255, 159, 64, 1)'
            )
        },
        options: commonOptions()
    });
})();

// ── Riepilogo Giornaliero ────────────────────────────────────────────────────
(function () {
    const fmt  = n => '€ ' + parseFloat(n).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dash = v => (v === undefined || v === null || v === '') ? '-' : v;

    function renderCancellazioni(logs) {
        if (!logs.length) return '<tr><td colspan="6" class="text-center text-muted">Nessuna cancellazione</td></tr>';
        return logs.map(r =>
            `<tr>
                <td>${dash(r.time)}</td>
                <td>${dash(r.operator)}</td>
                <td>${dash(r.table)}</td>
                <td>${dash(r.dish)}</td>
                <td class="text-center">${dash(r.qty)}</td>
                <td class="text-right">${r.price ? '€ ' + r.price : '-'}</td>
            </tr>`
        ).join('');
    }

    function renderPrezzi(logs) {
        if (!logs.length) return '<tr><td colspan="6" class="text-center text-muted">Nessuna modifica prezzo</td></tr>';
        return logs.map(r =>
            `<tr>
                <td>${dash(r.time)}</td>
                <td>${dash(r.operator)}</td>
                <td>${dash(r.table)}</td>
                <td>${dash(r.dish)}</td>
                <td class="text-right text-danger"><s>€ ${dash(r.price_before)}</s></td>
                <td class="text-right text-success"><strong>€ ${dash(r.price_after)}</strong></td>
            </tr>`
        ).join('');
    }

    function renderModifiche(logs) {
        if (!logs.length) return '<tr><td colspan="4" class="text-center text-muted">Nessuna modifica</td></tr>';
        return logs.map(r =>
            `<tr>
                <td>${dash(r.time)}</td>
                <td>${dash(r.operator)}</td>
                <td>${dash(r.table)}</td>
                <td><small>${dash(r.notes)}</small></td>
            </tr>`
        ).join('');
    }

    function loadDailyStats(date) {
        $('#btn-load-daily').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.get('{{ route("dashboard.daily-stats") }}', { date: date })
            .done(function (data) {
                $('#d-totale').text(fmt(data.totale));
                $('#d-media').text(fmt(data.media));
                $('#d-ordini').text(data.ordini);

                const nC = data.cancellazioni.count;
                const nP = data.modifiche_prezzo.count;
                const nM = data.modifiche_operatore.count;

                $('#d-cancellazioni').text(nC);
                $('#d-prezzi').text(nP);
                $('#d-modifiche').text(nM);

                $('#badge-cancellazioni').text(nC);
                $('#badge-prezzi').text(nP);
                $('#badge-modifiche').text(nM);

                $('#table-cancellazioni tbody').html(renderCancellazioni(data.cancellazioni.logs));
                $('#table-prezzi tbody').html(renderPrezzi(data.modifiche_prezzo.logs));
                $('#table-modifiche tbody').html(renderModifiche(data.modifiche_operatore.logs));

                const now = new Date();
                $('#daily-loaded-at').text(now.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' }));
                $('#daily-cache-note').show();
            })
            .fail(function () {
                toastr.error('Errore nel caricamento del riepilogo giornaliero');
            })
            .always(function () {
                $('#btn-load-daily').prop('disabled', false).html('<i class="fa fa-refresh"></i> Aggiorna');
            });
    }

    // Bottone invalida cache globale
    $(function () {
        $('#btn-clear-cache').on('click', function () {
            const btn = $(this);
            const date = $('#daily-date-picker').val();
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Aggiornamento...');
            $.post('{{ route("dashboard.clear-cache") }}', { _token: '{{ csrf_token() }}', date: date })
                .done(function () {
                    toastr.success('Cache invalidata, ricarico i dati...');
                    setTimeout(() => location.reload(), 800);
                })
                .fail(function () {
                    toastr.error('Errore durante l\'invalidazione della cache');
                    btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Aggiorna dati ora');
                });
        });
    });

    // Carica al primo avvio con la data di oggi
    $(function () {
        loadDailyStats($('#daily-date-picker').val());

        $('#btn-load-daily').on('click', function () {
            loadDailyStats($('#daily-date-picker').val());
        });

        $('#daily-date-picker').on('change', function () {
            loadDailyStats($(this).val());
        });
    });
})();
</script>
@endsection
