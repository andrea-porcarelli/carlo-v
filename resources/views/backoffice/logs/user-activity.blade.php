@extends('backoffice.layout')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Attività di {{ $user->name }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('backoffice.logs.table-orders') }}" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> Torna ai log
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filtro periodo -->
                    <form method="GET" action="{{ route('backoffice.logs.user', $user->id) }}" class="mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Data Da</label>
                                    <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Data A</label>
                                    <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-filter"></i> Filtra
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Statistiche -->
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3>{{ $stats['total_actions'] }}</h3>
                                    <p>Totale Azioni</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3>{{ $stats['orders_created'] }}</h3>
                                    <p>Ordini Creati</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="small-box bg-primary">
                                <div class="inner">
                                    <h3>{{ $stats['items_added'] }}</h3>
                                    <p>Prodotti Aggiunti</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-cart-plus"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3>{{ $stats['items_modified'] }}</h3>
                                    <p>Prodotti Modificati</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-edit"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3>{{ $stats['items_removed'] }}</h3>
                                    <p>Prodotti Rimossi</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-trash"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="small-box bg-secondary">
                                <div class="inner">
                                    <h3>{{ $stats['actions_by_type']->count() }}</h3>
                                    <p>Tipi di Azioni</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-list"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grafico azioni per tipo -->
                    @if($stats['actions_by_type']->isNotEmpty())
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Distribuzione Azioni</h3>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="actionsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Riepilogo per Tipo</h3>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Tipo Azione</th>
                                                    <th class="text-right">Conteggio</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($stats['actions_by_type']->sortByDesc(fn($count) => $count) as $action => $count)
                                                    <tr>
                                                        <td>{{ ucfirst(str_replace('_', ' ', $action)) }}</td>
                                                        <td class="text-right"><strong>{{ $count }}</strong></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Ultimi log -->
                    <h4 class="mb-3">Ultime Attività</h4>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Data/Ora</th>
                                    <th>Azione</th>
                                    <th>Tavolo</th>
                                    <th>Dettagli</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td><small>{{ $log->created_at->format('d/m/Y H:i:s') }}</small></td>
                                        <td>
                                            <span class="badge badge-{{ $log->action == 'delete_order' || $log->action == 'remove_item' ? 'danger' : ($log->action == 'create_order' || $log->action == 'add_item' ? 'success' : 'info') }}">
                                                {{ $log->getActionDescription() }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($log->tableOrder)
                                                <a href="{{ route('backoffice.logs.table-order', $log->tableOrder->id) }}">
                                                    #{{ $log->tableOrder->restaurantTable->table_number ?? 'N/D' }}
                                                </a>
                                            @else
                                                N/D
                                            @endif
                                        </td>
                                        <td>
                                            @if($log->orderItem)
                                                {{ $log->orderItem->dish?->name ?? 'N/D' }}
                                            @endif
                                        </td>
                                        <td><small>{{ $log->notes }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">Nessuna attività trovata</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
@if($stats['actions_by_type']->isNotEmpty())
    const ctx = document.getElementById('actionsChart');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($stats['actions_by_type']->keys()->map(fn($action) => ucfirst(str_replace('_', ' ', $action)))) !!},
            datasets: [{
                data: {!! json_encode($stats['actions_by_type']->values()) !!},
                backgroundColor: [
                    '#28a745',
                    '#17a2b8',
                    '#dc3545',
                    '#ffc107',
                    '#6c757d',
                    '#007bff',
                    '#fd7e14',
                    '#20c997',
                    '#e83e8c',
                ],
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
@endif
</script>
@endpush
@endsection
