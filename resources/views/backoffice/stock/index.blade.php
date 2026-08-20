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
                                <input type="hidden" name="sort" value="{{ $sort }}">
                                <input type="hidden" name="direction" value="{{ $direction }}">
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
                                    @php
                                        $sortCols = [
                                            'imported' => 'Importato',
                                            'consumed' => 'Consumato',
                                            'current'  => 'Giacenza',
                                        ];
                                    @endphp
                                    <tr>
                                        <th>#</th>
                                        <th>Materiale</th>
                                        <th class="text-center">Unita</th>
                                        @foreach($sortCols as $col => $label)
                                            @php
                                                $isActive = $sort === $col;
                                                $nextDir = ($isActive && $direction === 'desc') ? 'asc' : 'desc';
                                                $href = request()->fullUrlWithQuery(['sort' => $col, 'direction' => $nextDir]);
                                            @endphp
                                            <th class="text-right">
                                                <a href="{{ $href }}" class="sort-link {{ $isActive ? 'sort-active' : '' }}">
                                                    {{ $label }}
                                                    @if($isActive)
                                                        <i class="fa fa-sort-{{ $direction === 'asc' ? 'asc' : 'desc' }}"></i>
                                                    @else
                                                        <i class="fa fa-sort text-muted"></i>
                                                    @endif
                                                </a>
                                            </th>
                                        @endforeach
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
                                                <hr style="margin: 5px 0" />
                                                <button
                                                    class="btn btn-xs btn-warning btn-add-stock"
                                                    title="Aggiungi quantità"
                                                    data-id="{{ $stock['material']->id }}"
                                                    data-label="{{ $stock['material']->label }}"
                                                    data-stock-type="{{ $stock['material']->stock_type }}"
                                                >
                                                    <i class="fa fa-plus-circle"></i> Aggiungi quantità
                                                </button>
                                            </td>
                                            <td class="text-center">{{ $stock['material']->stock_type_label }}</td>
                                            <td class="text-right">
                                                {{ number_format($stock['imported'], 2, ',', '.') }}
                                                <small class="text-muted">{{ $stock['material']->stock_type }}</small>
                                            </td>
                                            <td class="text-right">
                                                {{ number_format($stock['consumed'], 2, ',', '.') }}
                                                <small class="text-muted">{{ $stock['material']->stock_type }}</small>
                                            </td>
                                            <td class="text-right">
                                                <strong class="{{ $stock['current'] < 0 ? 'text-danger' : '' }}">
                                                    {{ number_format($stock['current'], 2, ',', '.') }}
                                                </strong>
                                                <small class="text-muted">{{ $stock['material']->stock_type }}</small>
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
                                                @if(!$stock['material']->track_stock)
                                                    <span class="label label-default" title="Giacenza non tracciata: escluso dagli avvisi Telegram">
                                                        <i class="fa fa-bell-slash"></i> Non tracciato
                                                    </span>
                                                @elseif($stock['material']->alert_threshold === null)
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
    <x-load-material-modal />
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
        .sort-link {
            color: inherit;
            text-decoration: none;
            white-space: nowrap;
        }
        .sort-link:hover {
            text-decoration: none;
            color: inherit;
        }
        .sort-active {
            font-weight: bold;
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
