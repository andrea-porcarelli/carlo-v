@extends('backoffice.layout')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Storico Ordine Tavolo #{{ $tableOrder->restaurantTable->table_number }}
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('backoffice.logs.print-logs', $tableOrder->id) }}" class="btn btn-sm btn-info">
                            <i class="fas fa-print"></i> Log Stampe
                        </a>
                        <a href="{{ route('backoffice.logs.table-orders') }}" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> Torna ai log
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($tableOrder->covers == 0)
                    <!-- Banner Solo Bevande -->
                    <div class="alert alert-info alert-dismissible mb-4" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border: none;">
                        <div class="d-flex align-items-center">
                            <div class="mr-3">
                                <i class="fas fa-glass-cheers fa-3x text-white"></i>
                            </div>
                            <div>
                                <h4 class="alert-heading text-white mb-1">
                                    <i class="fas fa-info-circle"></i> Modalità Solo Bevande
                                </h4>
                                <p class="mb-0 text-white">
                                    Questo tavolo è stato aperto senza coperti - nessun coperto viene addebitato.
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Informazioni ordine -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-chair"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Tavolo</span>
                                    <span class="info-box-number">#{{ $tableOrder->restaurantTable->table_number }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-{{ $tableOrder->covers == 0 ? 'info' : 'success' }}">
                                    <i class="fas fa-{{ $tableOrder->covers == 0 ? 'glass-cheers' : 'users' }}"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">{{ $tableOrder->covers == 0 ? 'Modalità' : 'Coperti' }}</span>
                                    <span class="info-box-number">{{ $tableOrder->covers == 0 ? 'Consumo Bevande' : $tableOrder->covers }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-euro-sign"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Totale</span>
                                    <span class="info-box-number">{{ number_format($tableOrder->total_amount, 2) }}€</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-{{ $tableOrder->status == 'open' ? 'primary' : 'secondary' }}">
                                    <i class="fas fa-{{ $tableOrder->status == 'open' ? 'door-open' : 'door-closed' }}"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Stato</span>
                                    <span class="info-box-number">{{ ucfirst($tableOrder->status) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline dei log -->
                    <div class="timeline">
                        @php
                            function getTimelineIcon($action) {
                                $icons = [
                                    'create_order' => 'plus',
                                    'update_order' => 'edit',
                                    'delete_order' => 'trash',
                                    'add_item' => 'cart-plus',
                                    'update_item' => 'edit',
                                    'remove_item' => 'cart-arrow-down',
                                    'change_status' => 'exchange-alt',
                                    'update_covers' => 'users',
                                    'close_order' => 'lock',
                                    'reopen_order' => 'unlock'
                                ];
                                return $icons[$action] ?? 'circle';
                            }

                            function getTimelineColor($action) {
                                $colors = [
                                    'create_order' => 'success',
                                    'update_order' => 'info',
                                    'delete_order' => 'danger',
                                    'add_item' => 'success',
                                    'update_item' => 'info',
                                    'remove_item' => 'warning',
                                    'change_status' => 'primary',
                                    'update_covers' => 'info',
                                    'close_order' => 'secondary',
                                    'reopen_order' => 'primary'
                                ];
                                return $colors[$action] ?? 'secondary';
                            }
                        @endphp
                        @foreach($logs as $log)
                            <div>
                                <i class="fas fa-{{ getTimelineIcon($log->action) }} bg-{{ getTimelineColor($log->action) }}"></i>
                                <div class="timeline-item">
                                    <span class="time">
                                        <i class="fas fa-clock"></i> {{ $log->created_at->format('H:i:s') }}
                                    </span>
                                    <h3 class="timeline-header">
                                        <strong>{{ $log->user?->name ?? 'Sistema' }}</strong> - {{ $log->getActionDescription() }}
                                    </h3>
                                    <div class="timeline-body">
                                        <p>{{ $log->notes }}</p>

                                        @if($log->changes)
                                            <div class="card">
                                                <div class="card-header">
                                                    <h4 class="card-title">Modifiche</h4>
                                                </div>
                                                <div class="card-body p-0">
                                                    <table class="table table-sm mb-0">
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
                                                                    <td><span class="badge badge-danger">{{ $change['old'] ?? 'N/D' }}</span></td>
                                                                    <td><span class="badge badge-success">{{ $change['new'] ?? 'N/D' }}</span></td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        @endif

                                        @if($log->data_after && $log->action == 'add_item')
                                            <div class="mt-2">
                                                <strong>Prodotto:</strong> {{ $log->data_after['dish_name'] ?? 'N/D' }}<br>
                                                <strong>Quantità:</strong> {{ $log->data_after['quantity'] ?? 'N/D' }}<br>
                                                <strong>Prezzo:</strong> {{ number_format($log->data_after['price'] ?? 0, 2) }}€
                                            </div>
                                        @endif

                                        <div class="mt-2 text-muted">
                                            <small>
                                                <i class="fas fa-network-wired"></i> IP: {{ $log->ip_address }}<br>
                                                <i class="fas fa-calendar"></i> {{ $log->created_at->format('d/m/Y H:i:s') }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div>
                            <i class="fas fa-clock bg-gray"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
