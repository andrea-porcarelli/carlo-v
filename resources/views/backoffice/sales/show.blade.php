@extends('backoffice.layout', ['title' => 'Dettaglio Vendita #' . $sale->id])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Vendite', 'url' => route('restaurant.sales.index')],
        'level_2' => ['label' => 'Dettaglio Vendita #' . $sale->id],
    ])
@endsection
@section('main-content')
@php
  $tableId = $sale->restaurantTable->id;
  $orderId = $sale->id;
  $isOpen  = $sale->status === 'open';
@endphp
<script>
window._boSale = {
    tableId: {{ $tableId }},
    orderId: {{ $orderId }},
    isOpen: {{ $isOpen ? 'true' : 'false' }}
};
</script>
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
                    <h4 class="panel-title" style="display:flex; justify-content:space-between; align-items:center;">
                        <span><i class="fas fa-info-circle"></i> Informazioni Vendita</span>
                        @isset($sale->closed_at)
                        @php
                            $mins = $sale->opened_at->diffInMinutes($sale->closed_at);
                            $durLabel = $mins < 60
                                ? $mins . ' min'
                                : (floor($mins / 60) . 'h' . ($mins % 60 > 0 ? ' ' . ($mins % 60) . 'min' : ''));
                        @endphp
                        <small style="font-weight:400; opacity:0.85;"><i class="fas fa-clock"></i> {{ $durLabel }}</small>
                        @endisset
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
                            @endisset
                            <tr>
                                <td><strong>Apertura:</strong></td>
                                <td>
                                    @if($sale->waiter)
                                        <i class="fas fa-user"></i> {{ $sale->waiter->name }}
                                    @else
                                        <em class="text-muted">Non specificato</em>
                                    @endif
                                </td>
                            </tr>
                            @if($sale->closed_at)
                            <tr>
                                <td><strong>Chiusura:</strong></td>
                                <td>
                                    @if($sale->closeLog && $sale->closeLog->user)
                                        <i class="fas fa-user-check"></i> {{ $sale->closeLog->user->name }}
                                    @else
                                        <em class="text-muted">Non specificato</em>
                                    @endif
                                </td>
                            </tr>
                            @endif
                            @if($sale->hasDiscount())
                            <tr>
                                <td><strong>Sconto:</strong></td>
                                <td>
                                    @if($sale->discount_type === 'percent')
                                        <span class="badge badge-warning">
                                            <i class="fas fa-percent"></i> {{ number_format($sale->discount_amount, 1) }}%
                                        </span>
                                    @else
                                        <span class="badge badge-warning">
                                            <i class="fas fa-euro-sign"></i> {{ number_format($sale->discount_amount, 2, ',', '.') }}
                                        </span>
                                    @endif
                                    <span class="text-danger ml-1">
                                        &minus;€{{ number_format($sale->discount_value, 2, ',', '.') }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Totale scontato:</strong></td>
                                <td>
                                    <strong class="text-success" style="font-size:15px;">
                                        €{{ number_format($sale->getDiscountedTotal(), 2, ',', '.') }}
                                    </strong>
                                    <small class="text-muted ml-1" style="text-decoration:line-through;">
                                        €{{ number_format($sale->total_amount, 2, ',', '.') }}
                                    </small>
                                </td>
                            </tr>
                            @endif
                            @php
                                $paidSplits    = $sale->precontoSplits->where('status', 'paid');
                                $pendingSplits = $sale->precontoSplits->where('status', 'pending');
                                $paidSplitsTotal   = round((float) $paidSplits->sum('total'), 2);
                                $effectiveTotal    = $sale->hasDiscount() ? $sale->getDiscountedTotal() : (float) $sale->total_amount;
                                $remainingTotal    = max(0, round($effectiveTotal - $paidSplitsTotal, 2));
                            @endphp
                            @if($paidSplits->count() > 0 || $pendingSplits->count() > 0)
                            <tr>
                                <td colspan="2" style="padding:0;">
                                    <table class="table table-condensed" style="margin:0; font-size:12px; background:#fafafa;">
                                        <thead>
                                            <tr style="background:#e9ecef;">
                                                <th colspan="{{ $isOpen ? 4 : 3 }}" style="padding:4px 8px;">
                                                    <i class="fas fa-receipt"></i> Preconti
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($paidSplits as $split)
                                            <tr>
                                                <td style="padding:3px 8px;">
                                                    <i class="fas fa-check-circle text-success"></i>
                                                    {{ $split->label ?? 'Preconto' }}
                                                </td>
                                                <td style="padding:3px 8px; color:#666;">
                                                    {{ $split->paid_at ? $split->paid_at->format('H:i') : '' }}
                                                    @if($split->payment_method === 'chiusura_conto')
                                                        <span class="label label-default" style="font-size:10px;">chiusura</span>
                                                    @elseif($split->payment_method)
                                                        <span class="label label-default" style="font-size:10px;">{{ $split->payment_method }}</span>
                                                    @endif
                                                </td>
                                                <td style="padding:3px 8px; text-align:right; color:#d9534f;">
                                                    &minus;€{{ number_format($split->total, 2, ',', '.') }}
                                                </td>
                                                @if($isOpen)<td></td>@endif
                                            </tr>
                                            @endforeach
                                            @foreach($pendingSplits as $split)
                                            <tr>
                                                <td style="padding:3px 8px; color:#888;">
                                                    <i class="fas fa-clock"></i>
                                                    {{ $split->label ?? 'Preconto' }}
                                                </td>
                                                <td style="padding:3px 8px; color:#888;">in attesa</td>
                                                <td style="padding:3px 8px; text-align:right; color:#888;">
                                                    €{{ number_format($split->total, 2, ',', '.') }}
                                                </td>
                                                @if($isOpen)
                                                <td style="padding:3px 8px;">
                                                    <button class="btn btn-xs btn-success btn-incassa-split"
                                                        data-split-id="{{ $split->id }}"
                                                        data-split-total="{{ number_format($split->total, 2, ',', '.') }}"
                                                        data-split-label="{{ $split->label ?? 'Preconto' }}">
                                                        <i class="fas fa-cash-register"></i> Incassa
                                                    </button>
                                                </td>
                                                @endif
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        @if($paidSplitsTotal > 0)
                                        <tfoot>
                                            <tr style="background:#fff3cd;">
                                                <td colspan="2" style="padding:4px 8px;"><strong>Rimanente:</strong></td>
                                                <td style="padding:4px 8px; text-align:right;">
                                                    <strong class="text-success">€{{ number_format($remainingTotal, 2, ',', '.') }}</strong>
                                                </td>
                                            </tr>
                                        </tfoot>
                                        @endif
                                    </table>
                                </td>
                            </tr>
                            @endif
                            <tr>
                                <td><strong>Pagamento:</strong></td>
                                <td>
                                    @php
                                        $pmLabels = [
                                            'pos'            => ['label' => 'POS',             'icon' => 'fa-credit-card',  'class' => 'success'],
                                            'contanti'       => ['label' => 'Contanti',         'icon' => 'fa-coins',        'class' => 'info'],
                                            'fattura'        => ['label' => 'Fattura',          'icon' => 'fa-file-invoice', 'class' => 'primary'],
                                            'misto'          => ['label' => 'Misto',            'icon' => 'fa-layer-group',  'class' => 'warning'],
                                            'chiusura_conto' => ['label' => 'Chiusura conto',   'icon' => 'fa-times-circle', 'class' => 'default'],
                                            'fattura_pos' => ['label' => 'Pos con fattura',   'icon' => 'fa-credit-card', 'class' => 'success'],
                                        ];
                                        $pm = $pmLabels[$sale->payment_method] ?? null;
                                    @endphp
                                    @if($pm)
                                        <span style="font-size: 15px" class="badge badge-{{ $pm['class'] }}">
                                            <i class="fas {{ $pm['icon'] }}"></i> {{ $pm['label'] }}
                                        </span>
                                    @else
                                        <em class="text-muted">Non specificato</em>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @php
                $isAdmin = auth()->user()?->role === 'admin';
                $isPaid  = $sale->status === 'paid';
                $activeDitronSale = $isPaid
                    ? ($sale->ditronReceipts ?? collect())
                        ->where('type', \App\Models\DitronReceipt::TYPE_SALE)
                        ->whereIn('status', [
                            \App\Models\DitronReceipt::STATUS_PENDING,
                            \App\Models\DitronReceipt::STATUS_SENDING,
                            \App\Models\DitronReceipt::STATUS_SENT,
                        ])
                        ->whereNull('preconto_split_id')
                        ->filter(fn($r) => $r->cancelled_at === null)
                        ->sortByDesc('id')
                        ->first()
                    : null;
                $canEmitReceipt = $isPaid && in_array($sale->payment_method, ['contanti', 'pos'], true);
            @endphp
            @if($isPaid && $isAdmin)
            <div class="panel panel-danger" style="margin-top: 15px;">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fas fa-user-shield"></i> Azioni Amministrative
                    </h4>
                </div>
                <div class="panel-body">
                    <div class="alert alert-warning" style="padding:8px 12px; margin-bottom:12px; font-size:12px; align-items:flex-start;">
                        <i class="fa fa-exclamation-triangle" style="margin-top:2px;"></i>
                        <div style="flex:1;">
                            <strong>Attenzione:</strong> operazioni delicate e <u>irreversibili</u> sulla vendita chiusa.
                            Il cambio di metodo pagamento non annulla lo scontrino fiscale già emesso;
                            annullalo prima dal log Ditron se necessario.
                        </div>
                    </div>
                    <div class="row" style="margin: -3px;">
                        <div class="col-xs-12" style="padding: 3px;">
                            <button type="button" class="btn btn-warning btn-block btn-sm" onclick="toggleModal('modalChangePaymentMethod')">
                                <i class="fas fa-exchange-alt"></i> Cambia metodo di pagamento
                            </button>
                        </div>
                        <div class="col-xs-12" style="padding: 3px;">
                            <button type="button"
                                    class="btn btn-primary btn-block btn-sm"
                                    id="btnEmitFiscalReceipt"
                                    @if(!$canEmitReceipt || $activeDitronSale) disabled @endif
                                    @if($activeDitronSale)
                                        title="Esiste già uno scontrino fiscale attivo (#{{ $activeDitronSale->id }}). Annullalo prima di emetterne uno nuovo."
                                    @elseif(!$canEmitReceipt)
                                        title="Emissione possibile solo per pagamenti in contanti o POS."
                                    @endif>
                                <i class="fas fa-print"></i> Emetti scontrino fiscale
                            </button>
                            @if($activeDitronSale)
                                <small class="text-muted" style="display:block; margin-top:4px; font-size:11px;">
                                    <i class="fa fa-info-circle"></i>
                                    Scontrino attivo: #{{ $activeDitronSale->id }} — {{ $activeDitronSale->getStatusLabel() }}
                                </small>
                            @elseif(!$canEmitReceipt)
                                <small class="text-muted" style="display:block; margin-top:4px; font-size:11px;">
                                    <i class="fa fa-info-circle"></i>
                                    Disponibile solo per metodi <em>Contanti</em> o <em>POS</em>.
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal: Cambio Metodo di Pagamento (admin) -->
            <div id="modalChangePaymentMethod" class="log-modal" onclick="if(event.target===this)toggleModal('modalChangePaymentMethod')">
                <div class="log-modal-content" style="max-width:520px;">
                    <div class="log-modal-header" style="background:#d9534f;">
                        <h5><i class="fas fa-exchange-alt"></i> Cambia metodo di pagamento</h5>
                        <button type="button" onclick="toggleModal('modalChangePaymentMethod')" class="log-modal-close">&times;</button>
                    </div>
                    <div class="log-modal-body">
                        <div class="alert alert-danger" style="display:block; padding:10px 12px;">
                            <strong><i class="fa fa-exclamation-triangle"></i> Operazione irreversibile.</strong><br>
                            Stai modificando il metodo di pagamento di una vendita <u>già chiusa</u>.
                            Questa azione <strong>non annulla</strong> automaticamente lo scontrino fiscale
                            eventualmente già emesso: se necessario, procedi prima con l'annullo dal log Ditron.
                            L'operazione viene tracciata sul log operativo con il tuo utente.
                        </div>
                        <form id="formChangePaymentMethod">
                            <div class="form-group">
                                <label><strong>Metodo attuale:</strong>
                                    <span class="label label-default" style="font-size:12px;">
                                        {{ \App\Models\TableOrder::paymentMethodLabels()[$sale->payment_method] ?? ($sale->payment_method ?? '—') }}
                                    </span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label for="selectNewPaymentMethod"><strong>Nuovo metodo di pagamento</strong></label>
                                <select id="selectNewPaymentMethod" class="form-control" required>
                                    <option value="">-- Seleziona metodo --</option>
                                    @foreach(\App\Models\TableOrder::paymentMethodLabels() as $key => $label)
                                        @if($key !== $sale->payment_method)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="inputChangePaymentReason">Motivo (opzionale, verrà salvato nel log)</label>
                                <textarea id="inputChangePaymentReason" class="form-control" rows="2" maxlength="500" placeholder="Es. errata registrazione contanti/POS al momento della chiusura"></textarea>
                            </div>
                            <div style="display:flex; gap:10px; margin-top:15px;">
                                <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalChangePaymentMethod')">Annulla</button>
                                <button type="submit" class="btn btn-danger" style="flex:2" id="btnConfirmChangePaymentMethod">
                                    <i class="fa fa-check"></i> Conferma cambio
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            @if($isOpen)
            <div class="panel panel-warning" style="margin-top: 15px;">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fas fa-cogs"></i> Azioni sul Tavolo
                    </h4>
                </div>
                <div class="panel-body">
                    <div class="row" style="margin: -3px;">
                        <div class="col-xs-6" style="padding: 3px;">
                            <button class="btn btn-success btn-block btn-sm btn-bo-action" data-action="marcia">
                                <i class="fas fa-utensils"></i> Marcia
                            </button>
                        </div>
                        <div class="col-xs-6" style="padding: 3px;">
                            <button class="btn btn-info btn-block btn-sm" onclick="toggleModal('modalPreconto')">
                                <i class="fas fa-receipt"></i> Pre-Conto
                            </button>
                        </div>
                        <div class="col-xs-6" style="padding: 3px;">
                            <button class="btn btn-primary btn-block btn-sm" onclick="toggleModal('modalIncassa')">
                                <i class="fas fa-cash-register"></i> Incassa
                            </button>
                        </div>
                        <div class="col-xs-6" style="padding: 3px;">
                            <button class="btn btn-default btn-block btn-sm" onclick="boOpenAddDish()">
                                <i class="fas fa-plus"></i> Aggiungi
                            </button>
                        </div>
                        <div class="col-xs-6" style="padding: 3px;">
                            <button class="btn btn-default btn-block btn-sm" onclick="toggleModal('modalCoperti')">
                                <i class="fas fa-users"></i> Coperti
                            </button>
                        </div>
                        <div class="col-xs-6" style="padding: 3px;">
                            <button class="btn btn-default btn-block btn-sm" onclick="toggleModal('modalSconto')">
                                <i class="fas fa-percent"></i> Sconto
                            </button>
                        </div>
                        <div class="col-xs-6" style="padding: 3px;">
                            <button class="btn btn-default btn-block btn-sm" onclick="boOpenSposta()">
                                <i class="fas fa-arrows-alt"></i> Sposta
                            </button>
                        </div>
                        <div class="col-xs-6" style="padding: 3px;">
                            <button class="btn btn-default btn-block btn-sm btn-bo-action" data-action="reprint">
                                <i class="fas fa-print"></i> Ristampa
                            </button>
                        </div>
                        <div class="col-xs-6" style="padding: 3px;">
                            <button class="btn btn-default btn-block btn-sm" onclick="boOpenComunica()">
                                <i class="fas fa-comments"></i> Comunica
                            </button>
                        </div>
                        <div class="col-xs-6" style="padding: 3px;">
                            <button class="btn btn-warning btn-block btn-sm btn-bo-action" data-action="autoconsumo">
                                <i class="fas fa-user-check"></i> Autoconsumo
                            </button>
                        </div>
                        <div class="col-xs-12" style="padding: 3px; margin-top: 6px;">
                            <button class="btn btn-danger btn-block btn-sm" onclick="toggleModal('modalChiudiTavolo')">
                                <i class="fas fa-times-circle"></i> Chiudi Tavolo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif
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
                    @if($sale->autoconsumo)
                        <div class="alert" style="background:#fff3cd; border-left:4px solid #ffc107; padding:12px 15px; margin-bottom:15px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <strong style="color:#856404;"><i class="fas fa-utensils"></i>
                                        @if($autoconsumoType === 'autoconsumo_partial')
                                            Autoconsumo parziale
                                        @else
                                            Autoconsumo completo
                                        @endif
                                    </strong>
                                    @if($autoconsumoType === 'autoconsumo')
                                        <span class="text-muted"> — l'intero ordine è stato marcato come autoconsumo (nessuna assegnazione per operatore)</span>
                                    @endif
                                </div>
                            </div>
                            @if(!empty($autoconsumoBreakdown))
                                <hr style="margin:10px 0; border-color:#ffe69c;">
                                <div style="display:flex; flex-wrap:wrap; gap:12px;">
                                    @foreach($autoconsumoBreakdown as $userName => $entries)
                                        @php
                                            $totalQty = collect($entries)->sum('qty');
                                        @endphp
                                        <div style="background:#fff; border:1px solid #ffe69c; border-radius:4px; padding:8px 12px; min-width:180px;">
                                            <div style="font-weight:700; color:#333; border-bottom:1px solid #eee; padding-bottom:4px; margin-bottom:4px;">
                                                <i class="fas fa-user"></i> {{ $userName }}
                                                <span class="badge badge-warning" style="float:right;">{{ $totalQty }} pz</span>
                                            </div>
                                            <ul style="list-style:none; padding:0; margin:0; font-size:0.85rem;">
                                                @foreach($entries as $e)
                                                    <li style="padding:2px 0;">
                                                        <span style="color:#6c757d;">{{ $e['qty'] }}×</span> {{ $e['item'] }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                    @if($sale->items()->withTrashed()->get()->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="60" class="text-center">Qta</th>
                                        <th>Prodotto</th>
                                        @if($sale->autoconsumo)
                                            <th>Addebitato a</th>
                                        @endif
                                        <th width="100" class="text-right">Prezzo Unit.</th>
                                        <th width="110" class="text-right" title="Stima costo materie prime unitario / totale riga">
                                            <i class="fas fa-coins text-warning"></i> Costo stim.
                                        </th>
                                        <th width="100" class="text-right">Subtotale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sale->items()->withTrashed()->orderBy('id', 'DESC')->get() as $index => $item)
                                        <tr class="@if($item->status == 'cancelled') trashed @endif" data-item-id="{{ $item->id }}">
                                            @if(!isset($item->dish))
                                                <td colspan="{{ $sale->autoconsumo ? 6 : 5 }}"> ----- SEGUE ----- </td>
                                            @else
                                                <td class="text-center" style="vertical-align:middle;">
                                                    <span style="font-size:18px; font-weight:700; color:#333;">{{ $item->quantity }}</span>
                                                </td>
                                                <td style="padding-top:10px; padding-bottom:10px;">
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

                                                    @if($isOpen && $item->status != 'cancelled')
                                                        <div class="btn-group btn-group-xs" style="margin-top: 8px;">
                                                            <button class="btn btn-xs btn-default btn-item-qty"
                                                                    data-item-id="{{ $item->id }}"
                                                                    data-qty="{{ $item->quantity }}"
                                                                    title="Modifica quantità">
                                                                <i class="fas fa-hashtag"></i>
                                                            </button>
                                                            <button class="btn btn-xs btn-default btn-item-price"
                                                                    data-item-id="{{ $item->id }}"
                                                                    data-price="{{ $item->unit_price }}"
                                                                    title="Modifica prezzo">
                                                                <i class="fas fa-euro-sign"></i>
                                                            </button>
                                                            <button class="btn btn-xs btn-info btn-item-details"
                                                                    data-item-id="{{ $item->id }}"
                                                                    data-notes="{{ $item->notes }}"
                                                                    title="Modifica note">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-xs btn-warning btn-item-change-dish"
                                                                    data-item-id="{{ $item->id }}"
                                                                    title="Cambia piatto">
                                                                <i class="fas fa-exchange-alt"></i>
                                                            </button>
                                                            @if($sale->items->where('status', '!=', 'cancelled')->count() > 1)
                                                                <button class="btn btn-xs btn-danger btn-item-remove"
                                                                        data-item-id="{{ $item->id }}"
                                                                        title="Rimuovi">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                                @if($sale->autoconsumo)
                                                    <td>
                                                        @if($item->autoconsumoUser)
                                                            <i class="fas fa-user text-warning"></i>
                                                            <strong>{{ $item->autoconsumoUser->name }}</strong>
                                                            <br><small class="text-muted">tutto ({{ $item->quantity }} pz)</small>
                                                        @elseif($autoconsumoAssignments->has($item->id))
                                                            @php
                                                                $a = $autoconsumoAssignments->get($item->id);
                                                                $aQty = $a['actual_quantity'] ?? $a['quantity'] ?? 1;
                                                                $aName = $autoconsumoUserNames[$a['user_id']] ?? "Utente #{$a['user_id']}";
                                                            @endphp
                                                            <i class="fas fa-user text-warning"></i>
                                                            <strong>{{ $aName }}</strong>
                                                            <br><small class="text-muted">{{ $aQty }} pz autoconsumati</small>
                                                        @elseif($autoconsumoType === 'autoconsumo')
                                                            <span class="text-muted"><i class="fas fa-utensils"></i> Autoconsumo completo</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                @endif
                                                <td class="text-right" style="font-weight: bold">
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
                                                <td class="text-right">
                                                    @php
                                                        $costInfo = $itemCostEstimates[$item->id] ?? null;
                                                        $bd       = $costInfo['breakdown'] ?? null;
                                                        $hasBd    = $bd && !empty($bd['materials']);
                                                    @endphp
                                                    @if($costInfo && ($costInfo['unit_cost'] > 0 || $costInfo['coverage'] > 0))
                                                        <span style="font-size: 13px; color:#8a6d3b;">
                                                            €{{ number_format($costInfo['line_cost'], 2, ',', '.') }}
                                                        </span>
                                                        <br>
                                                        <small class="text-muted" style="font-size:11px;">
                                                            unit. €{{ number_format($costInfo['unit_cost'], 2, ',', '.') }}
                                                            @if($costInfo['coverage'] < 1 && $costInfo['coverage'] > 0)
                                                                <br><span class="text-warning" title="Costo di alcuni materiali non disponibile">
                                                                    <i class="fas fa-exclamation-triangle"></i> parziale ({{ round($costInfo['coverage']*100) }}%)
                                                                </span>
                                                            @endif
                                                        </small>
                                                        @if($hasBd)
                                                            <br>
                                                            <button type="button"
                                                                    class="btn btn-link btn-xs cost-debug-btn"
                                                                    data-toggle="collapse"
                                                                    data-target="#costDebug-{{ $item->id }}"
                                                                    aria-expanded="false"
                                                                    style="padding:0; font-size:10px; text-decoration:underline; color:#6c757d;">
                                                                <i class="fas fa-bug"></i> debug
                                                            </button>
                                                        @endif
                                                    @else
                                                        <span class="text-muted" title="Nessun costo materiali disponibile" style="font-size:12px;">
                                                            <i class="fas fa-question-circle"></i> n/d
                                                        </span>
                                                        @if($hasBd)
                                                            <br>
                                                            <button type="button"
                                                                    class="btn btn-link btn-xs cost-debug-btn"
                                                                    data-toggle="collapse"
                                                                    data-target="#costDebug-{{ $item->id }}"
                                                                    aria-expanded="false"
                                                                    style="padding:0; font-size:10px; text-decoration:underline; color:#6c757d;">
                                                                <i class="fas fa-bug"></i> debug
                                                            </button>
                                                        @endif
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    <strong style="font-size: 15px;">
                                                        €{{ number_format($item->subtotal, 2, ',', '.') }}
                                                    </strong>
                                                </td>
                                            @endif

                                        </tr>
                                        @if(isset($item->dish) && !empty($itemCostEstimates[$item->id]['breakdown']['materials'] ?? []))
                                            @php
                                                $debugCols = $sale->autoconsumo ? 6 : 5;
                                                $bd        = $itemCostEstimates[$item->id]['breakdown'];
                                            @endphp
                                            <tr class="collapse cost-debug-row" id="costDebug-{{ $item->id }}">
                                                <td colspan="{{ $debugCols }}" style="background:#fdfdf5; padding:12px 18px; border-top:1px dashed #d0d0b0;">
                                                    <div style="font-size:11px; color:#6c757d; margin-bottom:6px;">
                                                        <i class="fas fa-info-circle"></i>
                                                        Costo unitario = Σ (qty materiale per porzione × costo medio materiale).
                                                        Costo medio per unità base = media ponderata sui carichi, con purchase_price /
                                                        quantity_multiplier della riga fattura (fusto/cartone → cl/kg/pz).
                                                    </div>
                                                    <table class="table table-sm mb-0" style="font-size:12px; background:transparent;">
                                                        <thead>
                                                            <tr style="background:#f0efdc;">
                                                                <th style="padding:4px 8px;">Materiale</th>
                                                                <th class="text-right" style="padding:4px 8px;">Qta / porz.</th>
                                                                <th class="text-right" style="padding:4px 8px;">Costo medio</th>
                                                                <th class="text-right" style="padding:4px 8px;">Contributo unit.</th>
                                                                <th class="text-right" style="padding:4px 8px;">× {{ $bd['quantity'] }} porz.</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($bd['materials'] as $m)
                                                                <tr @if(!$m['has_cost']) style="color:#b58a1a;" @endif>
                                                                    <td style="padding:4px 8px;">
                                                                        {{ $m['name'] }}
                                                                        @if(!$m['has_cost'])
                                                                            <i class="fas fa-exclamation-triangle" title="Nessun carico con prezzo — costo non calcolabile"></i>
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-right" style="padding:4px 8px;">
                                                                        {{ rtrim(rtrim(number_format($m['qty_per_portion'], 4, ',', '.'), '0'), ',') }}
                                                                        <small class="text-muted">{{ $m['unit'] }}</small>
                                                                    </td>
                                                                    <td class="text-right" style="padding:4px 8px;">
                                                                        @if($m['has_cost'])
                                                                            €{{ number_format($m['avg_cost'], 4, ',', '.') }} / {{ $m['unit'] ?: 'un.' }}
                                                                        @else
                                                                            <span class="text-muted">n/d</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-right" style="padding:4px 8px;">
                                                                        @if($m['has_cost'])
                                                                            €{{ number_format($m['contribution_unit'], 4, ',', '.') }}
                                                                        @else
                                                                            —
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-right" style="padding:4px 8px;">
                                                                        @if($m['has_cost'])
                                                                            €{{ number_format($m['contribution_line'], 4, ',', '.') }}
                                                                        @else
                                                                            —
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                        <tfoot>
                                                            <tr style="font-weight:700; background:#f0efdc;">
                                                                <td colspan="3" class="text-right" style="padding:6px 8px;">Totale</td>
                                                                <td class="text-right" style="padding:6px 8px;">€{{ number_format($bd['unit_cost'], 4, ',', '.') }}</td>
                                                                <td class="text-right" style="padding:6px 8px;">€{{ number_format($bd['line_cost'], 4, ',', '.') }}</td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    @php
                                        // colspan della label: totali con "Costo stim." aggiunto → 4 base, 5 se autoconsumo
                                        $labelSpan = $sale->autoconsumo ? 5 : 4;
                                    @endphp
                                    @if($sale->autoconsumo)
                                        <tr class="table-success">
                                            <td colspan="{{ $labelSpan + 1 }}" class="text-right" style="font-size: 16px">
                                                <b>AUTOCONSUMO</b>
                                            </td>
                                        </tr>
                                    @endif
                                    @if($sale->hasCoverCharge())
                                        <tr>
                                            <td colspan="{{ $labelSpan }}" class="text-right text-muted">
                                                <i class="fas fa-utensils"></i>
                                                Coperto
                                                ({{ $sale->covers }} × €{{ number_format($sale->getCoverChargePerPerson(), 2, ',', '.') }}):
                                            </td>
                                            <td class="text-right">
                                                <strong>€{{ number_format($sale->getCoverChargeAmount(), 2, ',', '.') }}</strong>
                                            </td>
                                        </tr>
                                    @endif
                                    @if($sale->hasDiscount())
                                        <tr>
                                            <td colspan="{{ $labelSpan }}" class="text-right text-muted">
                                                Subtotale:
                                            </td>
                                            <td class="text-right text-muted">
                                                €{{ number_format($sale->total_amount, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                        <tr class="warning">
                                            <td colspan="{{ $labelSpan }}" class="text-right">
                                                <span class="text-danger">
                                                    <i class="fas fa-percent"></i>
                                                    Sconto
                                                    @if($sale->discount_type === 'percent')
                                                        ({{ number_format($sale->discount_amount, 1) }}%)
                                                    @else
                                                        (€{{ number_format($sale->discount_amount, 2, ',', '.') }})
                                                    @endif
                                                    :
                                                </span>
                                            </td>
                                            <td class="text-right text-danger">
                                                &minus;€{{ number_format($sale->discount_value, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif
                                    @if(isset($paidSplits) && $paidSplits->count() > 0)
                                        @foreach($paidSplits as $split)
                                        <tr style="color:#888; font-size:13px;">
                                            <td colspan="{{ $labelSpan }}" class="text-right">
                                                <i class="fas fa-check-circle text-success"></i>
                                                Preconto pagato ( ) — {{ $split->label ?? '' }}
                                                @if($split->paid_at)<small class="text-muted">({{ $split->paid_at->format('H:i') }})</small>@endif:
                                            </td>
                                            <td class="text-right text-danger">
                                                &minus;€{{ number_format($split->total, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                        @endforeach
                                        <tr style="background:#fff3cd;">
                                            <td colspan="{{ $labelSpan }}" class="text-right">
                                                <strong>RIMANENTE DA PAGARE:</strong>
                                            </td>
                                            <td class="text-right">
                                                <strong style="font-size: 18px;" class="text-success">
                                                    €{{ number_format($remainingTotal, 2, ',', '.') }}
                                                </strong>
                                            </td>
                                        </tr>
                                    @endif
                                    <tr class="table-success">
                                        <td colspan="{{ $labelSpan }}" class="text-right">
                                            <strong style="font-size: 16px;" class="@if($sale->status == 'cancelled') trashed @endif">TOTALE:</strong>
                                        </td>
                                        <td class="text-right">
                                            <strong style="font-size: 18px; @if($sale->autoconsumo) text-decoration:line-through @endif"  class="@if($sale->status == 'cancelled') text-danger @else text-success @endif @if($sale->status == 'cancelled') trashed @endif">
                                                €{{ number_format($sale->hasDiscount() ? $sale->getDiscountedTotal() : $sale->total_amount, 2, ',', '.') }}
                                            </strong>
                                        </td>
                                    </tr>

                                    {{-- ── Stima costi/margine ── --}}
                                    @if($totalEstimatedCost > 0)
                                        <tr style="background:#fff8e1;">
                                            <td colspan="{{ $labelSpan }}" class="text-right" style="color:#8a6d3b;">
                                                <i class="fas fa-coins"></i>
                                                Costo materie prime <em>(stima)</em>
                                                @if($costPercent !== null)
                                                    <small class="text-muted">— incidenza {{ number_format($costPercent, 1, ',', '.') }}%</small>
                                                @endif
                                                :
                                            </td>
                                            <td class="text-right" style="color:#8a6d3b;">
                                                <strong>€{{ number_format($totalEstimatedCost, 2, ',', '.') }}</strong>
                                            </td>
                                        </tr>
                                        <tr style="background:#f1f8e9;">
                                            <td colspan="{{ $labelSpan }}" class="text-right">
                                                <strong>
                                                    <i class="fas fa-chart-line text-success"></i>
                                                    Margine lordo stimato
                                                    @if($marginPercent !== null)
                                                        <small class="text-muted">({{ number_format($marginPercent, 1, ',', '.') }}%)</small>
                                                    @endif
                                                    :
                                                </strong>
                                            </td>
                                            <td class="text-right">
                                                <strong style="font-size: 15px;" class="@if($estimatedMargin >= 0) text-success @else text-danger @endif">
                                                    €{{ number_format($estimatedMargin, 2, ',', '.') }}
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="{{ $labelSpan + 1 }}" class="text-right text-muted" style="font-size:11px; padding-top:2px;">
                                                <i class="fas fa-info-circle"></i>
                                                Stima basata sul costo medio ponderato dei materiali (esclude personale, utenze, oneri).
                                            </td>
                                        </tr>
                                    @endif
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

    <!-- Invoice Section -->
    @php
        $tableOrderInvoices = $sale->tableOrderInvoices ?? collect();
        $invoiceLogs        = $logs->where('action', 'create_invoice')->values();
        $hasInvoices        = $tableOrderInvoices->count() > 0;
        $hasLegacyLogs      = !$hasInvoices && $invoiceLogs->count() > 0;
    @endphp
    @if($hasInvoices || $hasLegacyLogs)
    <div class="row mt-4">
        <div class="col-xs-12">
            <div class="panel panel-primary">
                <div class="panel-heading" style="display: flex; justify-content: space-between; align-items: center;">
                    <h4 class="panel-title" style="margin: 0;">
                        <i class="fas fa-file-invoice"></i> Fatturazioni
                        @if($hasInvoices)
                            <span class="label label-default" style="margin-left: 8px;">{{ $tableOrderInvoices->count() }} fattura/e</span>
                            @if($tableOrderInvoices->where('status','sent')->count() > 0)
                                <span class="label label-success" style="margin-left: 4px;">
                                    <i class="fas fa-check"></i> {{ $tableOrderInvoices->where('status','sent')->count() }} inviate
                                </span>
                            @endif
                            @if($tableOrderInvoices->where('status','error')->count() > 0)
                                <span class="label label-danger" style="margin-left: 4px;">
                                    <i class="fas fa-exclamation-triangle"></i> {{ $tableOrderInvoices->where('status','error')->count() }} errori
                                </span>
                            @endif
                        @else
                            <span class="label label-default" style="margin-left: 8px;">{{ $invoiceLogs->count() }} fattura/e (log)</span>
                        @endif
                    </h4>
                    @php
                        $totalInvoiced = $hasInvoices
                            ? $tableOrderInvoices->sum('amount')
                            : $invoiceLogs->sum(fn($l) => (float)($l->data_after['amount'] ?? 0));
                        $remaining = round((float) $sale->total_amount - $totalInvoiced, 2);
                    @endphp
                    <div style="text-align: right;">
                        <small class="text-muted">Totale fatturato:</small>
                        <strong style="font-size: 1.1rem; margin-left: 6px;">€{{ number_format($totalInvoiced, 2, ',', '.') }}</strong>
                        @if($remaining > 0.01)
                            <small class="text-muted" style="margin-left: 10px;">Resto:</small>
                            <strong style="font-size: 1.1rem; margin-left: 6px;">€{{ number_format($remaining, 2, ',', '.') }}</strong>
                        @endif
                    </div>
                </div>
                <div class="panel-body p-0">
                    <div class="table-responsive">
                        @if($hasInvoices)
                        {{-- Dati da table_order_invoices --}}
                        <table class="table table-condensed table-hover table-striped" style="margin: 0;">
                            <thead>
                                <tr class="active">
                                    <th width="110">N° Fattura</th>
                                    <th width="130">Data/Ora</th>
                                    <th width="110" class="text-right">Importo</th>
                                    <th>Intestatario</th>
                                    <th width="170">CF / P.IVA</th>
                                    <th width="80" class="text-center">Stato</th>
                                    <th width="185" class="text-center">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tableOrderInvoices as $inv)
                                    @php
                                        $customer  = $inv->customer;
                                        $statusMap = [
                                            'sent'    => ['label' => 'Inviata',   'class' => 'success'],
                                            'error'   => ['label' => 'Errore',    'class' => 'danger'],
                                            'pending' => ['label' => 'In attesa', 'class' => 'warning'],
                                        ];
                                        $st = $statusMap[$inv->status] ?? ['label' => $inv->status, 'class' => 'default'];
                                    @endphp
                                    <tr>
                                        <td>
                                            @if($inv->invoice_code)
                                                <code style="font-size:.85rem;">{{ $inv->invoice_code }}</code>
                                            @else
                                                <em class="text-muted">#{{ $inv->id }}</em>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-nowrap">
                                                {{ $inv->created_at->format('d/m/Y') }}<br>
                                                <strong>{{ $inv->created_at->format('H:i:s') }}</strong>
                                            </small>
                                        </td>
                                        <td class="text-right">
                                            <strong style="font-size:1rem;">€{{ number_format($inv->amount, 2, ',', '.') }}</strong>
                                            @if($inv->tax > 0)
                                                <br><small class="text-muted">IVA: €{{ number_format($inv->tax, 2, ',', '.') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($customer)
                                                <strong>{{ $customer->full_name }}</strong>
                                                @if($customer->address)
                                                    <br><small class="text-muted">{{ $customer->address }}, {{ $customer->zip_code }} {{ $customer->city }} ({{ $customer->province }})</small>
                                                @endif
                                                @php $typeLabels = [
                                                    'private' => 'Privato',
                                                    'company' => 'Azienda',
                                                    'sole_trader' => 'Ditta ind./Libero prof.',
                                                    'non_profit_entity' => 'Ente Non Commerciale',
                                                    'public_company' => 'PA',
                                                    'foreign' => 'Estero',
                                                ]; @endphp
                                                <br><span class="label label-default" style="font-size:.75em;">{{ $typeLabels[$customer->user_type] ?? $customer->user_type }}</span>
                                            @elseif($inv->description)
                                                <em class="text-muted">{{ $inv->description }}</em>
                                            @else
                                                <em class="text-muted">—</em>
                                            @endif
                                        </td>
                                        <td>
                                            @if($customer)
                                                @if($customer->fiscal_code)
                                                    <code style="font-size:.8rem;">{{ $customer->fiscal_code }}</code>
                                                @endif
                                                @if($customer->vat_number)
                                                    <br><code style="font-size:.8rem; color:#2563eb;">P.IVA {{ $customer->vat_number }}</code>
                                                @endif
                                                @if($customer->codice_destinatario)
                                                    <br><small class="text-muted">SDI: {{ $customer->codice_destinatario }}</small>
                                                @endif
                                            @else
                                                <em class="text-muted">—</em>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="label label-{{ $st['class'] }}">{{ $st['label'] }}</span>
                                            @if($inv->sent_at)
                                                <br><small class="text-muted">{{ $inv->sent_at->format('H:i') }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div style="display:flex; gap:4px; justify-content:center; flex-wrap:wrap;">
                                                @if($inv->xml_content)
                                                    <a href="{{ route('restaurant.table-order-invoices.xml', $inv->id) }}" target="_blank"
                                                       class="btn btn-xs btn-default" title="Visualizza XML">
                                                        <i class="fas fa-code"></i> XML
                                                    </a>
                                                    <a href="{{ route('restaurant.table-order-invoices.pdf', $inv->id) }}" target="_blank"
                                                       class="btn btn-xs btn-danger" title="Scarica PDF">
                                                        <i class="fas fa-file-pdf"></i> PDF
                                                    </a>
                                                @endif
                                                @if($customer)
                                                    <button class="btn btn-xs btn-warning btn-regenerate-invoice"
                                                            data-id="{{ $inv->id }}" title="Rigenera XML e reinvia">
                                                        <i class="fas fa-sync-alt"></i> Rigenera
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @if($inv->mysond_response && $inv->status !== 'sent')
                                    <tr class="danger">
                                        <td colspan="7" style="padding:6px 14px;">
                                            <small><i class="fas fa-exclamation-circle"></i>
                                            <strong>Risposta Mysond:</strong> {{ Str::limit($inv->mysond_response, 300) }}</small>
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="active">
                                    <td colspan="2"><strong>Totale fatturato</strong></td>
                                    <td class="text-right"><strong>€{{ number_format($totalInvoiced, 2, ',', '.') }}</strong></td>
                                    <td colspan="4">
                                        @if($remaining > 0.01)
                                            <small class="text-muted">
                                                Restante €{{ number_format($remaining, 2, ',', '.') }}
                                                @if($sale->payment_method === 'misto') — pagato con metodo complementare @endif
                                            </small>
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>

                        @else
                        {{-- Legacy: dati dai log (ordini precedenti) --}}
                        <table class="table table-condensed table-hover table-striped" style="margin: 0;">
                            <thead>
                                <tr class="active">
                                    <th width="140">Data/Ora</th>
                                    <th width="120" class="text-right">Importo</th>
                                    <th>Descrizione</th>
                                    <th width="200">Intestatario</th>
                                    <th width="150">Cod. Fiscale / P.IVA</th>
                                    <th width="120">Operatore</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoiceLogs as $inv)
                                    @php $d = $inv->data_after ?? []; @endphp
                                    <tr>
                                        <td>
                                            <small class="text-nowrap">
                                                {{ $inv->created_at->format('d/m/Y') }}<br>
                                                <strong>{{ $inv->created_at->format('H:i:s') }}</strong>
                                            </small>
                                        </td>
                                        <td class="text-right">
                                            <strong style="font-size:1rem;">€{{ number_format($d['amount'] ?? 0, 2, ',', '.') }}</strong>
                                        </td>
                                        <td>{{ $d['description'] ?? 'Pasto completo' }}</td>
                                        <td>
                                            @if(!empty($d['customer_name']))
                                                <i class="fas fa-user"></i> {{ $d['customer_name'] }}
                                            @else
                                                <em class="text-muted">—</em>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($d['customer_tax_code']))
                                                <code style="font-size:.85rem;">{{ $d['customer_tax_code'] }}</code>
                                            @else
                                                <em class="text-muted">—</em>
                                            @endif
                                        </td>
                                        <td>
                                            @if($inv->user)
                                                <i class="fas fa-user"></i> {{ $inv->user->name }}
                                            @else
                                                <em class="text-muted">—</em>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="active">
                                    <td><strong>Totale fatturato</strong></td>
                                    <td class="text-right"><strong>€{{ number_format($totalInvoiced, 2, ',', '.') }}</strong></td>
                                    <td colspan="4">
                                        @if($remaining > 0.01)
                                            <small class="text-muted">Restante €{{ number_format($remaining, 2, ',', '.') }}</small>
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($hasInvoices)
    <script>
    document.querySelectorAll('.btn-regenerate-invoice').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.id;
            if (!confirm('Rigenerare XML e reinviare la fattura #' + id + '?')) return;
            var me = this;
            me.disabled = true;
            me.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('/backoffice/restaurant/table-order-invoices/' + id + '/regenerate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Errore: ' + data.message);
                    me.disabled = false;
                    me.innerHTML = '<i class="fas fa-sync-alt"></i> Rigenera';
                }
            })
            .catch(function() {
                alert('Errore di rete');
                me.disabled = false;
                me.innerHTML = '<i class="fas fa-sync-alt"></i> Rigenera';
            });
        });
    });
    </script>
    @endif
    @endif

    <!-- Corrispettivi Elettronici -->
    @php
        $corrispettivi = $sale->corrispettivi ?? collect();
        $statusBadgeCorr = [
            'pending'   => 'default',
            'sending'   => 'info',
            'sent'      => 'success',
            'failed'    => 'danger',
            'cancelled' => 'inverse',
        ];
    @endphp
    @if($corrispettivi->isNotEmpty())
    <div class="row mt-4">
        <div class="col-xs-12">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h4 class="panel-title" style="margin:0;">
                        <i class="fa fa-file-invoice"></i> Corrispettivi elettronici
                        <span class="label label-default" style="margin-left:8px;">{{ $corrispettivi->count() }}</span>
                    </h4>
                </div>
                <div class="panel-body p-0">
                    <div class="table-responsive">
                        <table class="table table-condensed table-hover table-striped">
                            <thead>
                                <tr class="active">
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
                            @foreach($corrispettivi as $c)
                                <tr>
                                    <td>
                                        @if($c->isAnnullo())
                                            <span class="label label-inverse">Annullo</span>
                                            @if($c->emissioneAnnullata)
                                                <small class="text-muted d-block">di #{{ $c->emissioneAnnullata->id }}</small>
                                            @endif
                                        @else
                                            <span class="label label-primary">Emissione</span>
                                        @endif
                                    </td>
                                    <td><small>{{ ($c->sent_at ?? $c->created_at)->format('d/m/Y H:i:s') }}</small></td>
                                    <td>{{ $c->precontoSplit?->label ?? '—' }}</td>
                                    <td>{{ $c->payment_method }}</td>
                                    <td class="text-right">€{{ number_format((float)$c->importo_totale, 2) }}</td>
                                    <td>@if($c->progressivo_sdi)<code>{{ $c->progressivo_sdi }}</code>@else — @endif</td>
                                    <td>@if($c->identificativo_sdi)<code>{{ $c->identificativo_sdi }}</code>@else — @endif</td>
                                    <td>
                                        <span class="label label-{{ $statusBadgeCorr[$c->status] ?? 'default' }}">
                                            {{ $c->getStatusLabel() }}
                                        </span>
                                        @if($c->last_error)
                                            <i class="fa fa-exclamation-triangle text-danger" title="{{ $c->last_error }}"></i>
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
                                                    <i class="fa fa-redo"></i> Riprova
                                                </button>
                                            </form>
                                        @endif
                                        @if($c->canCancel())
                                            <form action="{{ route('backoffice.corrispettivi.annulla', $c->id) }}"
                                                  method="POST" style="display:inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Annullare il corrispettivo {{ $c->progressivo_sdi }}? L\'operazione è irreversibile.');">
                                                    <i class="fa fa-ban"></i> Annulla
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
            </div>
        </div>
    </div>
    @endif

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
                                                    <br />
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
                                                    <span style="font-size: 13px" class="text-muted">{{ $log->notes }}</span>
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

    <!-- Cash Drawer Logs Section -->
    <div class="row mt-4">
        <div class="col-xs-12">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-money"></i> Log Cassa Automatica
                        <span class="label label-default" style="margin-left: 8px;">{{ $cashDrawerLogs->count() }} eventi</span>
                        @if($cashDrawerLogs->where('event_type', 'error')->count() > 0)
                            <span class="label label-danger" style="margin-left: 4px;">{{ $cashDrawerLogs->where('event_type', 'error')->count() }} errori</span>
                        @endif
                        @if($cashDrawerLogs->where('event_type', 'completed')->count() > 0)
                            <span class="label label-success" style="margin-left: 4px;"><i class="fa fa-check"></i> Pagato</span>
                        @endif
                        @if($cashDrawerLogs->where('event_type', 'cancel')->count() > 0)
                            <span class="label label-warning" style="margin-left: 4px;"><i class="fa fa-ban"></i> Annullato</span>
                        @endif
                    </h4>
                </div>
                <div class="panel-body p-0">
                    @if($cashDrawerLogs->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-condensed table-hover table-striped">
                                <thead>
                                    <tr class="active">
                                        <th width="140">Data/Ora</th>
                                        <th width="200">Operation ID</th>
                                        <th width="120" class="text-center">Evento</th>
                                        <th>Dettagli</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cashDrawerLogs as $cdLog)
                                        @php
                                            $badgeClass = match($cdLog->event_type) {
                                                'start'     => 'label-info',
                                                'completed' => 'label-success',
                                                'cancel'    => 'label-warning',
                                                'error'     => 'label-danger',
                                                default     => 'label-default',
                                            };
                                            $badgeIcon = match($cdLog->event_type) {
                                                'start'     => 'fa-play-circle',
                                                'completed' => 'fa-check-circle',
                                                'cancel'    => 'fa-ban',
                                                'error'     => 'fa-exclamation-triangle',
                                                default     => 'fa-circle',
                                            };
                                            $payload = $cdLog->payload ?? [];
                                        @endphp
                                        <tr>
                                            <td>
                                                <span>{{ $cdLog->created_at->format('d/m/Y') }}</span><br>
                                                <strong>{{ $cdLog->created_at->format('H:i:s') }}</strong>
                                            </td>
                                            <td>
                                                <code style="font-size:11px; word-break:break-all;">
                                                    {{ $cdLog->operation_id ?? '—' }}
                                                </code>
                                            </td>
                                            <td class="text-center">
                                                <span class="label {{ $badgeClass }}">
                                                    <i class="fa {{ $badgeIcon }}"></i>
                                                    {{ ucfirst($cdLog->event_type) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($cdLog->event_type === 'start')
                                                    @php $amount = $payload['amount'] ?? null; @endphp
                                                    @if($amount !== null)
                                                        Importo richiesto: <strong>€{{ number_format($amount, 2) }}</strong>
                                                    @endif

                                                @elseif($cdLog->event_type === 'completed')
                                                    @php $details = $payload['payment_details'] ?? []; @endphp
                                                    @if(!empty($details))
                                                        Inserito: <strong>€{{ number_format(($details['inserted'] ?? 0) / 100, 2) }}</strong>
                                                        &nbsp;·&nbsp;
                                                        Resto: <strong>€{{ number_format(($details['rest'] ?? 0) / 100, 2) }}</strong>
                                                        @if(isset($details['status']))
                                                            &nbsp;·&nbsp;
                                                            <span class="label label-success">{{ $details['status'] }}</span>
                                                        @endif
                                                    @else
                                                        Pagamento completato
                                                    @endif

                                                @elseif($cdLog->event_type === 'cancel')
                                                    Transazione annullata dall'operatore

                                                @elseif($cdLog->event_type === 'error')
                                                    @php $resp = $payload['response'] ?? []; @endphp
                                                    <span class="text-danger">
                                                        <i class="fa fa-exclamation-triangle"></i>
                                                        Errore comunicazione con la cassa
                                                        @if(!empty($resp))
                                                            &nbsp;—&nbsp;<small>{{ json_encode($resp) }}</small>
                                                        @endif
                                                    </span>

                                                @else
                                                    @if(!empty($payload))
                                                        <small><code>{{ json_encode($payload) }}</code></small>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-success text-center" style="margin: 15px;">
                            <i class="fa fa-info-circle"></i>
                            Nessun log cassa automatica registrato per questa vendita.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Ditron Receipts / Logs Section -->
    @php
        $ditronReceipts = $sale->ditronReceipts ?? collect();
        $statusBadgeDitron = [
            \App\Models\DitronReceipt::STATUS_PENDING => 'default',
            \App\Models\DitronReceipt::STATUS_SENDING => 'info',
            \App\Models\DitronReceipt::STATUS_SENT    => 'success',
            \App\Models\DitronReceipt::STATUS_FAILED  => 'danger',
        ];
    @endphp
    @if($ditronReceipts->isNotEmpty())
    <div class="row mt-4">
        <div class="col-xs-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4 class="panel-title" style="margin:0;">
                        <i class="fa fa-print"></i> Log Ditron (scontrini fiscali)
                        <span class="label label-default" style="margin-left:8px;">{{ $ditronReceipts->count() }}</span>
                        @if($ditronReceipts->where('status', \App\Models\DitronReceipt::STATUS_FAILED)->count() > 0)
                            <span class="label label-danger" style="margin-left:4px;">
                                {{ $ditronReceipts->where('status', \App\Models\DitronReceipt::STATUS_FAILED)->count() }} falliti
                            </span>
                        @endif
                        @if($ditronReceipts->where('type', \App\Models\DitronReceipt::TYPE_CANCEL)->count() > 0)
                            <span class="label label-warning" style="margin-left:4px;">
                                <i class="fa fa-ban"></i>
                                {{ $ditronReceipts->where('type', \App\Models\DitronReceipt::TYPE_CANCEL)->count() }} annulli
                            </span>
                        @endif
                    </h4>
                </div>
                <div class="panel-body p-0">
                    <div class="table-responsive">
                        <table class="table table-condensed table-hover table-striped">
                            <thead>
                                <tr class="active">
                                    <th>Tipo</th>
                                    <th>Data</th>
                                    <th>Preconto</th>
                                    <th>Pagamento</th>
                                    <th class="text-right">Totale</th>
                                    <th>N. Fiscale</th>
                                    <th>Z / Matricola</th>
                                    <th>Stato</th>
                                    <th>Tentativi</th>
                                    <th>Operatore</th>
                                    <th>Dettagli</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($ditronReceipts as $dr)
                                <tr>
                                    <td>
                                        @if($dr->isCancel())
                                            <span class="label label-inverse">Annullo</span>
                                            @if($dr->cancelsReceipt)
                                                <small class="text-muted d-block">di #{{ $dr->cancelsReceipt->id }}</small>
                                            @endif
                                        @else
                                            <span class="label label-primary">Vendita</span>
                                            @if($dr->isCancelled())
                                                <small class="text-danger d-block">
                                                    <i class="fa fa-ban"></i> annullata
                                                    @if($dr->cancelledByReceipt)
                                                        da #{{ $dr->cancelledByReceipt->id }}
                                                    @endif
                                                </small>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ ($dr->sent_at ?? $dr->created_at)->format('d/m/Y H:i:s') }}</small>
                                    </td>
                                    <td>{{ $dr->precontoSplit?->label ?? '—' }}</td>
                                    <td>{{ $dr->payment_method }}</td>
                                    <td class="text-right">€{{ number_format((float)$dr->importo_totale, 2) }}</td>
                                    <td>
                                        @if($dr->fiscal_number)
                                            <code>{{ $dr->fiscal_number }}</code>
                                            @if($dr->fiscal_date)
                                                <small class="text-muted d-block">{{ $dr->fiscal_date->format('d/m/Y') }}</small>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if($dr->z_number || $dr->matricola)
                                            @if($dr->z_number)<small>Z: <code>{{ $dr->z_number }}</code></small>@endif
                                            @if($dr->matricola)<small class="d-block text-muted">{{ $dr->matricola }}</small>@endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <span class="label label-{{ $statusBadgeDitron[$dr->status] ?? 'default' }}">
                                            {{ $dr->getStatusLabel() }}
                                        </span>
                                        @if($dr->last_error)
                                            <i class="fa fa-exclamation-triangle text-danger" title="{{ $dr->last_error }}"></i>
                                        @endif
                                    </td>
                                    <td>{{ $dr->attempts }}/{{ $dr->max_attempts }}</td>
                                    <td>{{ $dr->operator?->name ?? '—' }}</td>
                                    <td>
                                        @if($dr->elapsed_ms)
                                            <small class="text-muted">{{ $dr->elapsed_ms }} ms</small><br>
                                        @endif
                                        @if($dr->isCancelled() && $dr->cancelled_at)
                                            <small class="text-danger">
                                                Annullata: {{ $dr->cancelled_at->format('d/m/Y H:i') }}
                                                @if($dr->cancelledByUser)
                                                    da {{ $dr->cancelledByUser->name }}
                                                @endif
                                            </small>
                                            @if($dr->cancel_reason)
                                                <small class="d-block text-muted">{{ $dr->cancel_reason }}</small>
                                            @endif
                                        @endif
                                        @if($dr->last_error)
                                            <small class="text-danger d-block" style="word-break:break-word;">
                                                {{ \Illuminate\Support\Str::limit($dr->last_error, 120) }}
                                            </small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

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

    @if($isOpen)
    <!-- BO Action Modals -->

    <!-- Modal: Modifica Quantità -->
    <div id="modalItemQty" class="log-modal" onclick="if(event.target===this)toggleModal('modalItemQty')">
        <div class="log-modal-content">
            <div class="log-modal-header" style="background:#5cb85c;">
                <h5><i class="fas fa-hashtag"></i> Modifica Quantità</h5>
                <button type="button" onclick="toggleModal('modalItemQty')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <form id="formItemQty">
                    <div class="form-group">
                        <label>Quantità</label>
                        <input type="number" id="inputItemQty" class="form-control" min="1" value="1">
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalItemQty')">Annulla</button>
                        <button type="submit" class="btn btn-success" style="flex:2">Aggiorna</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Modifica Dettagli -->
    <div id="modalItemDetails" class="log-modal" onclick="if(event.target===this)toggleModal('modalItemDetails')">
        <div class="log-modal-content">
            <div class="log-modal-header" style="background:#5bc0de;">
                <h5><i class="fas fa-edit"></i> Modifica Note</h5>
                <button type="button" onclick="toggleModal('modalItemDetails')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <form id="formItemDetails">
                    <div class="form-group">
                        <label>Note per la cucina</label>
                        <textarea id="inputItemNotes" class="form-control" rows="3" placeholder="Note aggiuntive..."></textarea>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalItemDetails')">Annulla</button>
                        <button type="submit" class="btn btn-info" style="flex:2">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Cambia Piatto -->
    <div id="modalChangeDish" class="log-modal" onclick="if(event.target===this)toggleModal('modalChangeDish')">
        <div class="log-modal-content">
            <div class="log-modal-header" style="background:#f0ad4e;">
                <h5><i class="fas fa-exchange-alt"></i> Cambia Piatto</h5>
                <button type="button" onclick="toggleModal('modalChangeDish')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <form id="formChangeDish">
                    <div class="form-group">
                        <label>Nuovo Piatto</label>
                        <select id="selectChangeDish" class="form-control">
                            <option value="">-- Seleziona piatto --</option>
                        </select>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalChangeDish')">Annulla</button>
                        <button type="submit" class="btn btn-warning" style="flex:2">Cambia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Motivo Rimozione -->
    <div id="modalRemoveReason" class="log-modal" onclick="if(event.target===this)closeRemoveReasonModal()">
        <div class="log-modal-content" style="max-width:420px;">
            <div class="log-modal-header" style="background:#d9534f;">
                <h5><i class="fas fa-trash-alt"></i> Motivo della rimozione</h5>
                <button type="button" onclick="closeRemoveReasonModal()" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                    <button class="btn btn-default bo-remove-reason-btn" data-reason="Rientro">Rientro</button>
                    <button class="btn btn-default bo-remove-reason-btn" data-reason="Il cliente ha sostituito">Il cliente ha sostituito</button>
                    <button class="btn btn-default bo-remove-reason-btn" data-reason="Errore nel piatto">Errore nel piatto</button>
                    <button class="btn btn-default bo-remove-reason-btn" data-reason="Omaggiato">Omaggiato</button>
                    <button class="btn btn-default bo-remove-reason-btn" data-reason="Errore sala">Errore sala</button>
                    <button class="btn btn-default bo-remove-reason-btn" data-reason="Errore cucina">Errore cucina</button>
                </div>
                <button type="button" class="btn btn-default btn-block" onclick="closeRemoveReasonModal()">Annulla</button>
            </div>
        </div>
    </div>

    <!-- Modal: Aggiungi Piatto -->
    <div id="modalAddDish" class="log-modal" onclick="if(event.target===this)toggleModal('modalAddDish')">
        <div class="log-modal-content">
            <div class="log-modal-header" style="background:#337ab7;">
                <h5><i class="fas fa-plus"></i> Aggiungi Piatto</h5>
                <button type="button" onclick="toggleModal('modalAddDish')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <form id="formAddDish">
                    <div class="form-group">
                        <label>Categoria</label>
                        <select id="selectAddDishCategory" class="form-control">
                            <option value="">-- Tutte le categorie --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Piatto</label>
                        <select id="selectAddDish" class="form-control">
                            <option value="">-- Seleziona piatto --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantità</label>
                        <input type="number" id="inputAddDishQty" class="form-control" min="1" value="1">
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalAddDish')">Annulla</button>
                        <button type="submit" class="btn btn-primary" style="flex:2">Aggiungi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Pre-Conto -->
    <div id="modalPreconto" class="log-modal" onclick="if(event.target===this)toggleModal('modalPreconto')">
        <div class="log-modal-content" style="max-width:700px;">
            <div class="log-modal-header" style="background:#5bc0de;">
                <h5><i class="fas fa-receipt"></i> Pre-Conto</h5>
                <button type="button" onclick="toggleModal('modalPreconto')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <form id="formPreconto">
                    <div class="form-group">
                        <label><strong>Tipo di pre-conto</strong></label>
                        <div>
                            <label class="radio-inline">
                                <input type="radio" name="preconto_mode" value="full" checked> Intero
                            </label>
                            <label class="radio-inline" style="margin-left:20px;">
                                <input type="radio" name="preconto_mode" value="partial"> Parziale
                            </label>
                        </div>
                    </div>
                    <div id="precontoPartialItems" style="display:none;">
                        <label><strong>Seleziona prodotti</strong></label>
                        <div class="table-responsive">
                            <table class="table table-condensed table-bordered">
                                <thead>
                                    <tr>
                                        <th width="30">Sel.</th>
                                        <th>Prodotto</th>
                                        <th width="60" class="text-center">Qta tot.</th>
                                        <th width="90">Qta parziale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sale->items()->get() as $pItem)
                                    <tr>
                                        <td><input type="checkbox" class="preconto-item-check" data-item-id="{{ $pItem->id }}" checked></td>
                                        <td>{{ $pItem->dish->label ?? 'N/D' }}</td>
                                        <td class="text-center">{{ $pItem->quantity }}</td>
                                        <td><input type="number" class="preconto-qty form-control input-sm" value="{{ $pItem->quantity }}" min="1" max="{{ $pItem->quantity }}"></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalPreconto')">Annulla</button>
                        <button type="submit" class="btn btn-info" style="flex:2"><i class="fas fa-receipt"></i> Invia Pre-Conto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Incassa -->
    <div id="modalIncassa" class="log-modal" onclick="if(event.target===this)toggleModal('modalIncassa')">
        <div class="log-modal-content" style="max-width:500px;">
            <div class="log-modal-header" style="background:#5cb85c;">
                <h5><i class="fas fa-cash-register"></i> Incassa</h5>
                <button type="button" onclick="toggleModal('modalIncassa')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <form id="formIncassa">
                    <div class="alert alert-info" style="text-align:center;margin-bottom:15px;">
                        <strong>Totale: €{{ number_format($sale->total_amount, 2, ',', '.') }}</strong>
                    </div>
                    <div class="form-group">
                        <label><strong>Metodo di pagamento</strong></label>
                        <div>
                            <label class="radio-inline">
                                <input type="radio" name="payment_method" value="contanti"> Contanti
                            </label>
                            <label class="radio-inline" style="margin-left:15px;">
                                <input type="radio" name="payment_method" value="pos"> POS
                            </label>
                            <label class="radio-inline" style="margin-left:15px;">
                                <input type="radio" name="payment_method" value="fattura"> Fattura
                            </label>
                        </div>
                    </div>
                    <div id="cashFields" style="display:none;">
                        <div class="form-group">
                            <label>Importo ricevuto (€)</label>
                            <input type="number" id="inputAmountGiven" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Resto</label>
                            <div class="form-control" style="background:#f5f5f5;font-weight:bold;" id="restoCalcolato">—</div>
                        </div>
                    </div>
                    <div id="invoiceFields" style="display:none;">
                        <div class="form-group">
                            <label>Intestatario</label>
                            <input type="text" id="inputInvoiceName" class="form-control" placeholder="Nome / Ragione sociale">
                        </div>
                        <div class="form-group">
                            <label>Cod. Fiscale / P.IVA</label>
                            <input type="text" id="inputInvoiceTaxCode" class="form-control" placeholder="CODICE FISCALE o P.IVA">
                        </div>
                        <div class="form-group">
                            <label>Descrizione</label>
                            <input type="text" id="inputInvoiceDescription" class="form-control" value="Pasto completo" placeholder="Pasto completo">
                        </div>
                        <div class="form-group">
                            <label>Importo (vuoto = totale €{{ number_format($sale->total_amount, 2, ',', '.') }})</label>
                            <input type="number" id="inputInvoiceAmount" class="form-control" step="0.01" min="0" placeholder="{{ number_format($sale->total_amount, 2) }}">
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalIncassa')">Annulla</button>
                        <button type="submit" class="btn btn-success" style="flex:2"><i class="fas fa-cash-register"></i> Incassa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Incassa Preconto Split -->
    <div id="modalIncassaSplit" class="log-modal" onclick="if(event.target===this)toggleModal('modalIncassaSplit')">
        <div class="log-modal-content" style="max-width:420px;">
            <div class="log-modal-header" style="background:#5cb85c;">
                <h5><i class="fas fa-cash-register"></i> Incassa Preconto</h5>
                <button type="button" onclick="toggleModal('modalIncassaSplit')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <div class="alert alert-info" style="text-align:center;margin-bottom:15px;">
                    <span id="splitModalLabel"></span><br>
                    <strong>Importo: €<span id="splitModalTotal"></span></strong>
                </div>
                <form id="formIncassaSplit">
                    <input type="hidden" id="splitModalId">
                    <div class="form-group">
                        <label><strong>Metodo di pagamento</strong></label>
                        <div>
                            <label class="radio-inline">
                                <input type="radio" name="split_payment_method" value="contanti"> Contanti
                            </label>
                            <label class="radio-inline" style="margin-left:15px;">
                                <input type="radio" name="split_payment_method" value="pos"> POS
                            </label>
                            <label class="radio-inline" style="margin-left:15px;">
                                <input type="radio" name="split_payment_method" value="fattura"> Fattura
                            </label>
                            <label class="radio-inline" style="margin-left:15px;">
                                <input type="radio" name="split_payment_method" value="chiusura_conto"> Chiusura conto
                            </label>
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalIncassaSplit')">Annulla</button>
                        <button type="submit" class="btn btn-success" style="flex:2"><i class="fas fa-cash-register"></i> Conferma</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Chiudi Tavolo -->
    <div id="modalChiudiTavolo" class="log-modal" onclick="if(event.target===this)toggleModal('modalChiudiTavolo')">
        <div class="log-modal-content" style="max-width:460px;">
            <div class="log-modal-header" style="background:#d9534f;">
                <h5><i class="fas fa-times-circle"></i> Chiudi Tavolo</h5>
                <button type="button" onclick="toggleModal('modalChiudiTavolo')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <div class="alert alert-danger" style="display:block;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Attenzione!</strong><br>
                    Questa operazione <strong>elimina tutti i piatti</strong> e chiude il tavolo <strong>senza incassare</strong>.<br>
                    L'azione non può essere annullata.
                </div>
                <p>Tavolo: <strong>{{ $sale->restaurantTable->table_number }}</strong> &mdash; Totale: <strong>€{{ number_format($sale->total_amount, 2, ',', '.') }}</strong></p>
                <div style="display:flex;gap:10px;margin-top:15px;">
                    <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalChiudiTavolo')">Annulla</button>
                    <button type="button" class="btn btn-danger" style="flex:2" id="btnConfirmChiudiTavolo">
                        <i class="fas fa-times-circle"></i> Conferma Chiusura
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Sposta Tavolo -->
    <div id="modalSposta" class="log-modal" onclick="if(event.target===this)toggleModal('modalSposta')">
        <div class="log-modal-content">
            <div class="log-modal-header" style="background:#337ab7;">
                <h5><i class="fas fa-arrows-alt"></i> Sposta Tavolo</h5>
                <button type="button" onclick="toggleModal('modalSposta')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <form id="formSposta">
                    <div class="form-group">
                        <label>Tavolo di destinazione</label>
                        <select id="selectTargetTable" class="form-control">
                            <option value="">-- Seleziona tavolo --</option>
                        </select>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalSposta')">Annulla</button>
                        <button type="submit" class="btn btn-primary" style="flex:2">Sposta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Comunica -->
    <div id="modalComunica" class="log-modal" onclick="if(event.target===this)toggleModal('modalComunica')">
        <div class="log-modal-content">
            <div class="log-modal-header" style="background:#337ab7;">
                <h5><i class="fas fa-comments"></i> Comunica</h5>
                <button type="button" onclick="toggleModal('modalComunica')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <form id="formComunica">
                    <div class="form-group">
                        <label>Stampante</label>
                        <select id="selectComunicaPrinter" class="form-control">
                            <option value="">-- Seleziona stampante --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Messaggio</label>
                        <textarea id="inputComunicaMessage" class="form-control" rows="4" placeholder="Messaggio da inviare..."></textarea>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalComunica')">Annulla</button>
                        <button type="submit" class="btn btn-primary" style="flex:2"><i class="fas fa-paper-plane"></i> Invia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Sconto -->
    <div id="modalSconto" class="log-modal" onclick="if(event.target===this)toggleModal('modalSconto')">
        <div class="log-modal-content">
            <div class="log-modal-header" style="background:#d9534f;">
                <h5><i class="fas fa-percent"></i> Applica Sconto</h5>
                <button type="button" onclick="toggleModal('modalSconto')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <form id="formSconto">
                    <div class="alert alert-info" style="text-align:center;margin-bottom:15px;">
                        Totale attuale: <strong>€{{ number_format($sale->total_amount, 2, ',', '.') }}</strong>
                    </div>
                    <div class="form-group">
                        <label><strong>Tipo di sconto</strong></label>
                        <div>
                            <label class="radio-inline">
                                <input type="radio" name="discount_type" value="percent" checked> Percentuale (%)
                            </label>
                            <label class="radio-inline" style="margin-left:20px;">
                                <input type="radio" name="discount_type" value="value"> Valore fisso (€)
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label id="scontoAmountLabel">Sconto (%)</label>
                        <input type="number" id="inputSconto" class="form-control" min="0" step="0.5" placeholder="Es: 10">
                    </div>
                    <div class="form-group">
                        <label>Totale dopo sconto</label>
                        <div class="form-control" style="background:#f5f5f5;font-weight:bold;font-size:16px;" id="scontoFinalTotal">—</div>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalSconto')">Annulla</button>
                        <button type="submit" class="btn btn-danger" style="flex:2">Applica Sconto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Coperti -->
    <div id="modalCoperti" class="log-modal" onclick="if(event.target===this)toggleModal('modalCoperti')">
        <div class="log-modal-content">
            <div class="log-modal-header" style="background:#337ab7;">
                <h5><i class="fas fa-users"></i> Modifica Coperti</h5>
                <button type="button" onclick="toggleModal('modalCoperti')" class="log-modal-close">&times;</button>
            </div>
            <div class="log-modal-body">
                <form id="formCoperti">
                    <div class="form-group">
                        <label>Numero coperti</label>
                        <input type="number" id="inputCoperti" class="form-control" min="0" value="{{ $sale->covers }}">
                    </div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-default" style="flex:1" onclick="toggleModal('modalCoperti')">Annulla</button>
                        <button type="submit" class="btn btn-primary" style="flex:2">Aggiorna</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

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
        @if($sale->status === 'paid' && auth()->user()?->role === 'admin')
        // --- Admin: cambio metodo di pagamento su vendita chiusa ---
        (function() {
            var form = document.getElementById('formChangePaymentMethod');
            if (!form) return;
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var select = document.getElementById('selectNewPaymentMethod');
                var reasonEl = document.getElementById('inputChangePaymentReason');
                var newMethod = select.value;
                if (!newMethod) {
                    alert('Seleziona un metodo di pagamento.');
                    return;
                }
                var currentLabel = select.options[select.selectedIndex].text;
                if (!confirm('Confermi il cambio metodo di pagamento in "' + currentLabel + '"?\n\nATTENZIONE: operazione IRREVERSIBILE. Lo scontrino fiscale eventualmente già emesso NON viene annullato.')) {
                    return;
                }
                var btn = document.getElementById('btnConfirmChangePaymentMethod');
                var original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Aggiornamento…';
                fetch('/backoffice/restaurant/sales/{{ $sale->id }}/payment-method', {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        payment_method: newMethod,
                        reason: reasonEl.value || null
                    })
                })
                .then(function(r) { return r.json().then(function(d){ return {ok: r.ok, data: d}; }); })
                .then(function(res) {
                    if (res.ok && res.data.success) {
                        alert(res.data.message || 'Metodo di pagamento aggiornato.');
                        window.location.reload();
                    } else {
                        alert('Errore: ' + (res.data.message || 'operazione fallita'));
                        btn.disabled = false;
                        btn.innerHTML = original;
                    }
                })
                .catch(function() {
                    alert('Errore di rete.');
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
            });
        })();

        // --- Admin: emissione manuale scontrino fiscale ---
        (function() {
            var btn = document.getElementById('btnEmitFiscalReceipt');
            if (!btn) return;
            btn.addEventListener('click', function() {
                if (btn.disabled) return;
                if (!confirm('Confermi l\'emissione dello scontrino fiscale Ditron per questa vendita?\n\nATTENZIONE: azione IRREVERSIBILE. Verrà inviato alla cassa fiscale con il metodo di pagamento attualmente registrato.')) {
                    return;
                }
                var original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Emissione in corso…';
                fetch('/backoffice/restaurant/sales/{{ $sale->id }}/emit-fiscal-receipt', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(function(r) { return r.json().then(function(d){ return {ok: r.ok, status: r.status, data: d}; }); })
                .then(function(res) {
                    var msg = res.data.message || (res.data.success ? 'Scontrino emesso.' : 'Operazione fallita.');
                    alert(msg);
                    if (res.data.success || res.status === 202) {
                        window.location.reload();
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = original;
                    }
                })
                .catch(function() {
                    alert('Errore di rete durante l\'emissione.');
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
            });
        })();
        @endif

        @if($hasInvoices)
        document.querySelectorAll('.btn-regenerate-invoice').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.dataset.id;
                if (!confirm('Rigenerare XML e reinviare la fattura #' + id + '?')) return;
                var me = this;
                me.disabled = true;
                me.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch('/backoffice/restaurant/table-order-invoices/' + id + '/regenerate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    }
                })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert('Errore: ' + data.message);
                            me.disabled = false;
                            me.innerHTML = '<i class="fas fa-sync-alt"></i> Rigenera';
                        }
                    })
                    .catch(function() {
                        alert('Errore di rete');
                        me.disabled = false;
                        me.innerHTML = '<i class="fas fa-sync-alt"></i> Rigenera';
                    });
            });
        });
   @endif
    </script>

@endsection

@section('custom-script')
    @if($isOpen)
    <script>
    $(function() {
        var _boSale = window._boSale;
        var csrfToken = '{{ csrf_token() }}';
        var _boToken = null;

        // Fetch operator token on page load
        $.ajax({
            url: '/api/admin/operator-token',
            method: 'GET',
            headers: { 'X-CSRF-TOKEN': csrfToken }
        }).done(function(data) {
            if (data.success) {
                _boToken = data.data.token;
            } else {
                console.warn('Bo token fetch failed:', data);
            }
        }).fail(function() {
            console.warn('Failed to fetch operator token');
        });

        // API helper
        function boApi(method, url, data) {
            var opts = {
                url: url,
                method: method,
                headers: {
                    'X-Operator-Token': _boToken,
                    'X-CSRF-TOKEN': csrfToken
                }
            };
            if (data !== undefined && data !== null) {
                opts.data = JSON.stringify(data);
                opts.contentType = 'application/json';
            }
            return $.ajax(opts);
        }

        function onSuccess(msg) {
            if (typeof toastr !== 'undefined') {
                toastr.success(msg);
            }
            setTimeout(function() { location.reload(); }, 900);
        }

        function onError(xhr) {
            var msg = 'Errore durante l\'operazione';
            if (xhr.responseJSON) {
                msg = xhr.responseJSON.message || xhr.responseJSON.error || msg;
            }
            if (typeof toastr !== 'undefined') {
                toastr.error(msg);
            } else {
                alert('Errore: ' + msg);
            }
        }

        function requireToken() {
            if (!_boToken) {
                alert('Token operatore non disponibile. Ricarica la pagina.');
                return false;
            }
            return true;
        }

        // ---- Simple button actions ----

        $('[data-action="marcia"]').on('click', function() {
            if (!requireToken()) return;
            var btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            boApi('POST', '/api/tables/' + _boSale.tableId + '/marcia')
                .done(function() { onSuccess('Marcia inviata!'); })
                .fail(function(xhr) {
                    onError(xhr);
                    btn.prop('disabled', false).html('<i class="fas fa-utensils"></i> Marcia');
                });
        });

        $('[data-action="reprint"]').on('click', function() {
            if (!requireToken()) return;
            var btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            boApi('POST', '/api/tables/' + _boSale.tableId + '/reprint')
                .done(function() { onSuccess('Ristampa inviata!'); })
                .fail(function(xhr) {
                    onError(xhr);
                    btn.prop('disabled', false).html('<i class="fas fa-print"></i> Ristampa');
                });
        });

        $('[data-action="autoconsumo"]').on('click', function() {
            if (!requireToken()) return;
            if (!confirm('Marcare questa vendita come autoconsumo? Il totale verrà azzerato.')) return;
            var btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            boApi('POST', '/api/tables/' + _boSale.tableId + '/clear')
                .done(function() { onSuccess('Autoconsumo impostato!'); })
                .fail(function(xhr) {
                    onError(xhr);
                    btn.prop('disabled', false).html('<i class="fas fa-user-check"></i> Autoconsumo');
                });
        });

        // ---- Chiudi Tavolo ----
        $('#btnConfirmChiudiTavolo').on('click', function() {
            if (!requireToken()) return;
            var btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Chiusura...');
            boApi('POST', '/api/tables/' + _boSale.tableId + '/clear')
                .done(function() { toggleModal('modalChiudiTavolo'); onSuccess('Tavolo chiuso con successo!'); })
                .fail(function(xhr) {
                    onError(xhr);
                    btn.prop('disabled', false).html('<i class="fas fa-times-circle"></i> Conferma Chiusura');
                });
        });

        // ---- Item: Modifica Quantità ----
        $(document).on('click', '.btn-item-qty', function() {
            var itemId = $(this).data('item-id');
            var qty = $(this).data('qty');
            $('#modalItemQty').data('item-id', itemId);
            $('#inputItemQty').val(qty);
            toggleModal('modalItemQty');
        });

        $('#formItemQty').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var itemId = $('#modalItemQty').data('item-id');
            var qty = parseInt($('#inputItemQty').val());
            if (!qty || qty < 1) { alert('Quantità non valida'); return; }
            boApi('PUT', '/api/tables/items/' + itemId + '/quantity', { quantity: qty })
                .done(function() { toggleModal('modalItemQty'); onSuccess('Quantità aggiornata!'); })
                .fail(onError);
        });

        // ---- Item: Modifica Prezzo ----
        $(document).on('click', '.btn-item-price', function() {
            var itemId = $(this).data('item-id');
            var price = parseFloat($(this).data('price')).toFixed(2);
            var newPrice = prompt('Nuovo prezzo (€):', price);
            if (newPrice === null) return;
            newPrice = parseFloat(String(newPrice).replace(',', '.'));
            if (isNaN(newPrice) || newPrice < 0) { alert('Prezzo non valido'); return; }
            if (!requireToken()) return;
            boApi('PUT', '/api/tables/items/' + itemId + '/price', { price: newPrice })
                .done(function() { onSuccess('Prezzo aggiornato!'); })
                .fail(onError);
        });

        // ---- Item: Rimuovi ----
        var _pendingRemoveItemId = null;

        function closeRemoveReasonModal() {
            toggleModal('modalRemoveReason');
            _pendingRemoveItemId = null;
        }
        window.closeRemoveReasonModal = closeRemoveReasonModal;

        $(document).on('click', '.btn-item-remove', function() {
            if (!requireToken()) return;
            _pendingRemoveItemId = $(this).data('item-id');
            toggleModal('modalRemoveReason');
        });

        $(document).on('click', '.bo-remove-reason-btn', function() {
            if (!_pendingRemoveItemId) return;
            var itemId = _pendingRemoveItemId;
            var reason = $(this).data('reason');
            closeRemoveReasonModal();
            boApi('DELETE', '/api/tables/items/' + itemId, { reason: reason })
                .done(function() { onSuccess('Prodotto rimosso!'); })
                .fail(onError);
        });

        // ---- Item: Modifica Dettagli ----
        $(document).on('click', '.btn-item-details', function() {
            var itemId = $(this).data('item-id');
            var notes = $(this).data('notes') || '';
            $('#modalItemDetails').data('item-id', itemId);
            $('#inputItemNotes').val(notes);
            toggleModal('modalItemDetails');
        });

        $('#formItemDetails').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var itemId = $('#modalItemDetails').data('item-id');
            var notes = $('#inputItemNotes').val();
            boApi('PUT', '/api/tables/items/' + itemId + '/details', { notes: notes })
                .done(function() { toggleModal('modalItemDetails'); onSuccess('Dettagli aggiornati!'); })
                .fail(onError);
        });

        // ---- Item: Cambia Piatto ----
        $(document).on('click', '.btn-item-change-dish', function() {
            var itemId = $(this).data('item-id');
            $('#modalChangeDish').data('item-id', itemId);
            loadDishes('#selectChangeDish', '#modalChangeDish');
            toggleModal('modalChangeDish');
        });

        $('#formChangeDish').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var itemId = $('#modalChangeDish').data('item-id');
            var dishId = $('#selectChangeDish').val();
            if (!dishId) { alert('Seleziona un piatto'); return; }
            boApi('PUT', '/api/tables/items/' + itemId + '/dish', { dish_id: parseInt(dishId) })
                .done(function() { toggleModal('modalChangeDish'); onSuccess('Piatto cambiato!'); })
                .fail(onError);
        });

        // ---- Aggiungi Piatto ----
        window.boOpenAddDish = function() {
            loadDishes('#selectAddDish', '#modalAddDish');
            toggleModal('modalAddDish');
        };

        $('#formAddDish').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var dishId = $('#selectAddDish').val();
            var qty = parseInt($('#inputAddDishQty').val()) || 1;
            if (!dishId) { alert('Seleziona un piatto'); return; }
            boApi('POST', '/api/tables/' + _boSale.tableId + '/items', { dish_id: parseInt(dishId), quantity: qty })
                .done(function() { toggleModal('modalAddDish'); onSuccess('Piatto aggiunto!'); })
                .fail(onError);
        });

        // ---- Pre-Conto ----
        $('input[name="preconto_mode"]').on('change', function() {
            $('#precontoPartialItems').toggle($(this).val() === 'partial');
        });

        $('#formPreconto').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var mode = $('input[name="preconto_mode"]:checked').val();
            var data = {};
            if (mode === 'partial') {
                var items = [];
                $('.preconto-item-check:checked').each(function() {
                    items.push({
                        item_id: parseInt($(this).data('item-id')),
                        quantity: parseInt($(this).closest('tr').find('.preconto-qty').val()) || 1
                    });
                });
                if (items.length === 0) { alert('Seleziona almeno un prodotto'); return; }
                data.items = items;
            }
            boApi('POST', '/api/tables/' + _boSale.tableId + '/preconto', data)
                .done(function() { toggleModal('modalPreconto'); onSuccess('Pre-conto inviato!'); })
                .fail(onError);
        });

        // ---- Incassa ----
        $('input[name="payment_method"]').on('change', function() {
            var val = $(this).val();
            $('#invoiceFields').toggle(val === 'fattura');
            $('#cashFields').toggle(val === 'contanti');
        });

        $('#inputAmountGiven').on('input', function() {
            var given = parseFloat($(this).val()) || 0;
            var total = parseFloat('{{ $sale->total_amount }}') || 0;
            var resto = given - total;
            $('#restoCalcolato').text(resto >= 0 ? '€' + resto.toFixed(2) : '—');
        });

        $('#formIncassa').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var method = $('input[name="payment_method"]:checked').val();
            if (!method) { alert('Seleziona il metodo di pagamento'); return; }
            if (method === 'fattura') {
                var invoiceData = {
                    customer_name: $('#inputInvoiceName').val(),
                    customer_tax_code: $('#inputInvoiceTaxCode').val(),
                    description: $('#inputInvoiceDescription').val() || 'Pasto completo',
                    amount: parseFloat($('#inputInvoiceAmount').val()) || null
                };
                boApi('POST', '/api/tables/' + _boSale.tableId + '/pay-invoice', invoiceData)
                    .done(function() { toggleModal('modalIncassa'); onSuccess('Fattura emessa e ordine chiuso!'); })
                    .fail(onError);
            } else {
                var payData = { payment_method: method };
                if (method === 'contanti') {
                    payData.amount_given = parseFloat($('#inputAmountGiven').val()) || 0;
                }
                boApi('POST', '/api/tables/' + _boSale.tableId + '/pay', payData)
                    .done(function() { toggleModal('modalIncassa'); onSuccess('Incasso completato!'); })
                    .fail(onError);
            }
        });

        // ---- Sposta Tavolo ----
        window.boOpenSposta = function() {
            loadAvailableTables();
            toggleModal('modalSposta');
        };

        function loadAvailableTables() {
            var sel = $('#selectTargetTable');
            sel.prop('disabled', true).html('<option>Caricamento...</option>');
            $.ajax({
                url: '/api/tables/',
                headers: { 'X-Operator-Token': _boToken, 'X-CSRF-TOKEN': csrfToken }
            }).done(function(d) {
                var tables = d.data || d;
                sel.empty().append('<option value="">-- Seleziona tavolo --</option>');
                if (Array.isArray(tables)) {
                    tables.forEach(function(t) {
                        if (t.id != _boSale.tableId && t.status === 'free') {
                            var label = 'Tavolo ' + t.table_number;
                            if (t.status === 'open') label += ' (occupato)';
                            sel.append('<option value="' + t.id + '">' + label + '</option>');
                        }
                    });
                }
                sel.prop('disabled', false);
            }).fail(function() {
                sel.html('<option value="">Errore caricamento</option>').prop('disabled', false);
            });
        }

        $('#formSposta').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var targetId = $('#selectTargetTable').val();
            if (!targetId) { alert('Seleziona un tavolo di destinazione'); return; }
            boApi('POST', '/api/tables/' + _boSale.tableId + '/move', { destination_table_id: parseInt(targetId) })
                .done(function() { toggleModal('modalSposta'); onSuccess('Tavolo spostato!'); })
                .fail(onError);
        });

        // ---- Comunica ----
        window.boOpenComunica = function() {
            loadPrinters();
            toggleModal('modalComunica');
        };

        function loadPrinters() {
            var sel = $('#selectComunicaPrinter');
            sel.prop('disabled', true).html('<option>Caricamento...</option>');
            $.ajax({
                url: '/api/tables/printers',
                headers: { 'X-Operator-Token': _boToken, 'X-CSRF-TOKEN': csrfToken }
            }).done(function(d) {
                var printers = d.data || d;
                sel.empty().append('<option value="">-- Seleziona stampante --</option>');
                if (Array.isArray(printers)) {
                    printers.forEach(function(p) {
                        sel.append('<option value="' + p.id + '">' + (p.label || p.name) + '</option>');
                    });
                }
                sel.prop('disabled', false);
            }).fail(function() {
                sel.html('<option value="">Errore caricamento</option>').prop('disabled', false);
            });
        }

        $('#formComunica').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var printerId = $('#selectComunicaPrinter').val();
            var message = $('#inputComunicaMessage').val();
            if (!printerId) { alert('Seleziona una stampante'); return; }
            if (!message.trim()) { alert('Inserisci un messaggio'); return; }
            boApi('POST', '/api/tables/comunica', {
                printer_id: parseInt(printerId),
                message: message,
                table_id: _boSale.tableId
            })
                .done(function() { toggleModal('modalComunica'); onSuccess('Messaggio inviato!'); })
                .fail(onError);
        });

        // ---- Sconto ----
        var _scontoOriginalTotal = parseFloat('{{ $sale->total_amount }}') || 0;

        function calcScontoFinal() {
            var type = $('input[name="discount_type"]:checked').val();
            var amount = parseFloat($('#inputSconto').val()) || 0;
            var final;
            if (type === 'percent') {
                if (amount < 0 || amount > 100) { $('#scontoFinalTotal').text('—'); return null; }
                final = Math.max(0, _scontoOriginalTotal - _scontoOriginalTotal * amount / 100);
            } else {
                if (amount < 0) { $('#scontoFinalTotal').text('—'); return null; }
                final = Math.max(0, _scontoOriginalTotal - amount);
            }
            final = Math.round(final * 100) / 100;
            $('#scontoFinalTotal').text('€' + final.toFixed(2).replace('.', ','));
            return final;
        }

        $('input[name="discount_type"]').on('change', function() {
            var isPercent = $(this).val() === 'percent';
            $('#scontoAmountLabel').text(isPercent ? 'Sconto (%)' : 'Sconto (€)');
            $('#inputSconto').attr('max', isPercent ? 100 : '').attr('placeholder', isPercent ? 'Es: 10' : 'Es: 5.00');
            calcScontoFinal();
        });

        $('#inputSconto').on('input', function() { calcScontoFinal(); });

        $('#formSconto').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var type = $('input[name="discount_type"]:checked').val();
            var amount = parseFloat($('#inputSconto').val());
            if (isNaN(amount) || amount < 0) { alert('Inserisci un valore di sconto valido'); return; }
            if (type === 'percent' && amount > 100) { alert('La percentuale non può superare 100'); return; }
            var finalTotal = calcScontoFinal();
            if (finalTotal === null) { alert('Valore non valido'); return; }
            boApi('POST', '/api/tables/' + _boSale.tableId + '/apply-discount', {
                discount_type: type,
                discount_amount: amount,
                original_total: _scontoOriginalTotal,
                final_total: finalTotal
            })
                .done(function() { toggleModal('modalSconto'); onSuccess('Sconto applicato!'); })
                .fail(onError);
        });

        // ---- Coperti ----
        $('#formCoperti').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var covers = parseInt($('#inputCoperti').val());
            if (isNaN(covers) || covers < 0) { alert('Numero coperti non valido'); return; }
            boApi('PUT', '/api/tables/' + _boSale.tableId + '/covers', { covers: covers })
                .done(function() { toggleModal('modalCoperti'); onSuccess('Coperti aggiornati!'); })
                .fail(onError);
        });

        // ---- Incassa Preconto Split ----
        $(document).on('click', '.btn-incassa-split', function() {
            var splitId = $(this).data('split-id');
            var splitTotal = $(this).data('split-total');
            var splitLabel = $(this).data('split-label');
            $('#splitModalId').val(splitId);
            $('#splitModalLabel').text(splitLabel);
            $('#splitModalTotal').text(splitTotal);
            $('input[name="split_payment_method"]').prop('checked', false);
            toggleModal('modalIncassaSplit');
        });

        $('#formIncassaSplit').on('submit', function(e) {
            e.preventDefault();
            if (!requireToken()) return;
            var splitId = $('#splitModalId').val();
            var method = $('input[name="split_payment_method"]:checked').val();
            if (!method) { alert('Seleziona il metodo di pagamento'); return; }
            var btn = $(this).find('[type=submit]').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            boApi('POST', '/api/tables/' + _boSale.tableId + '/pay-split/' + splitId, { payment_method: method })
                .done(function(data) {
                    toggleModal('modalIncassaSplit');
                    if (data.data && data.data.order_closed) {
                        onSuccess('Conto chiuso completamente!');
                    } else {
                        onSuccess('Preconto incassato! Rimanente: €' + parseFloat(data.data.remaining || 0).toFixed(2));
                    }
                })
                .fail(function(xhr) {
                    onError(xhr);
                    btn.prop('disabled', false).html('<i class="fas fa-cash-register"></i> Conferma');
                });
        });

        // ---- Helper: carica piatti ----
        var _allDishes = null;

        function renderDishOptions(dishes, sel) {
            sel.empty().append('<option value="">-- Seleziona piatto --</option>');
            dishes.forEach(function(dish) {
                var label = dish.label || dish.name || 'N/D';
                if (dish.price) label += ' (€' + parseFloat(dish.price).toFixed(2) + ')';
                sel.append('<option value="' + dish.id + '">' + label + '</option>');
            });
        }

        function loadDishes(selectEl, modalEl) {
            var sel = $(selectEl);
            var isAddModal = (selectEl === '#selectAddDish');

            sel.prop('disabled', true).html('<option>Caricamento piatti...</option>');

            var doLoad = function(dishes) {
                renderDishOptions(dishes, sel);
                sel.prop('disabled', false);
                if ($.fn.select2 && modalEl) {
                    try { sel.select2({ dropdownParent: $(modalEl) }); } catch(e) {}
                }
            };

            if (_allDishes) {
                doLoad(isAddModal ? filterDishesByCategory(_allDishes, $('#selectAddDishCategory').val()) : _allDishes);
                return;
            }

            $.get('/api/dishes', function(d) {
                _allDishes = d.data || d;

                if (isAddModal) {
                    // Popola il filtro categorie
                    var catSel = $('#selectAddDishCategory');
                    var seen = {};
                    catSel.empty().append('<option value="">-- Tutte le categorie --</option>');
                    _allDishes.forEach(function(dish) {
                        if (dish.category_id && !seen[dish.category_id]) {
                            seen[dish.category_id] = true;
                            catSel.append('<option value="' + dish.category_id + '">' + (dish.category_name || 'N/D') + '</option>');
                        }
                    });
                }

                doLoad(isAddModal ? _allDishes : _allDishes);
            }).fail(function() {
                sel.html('<option value="">Errore caricamento piatti</option>').prop('disabled', false);
            });
        }

        function filterDishesByCategory(dishes, categoryId) {
            if (!categoryId) return dishes;
            return dishes.filter(function(d) { return d.category_id == categoryId; });
        }

        $('#selectAddDishCategory').on('change', function() {
            var sel = $('#selectAddDish');
            renderDishOptions(filterDishesByCategory(_allDishes || [], $(this).val()), sel);
            if ($.fn.select2) {
                try { sel.select2({ dropdownParent: $('#modalAddDish') }); } catch(e) {}
            }
        });
    });
    </script>
    @endif
@endsection

