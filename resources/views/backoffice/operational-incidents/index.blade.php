@extends('backoffice.layout')

@section('content')
<div class="container-fluid">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Incidenti operativi</h3>
                    <div class="card-tools text-muted">
                        <small>Stampe, cassa contanti e scontrini fiscali con fallimento — ultimi 30 giorni</small>
                    </div>
                </div>
                <div class="card-body">
                    @if ($topCodes->isNotEmpty())
                        <div class="row mb-4">
                            <div class="col-12">
                                <strong>Top errori negli ultimi 30 giorni:</strong>
                                @foreach ($topCodes as $tc)
                                    <span class="badge badge-secondary ml-1">{{ $tc->code }} ({{ $tc->total }})</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <form method="GET" action="{{ route('backoffice.operational-incidents.index') }}" class="mb-3">
                        <div class="row">
                            <div class="col-md-2">
                                <label>Categoria</label>
                                <select name="category" class="form-control">
                                    <option value="">Tutte</option>
                                    @foreach ($categories as $c)
                                        <option value="{{ $c }}" @selected(request('category')===$c)>{{ $c }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>Severity</label>
                                <select name="severity" class="form-control">
                                    <option value="">Tutte</option>
                                    @foreach ($severities as $s)
                                        <option value="{{ $s }}" @selected(request('severity')===$s)>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Codice</label>
                                <select name="code" class="form-control">
                                    <option value="">Tutti</option>
                                    @foreach ($availableCodes as $val => $label)
                                        <option value="{{ $val }}" @selected(request('code')===$val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>Stato</label>
                                <select name="state" class="form-control">
                                    <option value="">Tutti</option>
                                    <option value="unack" @selected(request('state')==='unack')>Non letti</option>
                                    <option value="unres" @selected(request('state')==='unres')>Non risolti</option>
                                    <option value="resolved" @selected(request('state')==='resolved')>Risolti</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label>Dal</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                            </div>
                            <div class="col-md-1">
                                <label>Al</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button class="btn btn-primary btn-sm">Filtra</button>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Quando</th>
                                    <th>Codice</th>
                                    <th>Severity</th>
                                    <th>Messaggio operatore</th>
                                    <th>Tavolo</th>
                                    <th>Operatore</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse ($incidents as $inc)
                                @php
                                    $sevClass = match ($inc->severity) {
                                        'critical' => 'badge-danger',
                                        'error'    => 'badge-warning',
                                        'warn'     => 'badge-info',
                                        default    => 'badge-secondary',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $inc->id }}</td>
                                    <td>{{ $inc->created_at?->format('d/m/Y H:i:s') }}</td>
                                    <td><code>{{ $inc->code }}</code></td>
                                    <td><span class="badge {{ $sevClass }}">{{ $inc->severityLabel() }}</span></td>
                                    <td>{{ $inc->operator_message }}</td>
                                    <td>
                                        @if ($inc->tableOrder?->restaurantTable?->is_banco)
                                            Banco
                                        @elseif ($inc->tableOrder?->restaurantTable?->table_number)
                                            Tavolo {{ $inc->tableOrder->restaurantTable->table_number }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $inc->user?->name ?? '—' }}</td>
                                    <td>
                                        @if ($inc->resolved_at)
                                            <span class="badge badge-success">Risolto</span>
                                        @elseif ($inc->acknowledged_at)
                                            <span class="badge badge-warning">Letto</span>
                                        @else
                                            <span class="badge badge-danger">Nuovo</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (!$inc->acknowledged_at)
                                            <form method="POST" action="{{ route('backoffice.operational-incidents.ack', $inc) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-xs btn-outline-secondary">Segna letto</button>
                                            </form>
                                        @endif
                                        @if (!$inc->resolved_at)
                                            <form method="POST" action="{{ route('backoffice.operational-incidents.resolve', $inc) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-xs btn-outline-success">Risolvi</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted">Nessun incidente registrato.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $incidents->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
