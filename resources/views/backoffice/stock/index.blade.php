@extends('backoffice.layout', ['title' => 'Giacenze',])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Giacenze'],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-body">
                            <div class="stat-panel text-center">
                                <div class="stat-panel-number h1">{{ $stocks->count() }}</div>
                                <div class="stat-panel-title text-muted">Totale Materiali</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="panel panel-{{ $lowStockCount > 0 ? 'danger' : 'success' }}">
                        <div class="panel-body">
                            <div class="stat-panel text-center">
                                <div class="stat-panel-number h1">{{ $lowStockCount }}</div>
                                <div class="stat-panel-title text-muted">Sotto Soglia</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <form method="GET" action="{{ route('restaurant.stock.index') }}" class="form-inline" style="margin-bottom: 20px;">
                                <div class="form-group" style="margin-right: 15px;">
                                    <input type="text" name="search" class="form-control" placeholder="Cerca materiale..." value="{{ request('search') }}">
                                </div>
                                <div class="form-group" style="margin-right: 15px;">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="filter" value="low" {{ request('filter') === 'low' ? 'checked' : '' }} onchange="this.form.submit()">
                                        Solo sotto soglia
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Cerca</button>
                                <a href="{{ route('restaurant.stock.index') }}" class="btn btn-default"><i class="fa fa-times"></i> Reset</a>
                            </form>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Materiale</th>
                                        <th class="text-center">Unita</th>
                                        <th class="text-right">Importato</th>
                                        <th class="text-right">Consumato</th>
                                        <th class="text-right">Giacenza</th>
                                        <th class="text-center">Soglia Alert</th>
                                        <th class="text-center">Stato</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($stocks as $stock)
                                        <tr class="{{ $stock['is_low'] ? 'danger' : '' }}">
                                            <td>{{ $stock['material']->id }}</td>
                                            <td>
                                                <a href="{{ route('restaurant.materials.show', $stock['material']->id ) }}" target="_blank">
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
                                                <input type="number"
                                                       class="form-control input-sm threshold-input"
                                                       data-material-id="{{ $stock['material']->id }}"
                                                       value="{{ $stock['material']->alert_threshold }}"
                                                       placeholder="-"
                                                       step="0.01"
                                                       min="0"
                                                       style="width: 100px; display: inline-block;">
                                            </td>
                                            <td class="text-center">
                                                @if($stock['material']->alert_threshold === null)
                                                    <span class="label label-default">N/D</span>
                                                @elseif($stock['is_low'])
                                                    <span class="label label-danger blink-animation">
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
                                            <td colspan="8" class="text-center">Nessun materiale trovato</td>
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
    </div>
@endsection
@section('custom-style')
    <style>
        .blink-animation {
            animation: blink 1s ease-in-out infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .threshold-input {
            text-align: right;
        }
    </style>
@endsection
@section('custom-script')
    <script>
        $(document).ready(function(){
            let saveTimeout;

            $('.threshold-input').on('change keyup', function() {
                const input = $(this);
                const materialId = input.data('material-id');
                const value = input.val();

                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    $.ajax({
                        url: '/backoffice/restaurant/stock/' + materialId + '/threshold',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            alert_threshold: value || null
                        },
                        success: function(response) {
                            toastr.success('Soglia aggiornata');
                            setTimeout(function() {
                                location.reload();
                            }, 500);
                        },
                        error: function() {
                            toastr.error('Errore durante il salvataggio');
                        }
                    });
                }, 500);
            });
        });
    </script>
@endsection
