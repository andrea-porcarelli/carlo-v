@extends('backoffice.layout', ['title' => 'Contabilità'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Contabilità'],
        'level_2' => ['label' => 'Fatture'],
    ])
@endsection
@section('main-content')
    @if (!empty($pendingAcks) && $pendingAcks->isNotEmpty())
        <div class="alert alert-danger">
            <strong>⛔️ Emissione fatture bloccata.</strong>
            {{ $pendingAcks->count() }} scartata/e SDI su MySond da riconoscere prima di poter emettere nuove fatture.
            Vedi sezione "Scartate SDI da riconoscere" sotto.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row advanced-search">
                        @include('backoffice.components.form.input', [
                            'label' => 'Codice fattura',
                            'name'  => 'invoice_code',
                            'col'   => 3,
                            'class' => 'invoice_code',
                        ])
                        <div class="col-xs-12 col-sm-3 m-t-sm">
                            <label>Cliente</label>
                            <select name="customer_id" class="form-control customer_id">
                                <option value="">Tutti</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xs-12 col-sm-3 m-t-sm">
                            <label>Stato</label>
                            <select name="status" class="form-control status">
                                <option value="">Tutti</option>
                                <option value="pending">In coda</option>
                                <option value="sent">Inviata</option>
                                <option value="error">Errore</option>
                            </select>
                        </div>
                        <div class="col-xs-12 col-sm-3 m-t-sm" style="display:flex; align-items:flex-end;">
                            <button type="button" class="btn btn-info btn-find">Cerca</button>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-lg-12">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort">Azioni</th>
                                        <th class="all no-sort">Codice</th>
                                        <th class="all no-sort">Cliente</th>
                                        <th class="all no-sort">Importo</th>
                                        <th class="all no-sort">Stato</th>
                                        <th class="all no-sort">Esito SDI</th>
                                        <th class="all">Creata</th>
                                        <th class="all no-sort">Inviata</th>
                                        <th class="all no-sort">Risposta MySond</th>
                                    </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (!empty($pendingAcks) && $pendingAcks->isNotEmpty())
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-danger">
                    <div class="panel-heading"><strong>⚠️ Scartate SDI da riconoscere ({{ $pendingAcks->count() }})</strong></div>
                    <div class="panel-body">
                        <p>MySond ha rifiutato queste fatture. L'emissione di nuove fatture è bloccata finché non vengono riconosciute con nota di come sono state gestite.</p>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Numero</th>
                                    <th>Cliente</th>
                                    <th>Stato</th>
                                    <th>Scoperta</th>
                                    <th style="min-width:380px;">Riconosci</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($pendingAcks as $r)
                                    <tr>
                                        <td><code>{{ $r->file_name }}</code></td>
                                        <td><code>{{ $r->mysond_code ?? '—' }}</code></td>
                                        <td>{{ $r->customer_name ?? '—' }}</td>
                                        <td><span class="label label-danger">{{ $r->stato_label ?? ('Stato '.$r->stato) }}</span></td>
                                        <td>{{ $r->first_synced_at?->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('accounting.mirrored.ack', $r) }}" class="form-inline"
                                                  onsubmit="const n=this.querySelector('input[name=note]'); if(!n.value || n.value.length<5){alert('Inserisci una nota di almeno 5 caratteri.'); n.focus(); return false;}">
                                            @csrf
                                            <input type="text" name="note" class="form-control input-sm" placeholder="Nota (min 5 char)" required minlength="5" style="width:240px;">
                                            <button type="submit" class="btn btn-success btn-sm">Riconosci</button>
                                            </form>
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

    @if (!empty($externalInvoices) && $externalInvoices->isNotEmpty())
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Fatture da MySond (esterne)</strong></div>
                    <div class="panel-body">
                        <p class="text-muted">Fatture visibili sull'Azienda MySond ma non emesse da Carlo V (emesse da altri software o manualmente dal portale). Sola lettura.</p>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                <tr>
                                    <th>Numero</th>
                                    <th>Data</th>
                                    <th>Cliente</th>
                                    <th>Importo</th>
                                    <th>SDI</th>
                                    <th>Azione</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($externalInvoices as $m)
                                    <tr>
                                        <td>
                                            <code>{{ $m->mysond_code ?? '—' }}</code>
                                            <small class="text-muted d-block">{{ $m->file_name }}</small>
                                        </td>
                                        <td>{{ $m->mysond_date?->format('d/m/Y') ?? '—' }}</td>
                                        <td>
                                            {{ $m->customer_name ?? '—' }}
                                            @if ($m->customer_vat) <small class="text-muted d-block">P.IVA {{ $m->customer_vat }}</small>
                                            @elseif ($m->customer_cf) <small class="text-muted d-block">CF {{ $m->customer_cf }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $m->mysond_total !== null ? number_format((float) $m->mysond_total, 2, ',', '.').' €' : '—' }}</td>
                                        <td>
                                            @if ($m->stato !== null)
                                                @php
                                                    $cls = match(true) {
                                                        in_array($m->stato, [7, 9]) => 'label-success',
                                                        in_array($m->stato, [1, 6, 10]) => 'label-danger',
                                                        in_array($m->stato, [8, 11, 12]) => 'label-warning',
                                                        default => 'label-default',
                                                    };
                                                @endphp
                                                <span class="label {{ $cls }}">{{ $m->stato_label ?? ('Stato '.$m->stato) }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (!empty($m->xml_content))
                                                <a href="{{ route('accounting.mirrored.xml', $m) }}" target="_blank" class="btn btn-xs btn-info">XML</a>
                                            @else
                                                <span class="text-muted" title="Download XML lazy non ancora implementato">XML —</span>
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

    <div class="modal fade" id="mysond-logs-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document" style="max-width: 1100px;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Log invio MySond — <span class="mysond-logs-invoice"></span></h4>
                </div>
                <div class="modal-body">
                    <div class="mysond-logs-loading text-center" style="padding:30px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                    </div>
                    <div class="mysond-logs-empty" style="display:none; padding:20px; text-align:center;">
                        Nessun tentativo registrato per questa fattura.
                    </div>
                    <div class="mysond-logs-content"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .mysond-log-entry { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 12px; }
        .mysond-log-entry .log-header { padding: 8px 12px; background: #f6f6f6; border-bottom: 1px solid #ddd; cursor: pointer; }
        .mysond-log-entry .log-header .label { margin-right: 8px; }
        .mysond-log-entry .log-body { padding: 12px; display: none; }
        .mysond-log-entry.open .log-body { display: block; }
        .mysond-log-entry pre { background: #f5f5f5; border: 1px solid #e3e3e3; padding: 8px; font-size: 11px; max-height: 320px; overflow: auto; white-space: pre-wrap; word-break: break-all; }
        .mysond-log-entry .log-section-title { font-weight: bold; margin-top: 10px; margin-bottom: 4px; font-size: 12px; color: #444; }
    </style>
@endsection
@section('custom-script')
    <script>
        $(document).ready(function(){
            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('accounting.invoices.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '110px'},
                        {data: 'code', orderable: false},
                        {data: 'customer_name', orderable: false},
                        {data: 'amount_fmt', class: 'text-end', orderable: false},
                        {data: 'status_badge', class: 'text-center', orderable: false},
                        {data: 'sdi_status_label_fmt', class: 'text-center', orderable: false},
                        {data: 'created_at'},
                        {data: 'sent_at', orderable: false},
                        {data: 'mysond_desc', orderable: false},
                    ],
                    order: [[6, 'desc']],
                    dataForm: ['invoice_code', 'customer_id', 'status'],
                    serverSide: true,
                }]);
            }, 300);

            $(document).on('click', '.btn-show-mysond-logs', function () {
                const id = $(this).data('id');
                const $modal = $('#mysond-logs-modal');
                $modal.find('.mysond-logs-invoice').text('#' + id);
                $modal.find('.mysond-logs-content').empty();
                $modal.find('.mysond-logs-empty').hide();
                $modal.find('.mysond-logs-loading').show();
                $modal.modal('show');

                $.ajax({
                    url: '/backoffice/accounting/invoices/' + id + '/logs',
                    method: 'GET',
                    success: function (data) {
                        $modal.find('.mysond-logs-loading').hide();
                        const code = (data.invoice && (data.invoice.invoice_code || data.invoice.invoice_name)) || ('#' + id);
                        $modal.find('.mysond-logs-invoice').text(code);

                        if (!data.logs || data.logs.length === 0) {
                            $modal.find('.mysond-logs-empty').show();
                            return;
                        }

                        const $container = $modal.find('.mysond-logs-content');
                        data.logs.forEach(function (log) {
                            const outcomeClass = log.outcome === 'success' ? 'label-success'
                                : (log.outcome === 'exception' ? 'label-danger' : 'label-warning');
                            const codiceTxt = log.codice ? ' cod. ' + escapeHtml(log.codice) : '';
                            const esitoTxt  = (log.esito !== null && log.esito !== undefined) ? ' esito ' + log.esito : '';
                            const duration  = log.duration_ms ? ' · ' + log.duration_ms + ' ms' : '';

                            let body = '';
                            if (log.descrizione) {
                                body += '<div class="log-section-title">Descrizione</div>'
                                    +  '<div>' + escapeHtml(log.descrizione) + '</div>';
                            }
                            if (log.exception_message) {
                                body += '<div class="log-section-title">Eccezione (' + escapeHtml(log.exception_class || '') + ')</div>'
                                    +  '<pre>' + escapeHtml(log.exception_message) + '</pre>';
                            }
                            if (log.exception_trace) {
                                body += '<div class="log-section-title">Stack trace</div>'
                                    +  '<pre>' + escapeHtml(log.exception_trace) + '</pre>';
                            }
                            if (log.request_xml) {
                                body += '<div class="log-section-title">XML inviato (SOAP request)</div>'
                                    +  '<pre>' + escapeHtml(log.request_xml) + '</pre>';
                            }
                            if (log.response_xml) {
                                body += '<div class="log-section-title">Risposta server (SOAP response)</div>'
                                    +  '<pre>' + escapeHtml(log.response_xml) + '</pre>';
                            }
                            if (!body) {
                                body = '<em>Nessun dettaglio aggiuntivo registrato.</em>';
                            }

                            $container.append(
                                '<div class="mysond-log-entry">'
                                +   '<div class="log-header">'
                                +     '<span class="label ' + outcomeClass + '">' + escapeHtml(log.outcome) + '</span>'
                                +     '<strong>' + escapeHtml(log.operation) + '</strong>'
                                +     '<span class="text-muted"> ' + escapeHtml(log.created_at || '') + esitoTxt + codiceTxt + duration + '</span>'
                                +     '<i class="fa fa-chevron-down pull-right" style="margin-top:3px;"></i>'
                                +   '</div>'
                                +   '<div class="log-body">' + body + '</div>'
                                + '</div>'
                            );
                        });

                        $container.find('.mysond-log-entry').first().addClass('open');
                    },
                    error: function (xhr) {
                        $modal.find('.mysond-logs-loading').hide();
                        $modal.find('.mysond-logs-content').html(
                            '<div class="alert alert-danger">Errore caricamento log: ' + (xhr.status || 'sconosciuto') + '</div>'
                        );
                    }
                });
            });

            $(document).on('click', '.mysond-log-entry .log-header', function () {
                $(this).closest('.mysond-log-entry').toggleClass('open');
            });

            function escapeHtml(str) {
                if (str === null || str === undefined) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            $(document).on('click', '.btn-resend-invoice', function () {
                const id = $(this).data('id');
                if (!confirm('Re-inviare la fattura #' + id + ' a MySond?')) return;
                $.ajax({
                    url: '/backoffice/accounting/invoices/' + id + '/resend',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function () {
                        toastr.success('Fattura re-accodata per invio.', 'Operazione effettuata');
                        $('.datatable_table').DataTable().ajax.reload(null, false);
                    },
                    error: function (xhr) {
                        const r = xhr.responseJSON || {};
                        toastr.error(r.message || 'Errore', 'Errore');
                    }
                });
            });

            $(document).on('click', '.btn-refresh-sdi', function () {
                const id  = $(this).data('id');
                const $btn = $(this);
                const $icon = $btn.find('i');
                $btn.prop('disabled', true);
                $icon.addClass('fa-spin');
                $.ajax({
                    url: '/backoffice/accounting/invoices/' + id + '/refresh-sdi',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function (data) {
                        toastr.success(data.message || 'Esito SDI aggiornato.', 'Operazione effettuata');
                        $('.datatable_table').DataTable().ajax.reload(null, false);
                    },
                    error: function (xhr) {
                        const r = xhr.responseJSON || {};
                        toastr.error(r.message || 'Errore', 'Errore');
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                        $icon.removeClass('fa-spin');
                    }
                });
            });
        });
    </script>
@endsection
