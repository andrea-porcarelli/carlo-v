@extends('backoffice.layout')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Log Operazioni Tavoli</h3>
                    <div class="card-tools">
                        <a href="{{ route('backoffice.logs.export', request()->query()) }}" class="btn btn-sm btn-success">
                            <i class="fas fa-download"></i> Esporta CSV
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filtri -->
                    <form method="GET" action="{{ route('backoffice.logs.table-orders') }}" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Operatore</label>
                                    <select name="user_id" class="form-control">
                                        <option value="">Tutti gli operatori</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Azione</label>
                                    <select name="action" class="form-control">
                                        <option value="">Tutte le azioni</option>
                                        @foreach($actions as $action)
                                            <option value="{{ $action }}" {{ request('action') == $action ? 'selected' : '' }}>
                                                {{ ucfirst(str_replace('_', ' ', $action)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Data Da</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Data A</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-filter"></i> Filtra
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Tabella log -->
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Data/Ora</th>
                                    <th>Operatore</th>
                                    <th>Azione</th>
                                    <th>Tavolo</th>
                                    <th>Dettagli</th>
                                    <th>Note</th>
                                    <th>IP</th>
                                    <th>Modifiche</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>
                                            <small>{{ $log->created_at->format('d/m/Y H:i:s') }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $log->user?->name ?? 'N/D' }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $log->action == 'delete_order' || $log->action == 'remove_item' ? 'danger' : ($log->action == 'create_order' || $log->action == 'add_item' ? 'success' : 'info') }}">
                                                {{ $log->getActionDescription() }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($log->tableOrder)
                                                <a href="{{ route('backoffice.logs.table-order', $log->tableOrder->id) }}">
                                                    Tavolo #{{ $log->tableOrder->restaurantTable->table_number ?? 'N/D' }}
                                                </a>
                                            @else
                                                N/D
                                            @endif
                                        </td>
                                        <td>
                                            @if($log->orderItem)
                                                {{ $log->orderItem->dish?->name ?? 'N/D' }}
                                                @if($log->data_after && isset($log->data_after['quantity']))
                                                    (x{{ $log->data_after['quantity'] }})
                                                @endif
                                            @elseif($log->data_after && isset($log->data_after['covers']))
                                                {{ $log->data_after['covers'] }} coperti
                                            @endif
                                        </td>
                                        <td>
                                            <small>{{ $log->notes }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $log->ip_address }}</small>
                                        </td>
                                        <td>
                                            @if($log->changes)
                                                <button type="button" class="btn btn-xs btn-info" data-toggle="modal" data-target="#changesModal{{ $log->id }}">
                                                    <i class="fas fa-eye"></i> Vedi
                                                </button>

                                                <!-- Modal per le modifiche -->
                                                <div class="modal fade" id="changesModal{{ $log->id }}" tabindex="-1" role="dialog">
                                                    <div class="modal-dialog" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Modifiche Effettuate</h5>
                                                                <button type="button" class="close" data-dismiss="modal">
                                                                    <span>&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Campo</th>
                                                                            <th>Prima</th>
                                                                            <th>Dopo</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($log->getFormattedChanges() as $change)
                                                                            <tr>
                                                                                <td><strong>{{ $change['field'] }}</strong></td>
                                                                                <td><del class="text-danger">{{ $change['old'] ?? 'N/D' }}</del></td>
                                                                                <td><ins class="text-success">{{ $change['new'] ?? 'N/D' }}</ins></td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">Nessun log trovato</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginazione -->
                    <div class="mt-3">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
