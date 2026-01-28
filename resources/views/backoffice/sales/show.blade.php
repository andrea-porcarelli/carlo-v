@extends('backoffice.layout', ['title' => 'Dettaglio Vendita #' . $sale->id])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Vendite', 'url' => route('restaurant.sales.index')],
        'level_2' => ['label' => 'Dettaglio Vendita #' . $sale->id],
    ])
@endsection
@section('main-content')
    @if($sale->covers == 0)
    <!-- Banner Solo Bevande -->
    <div class="row">
        <div class="col-xs-12">
            <div class="alert alert-info" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border: none; color: white; margin-bottom: 20px;">
                <div class="d-flex align-items-center" style="display: flex; align-items: center;">
                    <div style="margin-right: 15px;">
                        <i class="fa fa-glass-cheers fa-3x"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 5px 0; font-weight: bold;">
                            <i class="fa fa-info-circle"></i> Modalita Solo Bevande
                        </h4>
                        <p style="margin: 0;">
                            Questo tavolo e stato aperto senza coperti - nessun coperto e stato addebitato.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <!-- Sale Info Card -->
        <div class="col-lg-4">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fas fa-info-circle"></i> Informazioni Vendita
                    </h4>
                </div>
                <div class="panel-body">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td><strong>ID Vendita:</strong></td>
                                <td>#{{ $sale->id }}</td>
                            </tr>
                            <tr>
                                <td><strong>Tavolo:</strong></td>
                                <td>
                                    <span class="badge badge-primary" style="font-size: 14px;">
                                        Tavolo {{ $sale->restaurantTable->table_number }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Stato:</strong></td>
                                <td>
                                    <span class="badge badge-{{ $sale->getStatusLevel() }}">
                                        <i class="fas {{ $sale->getStatusIcon() }}"></i> {{ $sale->getStatusLabel() }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Data Apertura:</strong></td>
                                <td>{{ $sale->opened_at->format('d/m/Y H:i:s') }}</td>
                            </tr>
                            @isset($sale->closed_at)
                            <tr>
                                <td><strong>Data Chiusura:</strong></td>
                                <td>{{ $sale->closed_at->format('d/m/Y H:i:s') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Durata:</strong></td>
                                <td>
                                    <strong>{{ $sale->opened_at->diffInMinutes($sale->closed_at) }} minuti</strong>
                                    <br>
                                    <small class="text-muted">
                                        ({{ $sale->opened_at->diffForHumans($sale->closed_at, true) }})
                                    </small>
                                </td>
                            </tr>
                            @endisset
                            <tr>
                                <td><strong>Cameriere:</strong></td>
                                <td>
                                    @if($sale->waiter)
                                        <i class="fas fa-user"></i> {{ $sale->waiter->name }}
                                    @else
                                        <em class="text-muted">Non specificato</em>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Total Card -->
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fas fa-euro-sign"></i> Totale Vendita
                    </h4>
                </div>
                <div class="panel-body text-center">
                    <h1 class="@if($sale->status == 'cancelled') text-danger @else text-success @endif mb-0 @if($sale->status == 'cancelled') trashed @endif">
                        <strong>€{{ number_format($sale->total_amount, 2, ',', '.') }}</strong>
                    </h1>
                    <p class="text-muted mb-0 @if($sale->status == 'cancelled') trashed @endif">
                        {{ $sale->items()->withTrashed()->get()->count() }} prodotti
                    </p>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="col-lg-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fas fa-shopping-cart"></i> Prodotti Ordinati
                    </h4>
                </div>
                <div class="panel-body">
                    @if($sale->items()->withTrashed()->get()->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Prodotto</th>
                                        <th width="80" class="text-center">Qta</th>
                                        <th width="120" class="text-end">Prezzo Unit.</th>
                                        <th width="120" class="text-end">Subtotale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sale->items()->withTrashed()->orderBy('id', 'DESC')->get() as $index => $item)
                                        <tr class="@if($sale->status == 'cancelled') trashed @endif">
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                @php
                                                    $originalPrice = $item->dish->price ?? $item->unit_price;
                                                    $hasPriceChange = abs($item->unit_price - $originalPrice) > 0.001;
                                                @endphp
                                                <div>
                                                    <strong style="font-size: 15px;">{{ $item->dish->label }}</strong>
                                                    @if($hasPriceChange)
                                                        <span class="badge badge-warning ml-2" title="Prezzo modificato: da €{{ number_format($originalPrice, 2, ',', '.') }} a €{{ number_format($item->unit_price, 2, ',', '.') }}">
                                                            <i class="fas fa-euro-sign"></i> Modificato
                                                        </span>
                                                    @endif
                                                    @if($item->dish->category)
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-tag"></i> {{ $item->dish->category->label }}
                                                        </small>
                                                    @endif
                                                </div>

                                                <!-- Extras -->
                                                @if($item->extras && is_array($item->extras) && count($item->extras) > 0)
                                                    <div class="mt-2">
                                                        <span class="badge badge-info">
                                                            <i class="fas fa-plus-circle"></i> Supplementi
                                                        </span>
                                                        <ul class="list-unstyled mb-0 mt-1" style="padding-left: 15px;">
                                                            @foreach($item->extras as $extraName => $extraPrice)
                                                                <li class="text-success">
                                                                    <i class="fas fa-check"></i>
                                                                    <strong>{{ $extraName }}</strong>
                                                                    <span class="text-muted">(+€{{ number_format($extraPrice, 2) }})</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif

                                                <!-- Removals -->
                                                @if($item->removals && is_array($item->removals) && count($item->removals) > 0)
                                                    <div class="mt-2">
                                                        <span class="badge badge-warning">
                                                            <i class="fas fa-minus-circle"></i> Rimozioni
                                                        </span>
                                                        <ul class="list-unstyled mb-0 mt-1" style="padding-left: 15px;">
                                                            @foreach($item->removals as $removal)
                                                                <li class="text-danger">
                                                                    <i class="fas fa-times"></i>
                                                                    {{ $removal }}
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif

                                                <!-- Notes -->
                                                @if($item->notes)
                                                    <div class="mt-2">
                                                        <span class="badge badge-secondary">
                                                            <i class="fas fa-sticky-note"></i> Note
                                                        </span>
                                                        <div class="alert alert-warning mt-1 mb-0 p-2">
                                                            <i class="fas fa-comment-dots"></i>
                                                            <em>{{ $item->notes }}</em>
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Allergens -->
                                                @if($item->dish->allergens && $item->dish->allergens->count() > 0)
                                                    <div class="mt-2">
                                                        <span class="badge badge-danger">
                                                            <i class="fas fa-exclamation-triangle"></i> Allergeni
                                                        </span>
                                                        <div style="padding-left: 15px; margin-top: 5px;">
                                                            @foreach($item->dish->allergens as $allergen)
                                                                <span class="badge badge-danger mr-1">
                                                                    {{ $allergen->label }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <strong style="font-size: 16px;">{{ $item->quantity }}</strong>
                                            </td>
                                            <td class="text-end" style="font-weight: bold">
                                                @php
                                                    $originalPrice = $item->dish->price ?? $item->unit_price;
                                                    $hasPriceChange = abs($item->unit_price - $originalPrice) > 0.001;
                                                @endphp
                                                @if($hasPriceChange)
                                                     €{{ number_format($item->unit_price, 2, ',', '.') }}
                                                <hr style="margin: 5px 0"/>
                                                    <span class="badge badge-danger" style="text-decoration: line-through; font-size: 12px;">
                                                        <i class="fas fa-edit"></i> €{{ number_format($originalPrice, 2, ',', '.') }}
                                                    </span>
                                                    <br>
                                                    <small class="text-muted" style="font-weight: normal; font-size: 11px;">
                                                        @if($item->addedBy)
                                                            <i class="fas fa-user"></i> {{ $item->addedBy->name }}
                                                        @endif
                                                        <br>
                                                        <i class="fas fa-clock"></i> {{ $item->created_at->format('H:i') }}
                                                    </small>
                                                @else
                                                    €{{ number_format($item->unit_price, 2, ',', '.') }}
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <strong style="font-size: 15px;">
                                                    €{{ number_format($item->subtotal, 2, ',', '.') }}
                                                </strong>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-success">
                                        <td colspan="4" class="text-end">
                                            <strong style="font-size: 16px;" class="@if($sale->status == 'cancelled') trashed @endif">TOTALE:</strong>
                                        </td>
                                        <td class="text-end">
                                            <strong style="font-size: 18px;"  class="@if($sale->status == 'cancelled') text-danger @else text-success @endif @if($sale->status == 'cancelled') trashed @endif">
                                                €{{ number_format($sale->total_amount, 2, ',', '.') }}
                                            </strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle"></i>
                            Nessun prodotto trovato per questa vendita.
                        </div>
                    @endif
                </div>
                <div class="panel-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="{{ route('restaurant.sales.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Torna alle Vendite
                        </a>
                        <div>
                            <button class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Stampa
                            </button>
                            <button class="btn btn-success" onclick="alert('Funzionalità in sviluppo')">
                                <i class="fas fa-file-pdf"></i> Esporta PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Activity Log -->
    <div class="row mt-4">
        <div class="col-xs-12">
            <div class="panel panel-info">
                <div class="panel-heading" style="display: flex; justify-content: space-between; align-items: center;">
                    <h4 class="panel-title" style="margin: 0;">
                        <i class="fa fa-history"></i> Storico Operazioni
                        <span class="label label-default" style="margin-left: 8px;">{{ $logs->count() }} operazioni</span>
                    </h4>
                    <button type="button" class="btn btn-sm btn-warning" onclick="toggleModal('printHistoryModal')">
                        <i class="fa fa-print"></i> Stampa Storico
                    </button>
                </div>
                <div class="panel-body p-0">
                    @if($logs && $logs->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-condensed table-hover table-striped">
                                <thead>
                                    <tr class="active">
                                        <th width="140">Data/Ora</th>
                                        <th width="180">Azione</th>
                                        <th width="150">Operatore</th>
                                        <th>Dettagli</th>
                                        <th width="100" class="text-center">Modifiche</th>
                                        <th width="120">IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        @php
                                            // Estrai il nome del piatto dal log
                                            $dishName = null;
                                            $dishOriginalPrice = null;

                                            if ($log->data_after && isset($log->data_after['dish_name'])) {
                                                $dishName = $log->data_after['dish_name'];
                                            } elseif ($log->data_before && isset($log->data_before['dish_name'])) {
                                                $dishName = $log->data_before['dish_name'];
                                            } elseif ($log->orderItem && $log->orderItem->dish) {
                                                $dishName = $log->orderItem->dish->label ?? $log->orderItem->dish->name ?? null;
                                            }

                                            // Estrai i dati del prodotto
                                            $itemData = null;
                                            if (in_array($log->action, ['add_item', 'update_item']) && $log->data_after) {
                                                $itemData = $log->data_after;
                                            } elseif ($log->action === 'remove_item' && $log->data_before) {
                                                $itemData = $log->data_before;
                                            }

                                            // Ottieni il prezzo originale del piatto
                                            // 1. Prima dai dati del log (dish_price)
                                            if ($itemData && isset($itemData['dish_price'])) {
                                                $dishOriginalPrice = $itemData['dish_price'];
                                            }
                                            // 2. Fallback: dal piatto attuale nel database
                                            if ($dishOriginalPrice === null && $log->orderItem && $log->orderItem->dish) {
                                                $dishOriginalPrice = $log->orderItem->dish->price ?? null;
                                            }

                                            // Verifica se il prezzo è stato modificato
                                            $logHasPriceChange = false;
                                            // 1. Prima controlla il flag price_modified nei dati del log
                                            if ($itemData && isset($itemData['price_modified']) && $itemData['price_modified']) {
                                                $logHasPriceChange = true;
                                            }
                                            // 2. Fallback: calcola dal confronto prezzi
                                            elseif ($itemData && isset($itemData['unit_price']) && $dishOriginalPrice !== null) {
                                                $logHasPriceChange = abs(floatval($itemData['unit_price']) - floatval($dishOriginalPrice)) > 0.001;
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                <small class="text-nowrap">
                                                    {{ $log->created_at->format('d/m/Y') }}<br>
                                                    <strong>{{ $log->created_at->format('H:i:s') }}</strong>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="label label-{{ $log->getActionBadgeClass($log->action) }}">
                                                    <i class="fa fa-{{ $log->getActionIcon($log->action) }}"></i>
                                                    {{ $log->getActionDescription() }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($log->user)
                                                    <i class="fa fa-user"></i> {{ $log->user->name }}
                                                @else
                                                    <em class="text-muted">Sistema</em>
                                                @endif
                                            </td>
                                            <td>
                                                @if($dishName)
                                                    <strong class="text-primary">{{ $dishName }}</strong>
                                                @endif

                                                @if($itemData)
                                                    @if(isset($itemData['quantity']))
                                                        <br><strong>{{ $itemData['quantity'] }}x</strong>
                                                    @endif
                                                    @if(isset($itemData['unit_price']))
                                                        @if($logHasPriceChange)
                                                            <b>{{ Utils::price($itemData['unit_price']) }} </b>
                                                            <small style="text-decoration: line-through;" class="text-danger">(€{{ Utils::price($dishOriginalPrice) }})</small>
                                                        @else
                                                            <strong>€{{ Utils::price($itemData['unit_price']) }}</strong>
                                                        @endif
                                                    @endif
                                                    @if(isset($itemData['subtotal']))
                                                         = <strong>€{{ Utils::price($itemData['subtotal']) }}</strong>
                                                    @endif
                                                    @if(isset($itemData['notes']) && $itemData['notes'])
                                                        <br><small class="text-muted"><i class="fa fa-sticky-note"></i> {{ $itemData['notes'] }}</small>
                                                    @endif
                                                @else
                                                    <br />
                                                    <small class="text-muted">{{ $log->notes }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($log->changes && count($log->changes) > 0)
                                                    <button type="button" class="btn btn-xs btn-info" onclick="toggleModal('changesModal_{{ $log->id }}')" title="Visualizza modifiche">
                                                        <i class="fa fa-exchange"></i> {{ count($log->changes) }}
                                                    </button>

                                                    <!-- Modal Modifiche -->
                                                    <div id="changesModal_{{ $log->id }}" class="log-modal" onclick="if(event.target === this) toggleModal('changesModal_{{ $log->id }}')">
                                                        <div class="log-modal-content">
                                                            <div class="log-modal-header">
                                                                <h5>
                                                                    <i class="fa fa-exchange"></i> Modifiche Effettuate
                                                                </h5>
                                                                <button type="button" onclick="toggleModal('changesModal_{{ $log->id }}')" class="log-modal-close">
                                                                    &times;
                                                                </button>
                                                            </div>
                                                            <div class="log-modal-body">
                                                                @php
                                                                    $formattedChanges = $log->getFormattedChanges();
                                                                @endphp

                                                                @if(count($formattedChanges) > 0)
                                                                    <table class="table table-condensed table-bordered">
                                                                        <thead>
                                                                            <tr class="active">
                                                                                <th width="30%">Campo</th>
                                                                                <th width="35%">Prima</th>
                                                                                <th width="35%">Dopo</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @foreach($formattedChanges as $change)
                                                                                <tr>
                                                                                    <td><strong>{{ $change['field'] }}</strong></td>
                                                                                    <td>
                                                                                        @php
                                                                                            $oldValue = $change['old'];
                                                                                            if (is_array($oldValue)) {
                                                                                                $oldValue = json_encode($oldValue);
                                                                                            } elseif (is_bool($oldValue)) {
                                                                                                $oldValue = $oldValue ? 'Sì' : 'No';
                                                                                            } elseif (is_null($oldValue)) {
                                                                                                $oldValue = 'N/D';
                                                                                            }
                                                                                        @endphp
                                                                                        <span class="label label-danger">{{ $oldValue }}</span>
                                                                                    </td>
                                                                                    <td>
                                                                                        @php
                                                                                            $newValue = $change['new'];
                                                                                            if (is_array($newValue)) {
                                                                                                $newValue = json_encode($newValue);
                                                                                            } elseif (is_bool($newValue)) {
                                                                                                $newValue = $newValue ? 'Sì' : 'No';
                                                                                            } elseif (is_null($newValue)) {
                                                                                                $newValue = 'N/D';
                                                                                            }
                                                                                        @endphp
                                                                                        <span class="label label-success">{{ $newValue }}</span>
                                                                                    </td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                @else
                                                                    <div class="alert alert-info">
                                                                        <i class="fa fa-info-circle"></i> Nessuna modifica registrata.
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $log->ip_address ?? 'N/D' }}</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-info-circle"></i>
                            Nessuna operazione registrata per questa vendita.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Print Logs Section -->
    <div class="row mt-4">
        <div class="col-xs-12">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-print"></i> Log Stampe
                        <span class="label label-default" style="margin-left: 8px;">{{ $printLogs->count() }} stampe</span>
                        @if($printLogs->where('success', false)->count() > 0)
                            <span class="label label-danger" style="margin-left: 4px;">{{ $printLogs->where('success', false)->count() }} fallite</span>
                        @endif
                    </h4>
                </div>
                <div class="panel-body p-0">
                    @if($printLogs && $printLogs->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-condensed table-hover table-striped">
                                <thead>
                                    <tr class="active">
                                        <th width="140">Data/Ora</th>
                                        <th width="120">Tipo</th>
                                        <th width="100">Operazione</th>
                                        <th width="180">Stampante</th>
                                        <th width="150">Operatore</th>
                                        <th width="100" class="text-center">Stato</th>
                                        <th width="120" class="text-center">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($printLogs as $printLog)
                                        @php
                                            $typeColors = [
                                                'order' => 'primary',
                                                'marcia' => 'success',
                                                'preconto' => 'info',
                                                'comunica' => 'warning',
                                            ];
                                            $typeIcons = [
                                                'order' => 'fa-utensils',
                                                'marcia' => 'fa-play-circle',
                                                'preconto' => 'fa-receipt',
                                                'comunica' => 'fa-bullhorn',
                                            ];
                                            $opColors = [
                                                'add' => 'success',
                                                'update' => 'info',
                                                'remove' => 'danger',
                                            ];
                                        @endphp
                                        <tr>
                                            <td>
                                                <small class="text-nowrap">
                                                    {{ $printLog->created_at->format('d/m/Y') }}<br>
                                                    <strong>{{ $printLog->created_at->format('H:i:s') }}</strong>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="label label-{{ $typeColors[$printLog->print_type] ?? 'default' }}">
                                                    <i class="fa {{ $typeIcons[$printLog->print_type] ?? 'fa-print' }}"></i>
                                                    {{ $printLog->getPrintTypeLabel() }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($printLog->operation)
                                                    <span class="label label-{{ $opColors[$printLog->operation] ?? 'default' }}">
                                                        {{ $printLog->getOperationLabel() }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($printLog->printer)
                                                    <strong>{{ $printLog->printer->label }}</strong>
                                                    <br><small class="text-muted">{{ $printLog->printer->ip }}</small>
                                                @else
                                                    <span class="text-muted">N/D</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($printLog->user)
                                                    <i class="fa fa-user"></i> {{ $printLog->user->name }}
                                                @else
                                                    <em class="text-muted">N/D</em>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($printLog->success)
                                                    <span class="label label-success">
                                                        <i class="fa fa-check"></i> OK
                                                    </span>
                                                @else
                                                    <span class="label label-danger" title="{{ $printLog->error_message }}">
                                                        <i class="fa fa-times"></i> Errore
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="{{ route('backoffice.logs.print-preview', $printLog->id) }}"
                                                       class="btn btn-xs btn-info"
                                                       target="_blank"
                                                       title="Anteprima">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                    @if($printLog->printer)
                                                        <button type="button"
                                                                class="btn btn-xs btn-warning btn-reprint"
                                                                data-id="{{ $printLog->id }}"
                                                                title="Ristampa">
                                                            <i class="fa fa-redo"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @if(!$printLog->success && $printLog->error_message)
                                            <tr class="danger">
                                                <td colspan="7" style="padding-left: 30px;">
                                                    <small><i class="fa fa-exclamation-triangle"></i> <strong>Errore:</strong> {{ $printLog->error_message }}</small>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning text-center" style="margin: 15px;">
                            <i class="fa fa-info-circle"></i>
                            Nessun log di stampa registrato per questa vendita.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Print History Modal -->
    <div id="printHistoryModal" class="log-modal" onclick="if(event.target === this) toggleModal('printHistoryModal')">
        <div class="log-modal-content">
            <div class="log-modal-header" style="background: #f0ad4e;">
                <h5>
                    <i class="fa fa-print"></i> Stampa Storico Operazioni
                </h5>
                <button type="button" onclick="toggleModal('printHistoryModal')" class="log-modal-close">
                    &times;
                </button>
            </div>
            <div class="log-modal-body">
                <form id="printHistoryForm">
                    <input type="hidden" name="sale_id" value="{{ $sale->id }}">

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="font-weight: bold; margin-bottom: 10px; display: block;">
                            <i class="fa fa-filter"></i> Categorie da stampare
                        </label>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            @php
                                $categories = \App\Models\TableOrderLog::getAvailableCategories();
                            @endphp
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="categories[]" value="all" id="categoryAll" checked style="width: 18px; height: 18px; margin-right: 10px;">
                                <span><strong>Tutte le categorie</strong></span>
                            </label>
                            <hr style="margin: 5px 0;">
                            @foreach($categories as $key => $label)
                                <label style="display: flex; align-items: center; cursor: pointer; padding-left: 20px;">
                                    <input type="checkbox" name="categories[]" value="{{ $key }}" class="category-checkbox" style="width: 18px; height: 18px; margin-right: 10px;">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="font-weight: bold; margin-bottom: 10px; display: block;">
                            <i class="fa fa-print"></i> Stampante
                        </label>
                        <select name="printer_id" id="historyPrinterSelect" class="form-control" required style="padding: 10px; font-size: 14px;">
                            <option value="">-- Seleziona stampante --</option>
                            @php
                                $printers = \App\Models\Printer::where('is_active', true)->orderBy('label')->get();
                            @endphp
                            @foreach($printers as $printer)
                                <option value="{{ $printer->id }}">{{ $printer->label }} ({{ $printer->ip }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 25px;">
                        <button type="button" class="btn btn-default" style="flex: 1;" onclick="toggleModal('printHistoryModal')">
                            <i class="fa fa-times"></i> Annulla
                        </button>
                        <button type="submit" class="btn btn-warning" style="flex: 2;" id="btnPrintHistory">
                            <i class="fa fa-print"></i> Stampa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Print Styles -->
    <style>
        @media print {
            .navbar, .breadcrumb, .panel-footer, .btn {
                display: none !important;
            }
            .panel {
                border: 1px solid #ddd !important;
                page-break-inside: avoid;
            }
            /* Hide log sections in print */
            .panel-info, .panel-warning {
                display: none !important;
            }
        }

        /* Modal Styles */
        .log-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .log-modal.active {
            display: flex !important;
        }

        .log-modal-content {
            background: white;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .log-modal-header {
            background: #17a2b8;
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .log-modal-header h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .log-modal-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            line-height: 28px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .log-modal-close:hover {
            transform: scale(1.2);
            opacity: 0.8;
        }

        .log-modal-body {
            padding: 20px;
        }
    </style>

    <!-- Modal JavaScript -->
    <script>
        function toggleModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                if (modal.classList.contains('active')) {
                    modal.classList.remove('active');
                } else {
                    // Close any other open modals first
                    document.querySelectorAll('.log-modal.active').forEach(m => {
                        m.classList.remove('active');
                    });
                    // Open this modal
                    modal.classList.add('active');
                }
            }
        }

        // Close modal when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.log-modal.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });

        // Reprint functionality
        document.querySelectorAll('.btn-reprint').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const button = this;

                if (!confirm('Vuoi ristampare questo documento?')) {
                    return;
                }

                button.disabled = true;
                button.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

                fetch('/backoffice/logs/print/' + id + '/reprint', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Ristampa inviata con successo!');
                    } else {
                        alert('Errore: ' + (data.message || 'Errore durante la ristampa'));
                    }
                })
                .catch(error => {
                    alert('Errore durante la ristampa');
                    console.error('Error:', error);
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = '<i class="fa fa-redo"></i>';
                });
            });
        });

        // Print History - Category checkboxes logic
        const categoryAll = document.getElementById('categoryAll');
        const categoryCheckboxes = document.querySelectorAll('.category-checkbox');

        categoryAll.addEventListener('change', function() {
            if (this.checked) {
                categoryCheckboxes.forEach(cb => cb.checked = false);
            }
        });

        categoryCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                if (this.checked) {
                    categoryAll.checked = false;
                }
                // If no category is selected, select "all"
                const anySelected = Array.from(categoryCheckboxes).some(c => c.checked);
                if (!anySelected) {
                    categoryAll.checked = true;
                }
            });
        });

        // Print History Form Submit
        document.getElementById('printHistoryForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const printerId = document.getElementById('historyPrinterSelect').value;
            if (!printerId) {
                alert('Seleziona una stampante');
                return;
            }

            // Get selected categories
            let categories = [];
            if (categoryAll.checked) {
                categories = ['all'];
            } else {
                categoryCheckboxes.forEach(cb => {
                    if (cb.checked) categories.push(cb.value);
                });
            }

            if (categories.length === 0) {
                alert('Seleziona almeno una categoria');
                return;
            }

            const btn = document.getElementById('btnPrintHistory');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Stampa in corso...';

            fetch('/backoffice/logs/print-history', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    sale_id: {{ $sale->id }},
                    printer_id: printerId,
                    categories: categories
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Storico inviato alla stampante!');
                    toggleModal('printHistoryModal');
                } else {
                    alert('Errore: ' + (data.message || 'Errore durante la stampa'));
                }
            })
            .catch(error => {
                alert('Errore durante la stampa');
                console.error('Error:', error);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    </script>
@endsection
