@extends('backoffice.layout', ['title' => 'Ditron GETP'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Scontrini Ditron', 'url' => route('backoffice.ditron.receipts.index')],
        'level_2' => ['label' => 'Lettura cassa (GETP)'],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>Lettura diretta dalla cassa Ditron RT</strong> — comando <code>GETP</code> (opcode 49).
                </div>
                <div class="panel-body">
                    <p class="text-muted">
                        Interroga in tempo reale la cassa fiscale. Utile prima di riemettere uno scontrino incerto
                        (per capire se è già stato stampato) o per recuperare i dati fiscali (matricola, N° scontrino,
                        Z) di uno scontrino andato in <code>failed</code>.
                    </p>

                    <form method="GET" action="{{ route('backoffice.ditron.receipts.getp') }}">
                        <div class="row g-1 align-items-end">
                            <div class="col-md-6">
                                <label>Cosa vuoi leggere</label>
                                <select name="preset" class="form-control">
                                    @foreach($presets as $key => $p)
                                        <option value="{{ $key }}" {{ $preset === $key ? 'selected' : '' }}>
                                            {{ $p['label'] }}
                                            (proprietà: {{ implode(', ', $p['properties']) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-eye"></i> Leggi cassa
                                </button>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ route('backoffice.ditron.receipts.index') }}" class="btn btn-default btn-block">
                                    ← Torna a scontrini
                                </a>
                            </div>
                        </div>
                    </form>

                    <hr>

                    <h4>Risultato</h4>
                    @if(is_array($result))
                        @if(($result['ok'] ?? false))
                            <div class="alert alert-success">
                                Lettura completata in {{ $result['elapsed_ms'] ?? '?' }}ms.
                            </div>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th style="width:80px">#</th>
                                        <th>Etichetta</th>
                                        <th>Valore grezzo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($properties as $p)
                                        <tr>
                                            <td><strong>{{ $p }}</strong></td>
                                            <td>{{ $labels[$p] ?? '—' }}</td>
                                            <td>
                                                @if(isset($result['values'][$p]))
                                                    <code>{{ $result['values'][$p] }}</code>
                                                @else
                                                    <span class="text-muted">non presente nella risposta</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            @php
                                $rawError = trim((string) ($result['error'] ?? ''));
                                // WinEcrCom concatena e ripete ogni riga di errore due volte nel .err.
                                // Splittiamo prima di ogni "ERRORE N/M in line ..." e deduplichiamo,
                                // così il messaggio diventa una lista leggibile per l'operatore.
                                $errorLines = [];
                                if ($rawError !== '') {
                                    $parts = preg_split('/(?=ERRORE\s+\d+\/\d+\s+in\s+line)/i', $rawError, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                                    $errorLines = array_values(array_unique(array_map('trim', $parts)));
                                    if (empty($errorLines)) {
                                        $errorLines = [$rawError];
                                    }
                                }
                            @endphp
                            <div class="alert alert-danger">
                                <div>
                                    <strong>Lettura fallita.</strong>
                                    @if(!empty($result['elapsed_ms']))
                                        <small class="text-muted ml-2">Elapsed: {{ $result['elapsed_ms'] }}ms</small>
                                    @endif
                                </div>
                                @if(count($errorLines))
                                    <hr class="my-2">
                                    <div class="small mb-1"><strong>Dettagli dal driver WinEcrCom:</strong></div>
                                    <ul class="mb-0" style="font-family:monospace; font-size:12px; padding-left:20px;">
                                        @foreach($errorLines as $line)
                                            <li>{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <p class="text-muted">
                                Se l'errore è "Nessun valore riconosciuto nella risposta" o timeout, probabilmente
                                WinEcrCom non produce un file di risposta col formato atteso da
                                <code>AutoRunPropertyReader</code>. In quel caso occorre modificare il parser
                                sull'agent dopo aver esaminato il contenuto reale del file.
                            </p>
                        @endif

                        @if(!empty($result['raw_command']))
                            <details class="mt-3">
                                <summary>Comando inviato</summary>
                                <pre style="background:#1e1e1e;color:#d4d4d4;padding:10px;border-radius:4px;font-size:12px;">{{ $result['raw_command'] }}</pre>
                            </details>
                        @endif
                        @if(!empty($result['raw_err']))
                            <details class="mt-3">
                                <summary>Contenuto file .err della cassa</summary>
                                <pre style="background:#1e1e1e;color:#d4d4d4;padding:10px;border-radius:4px;font-size:12px;">{{ $result['raw_err'] }}</pre>
                            </details>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
