@extends('backoffice.layout')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-print me-2"></i>
                        Log Stampe - Tavolo #{{ $tableOrder->restaurantTable->table_number }}
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('backoffice.logs.table-order', $tableOrder->id) }}" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> Torna al dettaglio ordine
                        </a>
                    </div>
                </div>
                <div class="card-body">
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
                                <span class="info-box-icon bg-success"><i class="fas fa-print"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Totale Stampe</span>
                                    <span class="info-box-number">{{ $printLogs->count() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Stampe Fallite</span>
                                    <span class="info-box-number">{{ $printLogs->where('success', false)->count() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-euro-sign"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Totale Ordine</span>
                                    <span class="info-box-number">{{ number_format($tableOrder->total_amount, 2) }}€</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabella log stampe -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Data/Ora</th>
                                    <th>Tipo</th>
                                    <th>Operazione</th>
                                    <th>Stampante</th>
                                    <th>Operatore</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($printLogs as $log)
                                    <tr>
                                        <td>
                                            <small>{{ $log->created_at->format('d/m/Y H:i:s') }}</small>
                                        </td>
                                        <td>
                                            @php
                                                $typeColors = [
                                                    'order' => 'primary',
                                                    'marcia' => 'success',
                                                    'preconto' => 'info',
                                                ];
                                                $typeIcons = [
                                                    'order' => 'fa-utensils',
                                                    'marcia' => 'fa-play-circle',
                                                    'preconto' => 'fa-receipt',
                                                ];
                                            @endphp
                                            <span class="badge badge-{{ $typeColors[$log->print_type] ?? 'secondary' }}">
                                                <i class="fas {{ $typeIcons[$log->print_type] ?? 'fa-print' }} me-1"></i>
                                                {{ $log->getPrintTypeLabel() }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($log->operation)
                                                @php
                                                    $opColors = [
                                                        'add' => 'success',
                                                        'update' => 'info',
                                                        'remove' => 'danger',
                                                    ];
                                                @endphp
                                                <span class="badge badge-{{ $opColors[$log->operation] ?? 'secondary' }}">
                                                    {{ $log->getOperationLabel() }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($log->printer)
                                                <strong>{{ $log->printer->label }}</strong>
                                                <br><small class="text-muted">{{ $log->printer->ip }}</small>
                                            @else
                                                <span class="text-muted">N/D</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $log->user->name ?? 'N/D' }}
                                        </td>
                                        <td>
                                            @if($log->success)
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> OK
                                                </span>
                                            @else
                                                <span class="badge badge-danger" title="{{ $log->error_message }}">
                                                    <i class="fas fa-times"></i> Errore
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="{{ route('backoffice.logs.print-preview', $log->id) }}"
                                                   class="btn btn-sm btn-info"
                                                   target="_blank"
                                                   title="Anteprima">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                @if($log->printer)
                                                    <button type="button"
                                                            class="btn btn-sm btn-warning btn-reprint"
                                                            data-id="{{ $log->id }}"
                                                            title="Ristampa">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">Nessun log di stampa trovato</p>
                                        </td>
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

@push('scripts')
<script>
$(document).ready(function() {
    $('.btn-reprint').click(function() {
        const btn = $(this);
        const id = btn.data('id');

        if (!confirm('Vuoi ristampare questo documento?')) {
            return;
        }

        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: `/backoffice/logs/print/${id}/reprint`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Errore durante la ristampa';
                toastr.error(msg);
            },
            complete: function() {
                btn.prop('disabled', false);
                btn.html('<i class="fas fa-redo"></i>');
            }
        });
    });
});
</script>
@endpush
@endsection
