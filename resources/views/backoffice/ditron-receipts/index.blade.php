@extends('backoffice.layout', ['title' => 'Scontrini Ditron'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Scontrini Ditron'],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif

            <div class="panel panel-default">
                <div class="panel-body">
                    <form method="GET" action="{{ route('backoffice.ditron.receipts.index') }}" class="mb-4">
                        <div class="row g-1 advanced-search">
                            <div class="col-md-2">
                                <label>Dal</label>
                                <input type="date" name="from" value="{{ $from }}" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label>Al</label>
                                <input type="date" name="to" value="{{ $to }}" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label>Tipo</label>
                                <select name="type" class="form-control">
                                    <option value="all"    {{ $type === 'all' ? 'selected' : '' }}>Tutti</option>
                                    <option value="sale"   {{ $type === 'sale' ? 'selected' : '' }}>Vendite</option>
                                    <option value="cancel" {{ $type === 'cancel' ? 'selected' : '' }}>Annulli</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>Stato</label>
                                <select name="status" class="form-control">
                                    <option value="">Tutti</option>
                                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>In attesa</option>
                                    <option value="sending" {{ $status === 'sending' ? 'selected' : '' }}>In invio</option>
                                    <option value="sent"    {{ $status === 'sent' ? 'selected' : '' }}>Emesso</option>
                                    <option value="failed"  {{ $status === 'failed' ? 'selected' : '' }}>Fallito</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>N° tavolo</label>
                                <input type="number" name="table_number" value="{{ $tableNumber }}" class="form-control" placeholder="es. 5">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-search"></i> Filtra
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tipo</th>
                                <th>Data</th>
                                <th>Tavolo</th>
                                <th>N° Fiscale</th>
                                <th>Z</th>
                                <th>Matricola</th>
                                <th>Importo</th>
                                <th>Pagamento</th>
                                <th>Stato</th>
                                <th>Operatore</th>
                                <th>Azioni</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($receipts as $r)
                                <tr class="{{ $r->isCancelled() ? 'text-muted' : '' }}">
                                    <td>{{ $r->id }}</td>
                                    <td>
                                        @if($r->isCancel())
                                            <span class="badge badge-danger">Annullo</span>
                                        @else
                                            <span class="badge badge-info">Vendita</span>
                                        @endif
                                        @if($r->isCancelled())
                                            <br><small class="text-danger">✗ annullato</small>
                                        @endif
                                    </td>
                                    <td>{{ $r->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @if($r->tableOrder && $r->tableOrder->restaurantTable)
                                            #{{ $r->tableOrder->restaurantTable->table_number }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $r->fiscal_number ?? '—' }}</strong>
                                        @if($r->fiscal_date)
                                            <br><small>{{ $r->fiscal_date->format('d/m/Y') }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $r->z_number ?? '—' }}</td>
                                    <td><small>{{ $r->matricola ?? '—' }}</small></td>
                                    <td>€ {{ number_format((float) $r->importo_totale, 2, ',', '.') }}</td>
                                    <td>{{ $r->payment_method }}</td>
                                    <td>
                                        <span class="badge badge-{{ $r->isSent() ? 'success' : ($r->isFailed() ? 'danger' : 'warning') }}">
                                            {{ $r->getStatusLabel() }}
                                        </span>
                                        @if($r->last_error)
                                            <br><small class="text-danger" title="{{ $r->last_error }}">{{ \Illuminate\Support\Str::limit($r->last_error, 40) }}</small>
                                        @endif
                                    </td>
                                    <td><small>{{ $r->operator->name ?? '—' }}</small></td>
                                    <td>
                                        @if($r->isCancellable())
                                            <button type="button" class="btn btn-sm btn-danger"
                                                    data-toggle="modal" data-target="#cancelModal-{{ $r->id }}">
                                                <i class="fa fa-ban"></i> Annulla
                                            </button>
                                        @elseif($r->isCancel() && $r->cancelsReceipt)
                                            <small>annulla #{{ $r->cancels_receipt_id }}</small>
                                        @elseif($r->isCancelled() && $r->cancelledByReceipt)
                                            <small>annullato da #{{ $r->cancelled_by_receipt_id }}<br>
                                                {{ $r->cancelled_at?->format('d/m/Y H:i') }}</small>
                                        @endif
                                    </td>
                                </tr>

                                @if($r->isCancellable())
                                    <div class="modal fade" id="cancelModal-{{ $r->id }}" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <form method="POST" action="{{ route('backoffice.ditron.receipts.cancel', $r) }}">
                                                @csrf
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">Emissione documento di annullamento</h4>
                                                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-warning">
                                                            <strong>⚠️ Attenzione:</strong> stai per emettere un <strong>DOCANNULLO</strong> sulla cassa fiscale.
                                                            L'operazione è tracciata nel registro fiscale e verrà trasmessa all'Agenzia delle Entrate con la prossima chiusura Z.
                                                        </div>
                                                        <p><strong>Scontrino da annullare:</strong></p>
                                                        <ul>
                                                            <li>N° fiscale: <strong>{{ $r->fiscal_number }}</strong> del {{ $r->fiscal_date->format('d/m/Y') }}</li>
                                                            <li>Chiusura Z: {{ $r->z_number }}</li>
                                                            <li>Matricola: {{ $r->matricola }}</li>
                                                            <li>Importo: <strong>€ {{ number_format((float) $r->importo_totale, 2, ',', '.') }}</strong></li>
                                                            @if($r->tableOrder && $r->tableOrder->restaurantTable)
                                                                <li>Tavolo: #{{ $r->tableOrder->restaurantTable->table_number }}</li>
                                                            @endif
                                                        </ul>
                                                        <div class="form-group">
                                                            <label>Motivazione (obbligatoria)</label>
                                                            <textarea name="reason" class="form-control" rows="3"
                                                                      required minlength="5" maxlength="500"
                                                                      placeholder="Es. errore battitura, scontrino duplicato, cliente ha annullato ordine…"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                                        <button type="submit" class="btn btn-danger">
                                                            <i class="fa fa-ban"></i> Emetti annullo
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted">Nessuno scontrino nel periodo selezionato.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center">
                        {{ $receipts->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
