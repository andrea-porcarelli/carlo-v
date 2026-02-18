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
    <div class="col-lg-12">
        <h3 class="m-t-none">
            <i class="fa fa-line-chart"></i> Andamento Finanziario
            <small class="text-muted" style="font-size:13px;">
                <i class="fa fa-clock-o"></i> Aggiornato ogni 5 minuti &mdash; Autoconsumo escluso
            </small>
        </h3>
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

{{-- Top dishes + Miglior scontrino dettaglio --}}
<div class="row">
    <div class="{{ $bestReceipt ? 'col-lg-7' : 'col-lg-12' }}">
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

    @if($bestReceipt)
    <div class="col-lg-5">
        <div class="panel panel-warning">
            <div class="panel-heading">
                <i class="fa fa-trophy"></i> Dettaglio Miglior Scontrino
            </div>
            <div class="panel-body">
                <table class="table table-condensed m-b-none">
                    <tr>
                        <td><strong>Tavolo</strong></td>
                        <td>{{ $bestReceipt->restaurantTable?->table_number ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Data</strong></td>
                        <td>{{ $bestReceipt->closed_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Cameriere</strong></td>
                        <td>{{ $bestReceipt->waiter?->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td><strong>N. Prodotti</strong></td>
                        <td>{{ $bestReceipt->items->count() }}</td>
                    </tr>
                </table>
                <div class="text-center m-t-sm m-b-sm">
                    <div class="h2 text-warning m-none">
                        € {{ number_format($bestReceipt->total_amount, 2, ',', '.') }}
                    </div>
                </div>
                <hr style="margin: 8px 0;">
                <strong><i class="fa fa-list"></i> Prodotti ordinati:</strong>
                <ul class="list-unstyled m-t-sm m-b-none" style="max-height:200px; overflow-y:auto;">
                    @foreach($bestReceipt->items as $item)
                    <li style="padding: 3px 0; border-bottom: 1px solid #f5f5f5;">
                        <i class="fa fa-cutlery text-muted"></i>
                        {{ $item->dish?->label ?? 'N/A' }}
                        <span class="badge" style="margin-left:4px;">x{{ $item->quantity }}</span>
                        <span class="pull-right text-muted">€ {{ number_format($item->subtotal, 2, ',', '.') }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif
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
</script>
@endsection
