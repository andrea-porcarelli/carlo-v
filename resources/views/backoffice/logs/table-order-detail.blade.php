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

                    @if($tableOrder->autoconsumo)
                    <!-- Autoconsumo Banner & Breakdown -->
                    <div class="alert mb-4" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%); border: none; border-radius: 8px;">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-eraser fa-2x text-white mr-3"></i>
                            <div>
                                <h5 class="text-white mb-0 font-weight-bold">
                                    <i class="fas fa-eraser mr-1"></i> Tavolo in Autoconsumo
                                </h5>
                                @php
                                    $itemsWithUser = $tableOrder->items->filter(fn($i) => $i->autoconsumo_user_id);
                                    $grouped = $itemsWithUser->groupBy('autoconsumo_user_id');
                                @endphp
                                <small class="text-white-50">
                                    @if($itemsWithUser->isEmpty())
                                        Autoconsumo completo — nessuna assegnazione per operatore
                                    @else
                                        Autoconsumo parziale — {{ $itemsWithUser->count() }}/{{ $tableOrder->items->count() }} piatti assegnati per operatore
                                    @endif
                                </small>
                            </div>
                        </div>

                        @if($itemsWithUser->isNotEmpty())
                        <div class="mt-3">
                            <table class="table table-sm table-bordered mb-0" style="background:white; border-radius:6px; overflow:hidden;">
                                <thead style="background:#343a40; color:white;">
                                    <tr>
                                        <th style="width:30%;">Operatore</th>
                                        <th>Piatti assegnati</th>
                                        <th style="width:100px; text-align:right;">Totale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($grouped as $userId => $userItems)
                                    @php $userName = $userItems->first()->autoconsumoUser?->name ?? "Utente #{$userId}"; @endphp
                                    <tr>
                                        <td class="font-weight-bold">{{ $userName }}</td>
                                        <td>
                                            @foreach($userItems as $item)
                                                <span class="badge badge-secondary mr-1">
                                                    {{ $item->dish->name ?? 'N/D' }} x{{ $item->quantity }}
                                                </span>
                                            @endforeach
                                        </td>
                                        <td style="text-align:right; font-weight:bold;">
                                            €{{ number_format($userItems->sum('subtotal'), 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                    @php
                                        $unassignedItems = $tableOrder->items->filter(fn($i) => !$i->autoconsumo_user_id);
                                    @endphp
                                    @if($unassignedItems->isNotEmpty())
                                    <tr class="table-light">
                                        <td class="text-muted font-italic">Non assegnati</td>
                                        <td>
                                            @foreach($unassignedItems as $item)
                                                <span class="badge badge-light mr-1">
                                                    {{ $item->dish->name ?? 'N/D' }} x{{ $item->quantity }}
                                                </span>
                                            @endforeach
                                        </td>
                                        <td style="text-align:right; color:#6c757d;">
                                            €{{ number_format($unassignedItems->sum('subtotal'), 2) }}
                                        </td>
                                    </tr>
                                    @endif
                                </tbody>
                                <tfoot style="background:#f8f9fa;">
                                    <tr>
                                        <td colspan="2" class="font-weight-bold">TOTALE AUTOCONSUMO</td>
                                        <td style="text-align:right; font-weight:bold;">
                                            €{{ number_format($tableOrder->items->sum('subtotal'), 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        @endif
                    </div>
                    @endif

                    <!-- Sconto applicato all'ordine -->
                    @if($tableOrder->hasDiscount())
                    @php
                        $discountLabel = $tableOrder->discount_type === 'percent'
                            ? number_format((float) $tableOrder->discount_amount, 0) . '%'
                            : '€' . number_format((float) $tableOrder->discount_amount, 2);
                        $discountedTotal = $tableOrder->getDiscountedTotal();
                    @endphp
                    <div class="alert mb-4" style="background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); border: none; border-radius: 8px;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-tag fa-2x text-white mr-3"></i>
                            <div class="flex-grow-1">
                                <h5 class="text-white mb-1 font-weight-bold">
                                    Sconto applicato: {{ $discountLabel }}
                                </h5>
                                <small class="text-white-50 d-block">
                                    Sconto €{{ number_format((float) $tableOrder->discount_value, 2) }}
                                    — Totale scontato: <strong>€{{ number_format($discountedTotal, 2) }}</strong>
                                    (originale €{{ number_format((float) $tableOrder->total_amount, 2) }})
                                </small>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Corrispettivi elettronici -->
                    @if($tableOrder->corrispettivi->isNotEmpty())
                    @php
                        $statusBadge = [
                            'pending'   => 'secondary',
                            'sending'   => 'info',
                            'sent'      => 'success',
                            'failed'    => 'danger',
                            'cancelled' => 'dark',
                        ];
                    @endphp
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h3 class="card-title">
                                <i class="fas fa-receipt"></i> Corrispettivi elettronici
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Data</th>
                                        <th>Preconto</th>
                                        <th>Pagamento</th>
                                        <th class="text-right">Totale</th>
                                        <th>Progressivo</th>
                                        <th>ID Transazione</th>
                                        <th>Stato</th>
                                        <th>Tentativi</th>
                                        <th>Operatore</th>
                                        <th class="text-right">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tableOrder->corrispettivi as $c)
                                    <tr>
                                        <td>
                                            @if($c->isAnnullo())
                                                <span class="badge badge-dark">Annullo</span>
                                                @if($c->emissioneAnnullata)
                                                    <small class="text-muted d-block">di #{{ $c->emissioneAnnullata->id }}</small>
                                                @endif
                                            @else
                                                <span class="badge badge-primary">Emissione</span>
                                            @endif
                                        </td>
                                        <td>{{ ($c->sent_at ?? $c->created_at)->format('d/m/Y H:i:s') }}</td>
                                        <td>{{ $c->precontoSplit?->label ?? '—' }}</td>
                                        <td>{{ $c->payment_method }}</td>
                                        <td class="text-right">€{{ number_format((float)$c->importo_totale, 2) }}</td>
                                        <td>
                                            @if($c->progressivo_sdi)
                                                <code>{{ $c->progressivo_sdi }}</code>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($c->identificativo_sdi)
                                                <code>{{ $c->identificativo_sdi }}</code>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $statusBadge[$c->status] ?? 'secondary' }}">
                                                {{ $c->getStatusLabel() }}
                                            </span>
                                            @if($c->last_error)
                                                <i class="fas fa-exclamation-triangle text-danger ml-1"
                                                   title="{{ $c->last_error }}"></i>
                                            @endif
                                        </td>
                                        <td>{{ $c->attempts }}/{{ $c->max_attempts }}</td>
                                        <td>{{ $c->operator?->name ?? '—' }}</td>
                                        <td class="text-right">
                                            @if($c->canRetry())
                                                <form action="{{ route('backoffice.corrispettivi.riprova', $c->id) }}"
                                                      method="POST" style="display:inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-warning"
                                                            onclick="return confirm('Riprovare l\'invio del corrispettivo?');">
                                                        <i class="fas fa-redo"></i> Riprova
                                                    </button>
                                                </form>
                                            @endif
                                            @if($c->canCancel())
                                                <form action="{{ route('backoffice.corrispettivi.annulla', $c->id) }}"
                                                      method="POST" style="display:inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                            onclick="return confirm('Annullare il corrispettivo {{ $c->progressivo_sdi }}? L\'operazione è irreversibile.');">
                                                        <i class="fas fa-ban"></i> Annulla
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

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

                                        @if($log->action == 'remove_item' && !empty($log->data_before['removal_reason']))
                                            <div class="mt-2">
                                                <span class="badge badge-warning" style="font-size:0.85rem; padding:5px 10px;">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    Motivo: {{ $log->data_before['removal_reason'] }}
                                                </span>
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
