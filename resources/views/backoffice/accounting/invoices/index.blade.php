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

    <div class="row" style="margin-bottom:12px;">
        <div class="col-lg-12">
            <a href="{{ route('accounting.invoices.create') }}" class="btn btn-primary">
                <i class="fa fa-plus"></i> Nuova fattura
            </a>
            <button type="button" class="btn btn-warning" id="btn-open-credit-note">
                <i class="fa fa-file-invoice"></i> Nuova nota di credito
            </button>
        </div>
    </div>

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
                                                <a href="{{ route('accounting.mirrored.xml', $m) }}" target="_blank" class="btn btn-xs btn-info" title="Apri XML"><i class="fa fa-file-code"></i> XML</a>
                                            @else
                                                <span class="text-muted" title="Download XML lazy non ancora implementato">XML —</span>
                                            @endif
                                            <a href="{{ route('accounting.credit-notes.create', ['source' => 'mirrored', 'id' => $m->id]) }}"
                                               class="btn btn-xs btn-warning"
                                               title="Emetti nota di credito da questa fattura esterna">
                                                <i class="fa fa-file-invoice"></i> Nota di credito
                                            </a>
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

    <div class="modal fade" id="credit-note-source-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document" style="max-width: 900px;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-file-invoice"></i> Nuova nota di credito — seleziona fattura</h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        Scegli la fattura da stornare fra quelle interne (emesse da Carlo V) o esterne (viste su MySond ed emesse altrove).
                        Oppure emetti una nota credito <em>senza riferimento</em> (SDI la accetta ma senza <code>DatiFattureCollegate</code>).
                    </p>

                    <div class="form-group">
                        <input type="text" class="form-control" id="credit-note-source-search" placeholder="Cerca per numero fattura o cliente...">
                    </div>

                    <div id="credit-note-source-loading" class="text-center" style="padding:30px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                    </div>
                    <div id="credit-note-source-content" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('accounting.credit-notes.create', ['source' => 'blank']) }}" class="btn btn-default pull-left">
                        <i class="fa fa-file"></i> Senza fattura di riferimento
                    </a>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                </div>
            </div>
        </div>
    </div>

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

    <div class="modal fade" id="mysond-inspect-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document" style="max-width: 1000px;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Ispezione MySond — <span class="mysond-inspect-invoice"></span></h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted mysond-inspect-intro" style="margin-bottom:12px;">
                        Elenco di tutti i record trovati su MySond per il fileName della fattura.
                        Se un tentativo precedente è stato consegnato, puoi adottarne l'esito per sbloccare la fattura locale.
                    </p>
                    <div><small class="text-muted">FileName: <code class="mysond-inspect-filename"></code></small></div>
                    <div class="mysond-inspect-loading text-center" style="padding:30px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                    </div>
                    <div class="mysond-inspect-empty" style="display:none; padding:20px; text-align:center;">
                        Nessun record trovato su MySond per questo fileName.
                    </div>
                    <div class="mysond-inspect-content" style="margin-top:12px;"></div>
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

            $(document).on('click', '.btn-inspect-mysond', function () {
                const id = $(this).data('id');
                const $modal = $('#mysond-inspect-modal');
                $modal.data('invoice-id', id);
                $modal.find('.mysond-inspect-invoice').text('#' + id);
                $modal.find('.mysond-inspect-filename').text('');
                $modal.find('.mysond-inspect-content').empty();
                $modal.find('.mysond-inspect-empty').hide();
                $modal.find('.mysond-inspect-loading').show();
                $modal.modal('show');

                $.ajax({
                    url: '/backoffice/accounting/invoices/' + id + '/mysond-inspect',
                    method: 'GET',
                    success: function (data) {
                        $modal.find('.mysond-inspect-loading').hide();
                        const code = (data.invoice && (data.invoice.invoice_code || data.invoice.invoice_name)) || ('#' + id);
                        $modal.find('.mysond-inspect-invoice').text(code);
                        $modal.find('.mysond-inspect-filename').text(data.file_name || '');

                        if (!data.records || data.records.length === 0) {
                            $modal.find('.mysond-inspect-empty').show();
                            return;
                        }

                        let html = '<table class="table table-striped table-bordered" style="margin-top:8px;">'
                                 + '<thead><tr>'
                                 + '<th>#</th><th>Numero</th><th>Data</th><th>Importo</th>'
                                 + '<th>Stato SDI</th><th>Descrizione</th><th class="text-center">Azione</th>'
                                 + '</tr></thead><tbody>';

                        data.records.forEach(function (r, idx) {
                            const stato = r.stato !== null && r.stato !== undefined ? r.stato : '—';
                            const cls = r.is_success ? 'label-success' : (r.is_rejected ? 'label-danger' : 'label-default');
                            const label = escapeHtml(r.stato_label || ('Stato ' + stato));
                            const dateTxt = r.date ? escapeHtml(String(r.date)) : '—';
                            const totalTxt = (r.total !== null && r.total !== undefined)
                                ? Number(r.total).toLocaleString('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €'
                                : '—';
                            const descTxt = r.descrizione ? '<small class="text-muted">' + escapeHtml(r.descrizione) + '</small>' : '—';
                            let actionCell = '—';
                            if (r.is_success) {
                                const payload = {
                                    stato: r.stato,
                                    codice: r.code || '',
                                    descrizione: r.descrizione || '',
                                };
                                actionCell = '<button class="btn btn-xs btn-success btn-adopt-mysond" data-payload=\''
                                    + escapeAttr(JSON.stringify(payload))
                                    + '\'><i class="fa fa-check"></i> Adotta esito</button>';
                            }

                            html += '<tr>'
                                 + '<td>' + (idx + 1) + '</td>'
                                 + '<td><code>' + escapeHtml(r.code || '—') + '</code></td>'
                                 + '<td>' + dateTxt + '</td>'
                                 + '<td class="text-end">' + totalTxt + '</td>'
                                 + '<td><span class="label ' + cls + '">' + label + '</span></td>'
                                 + '<td>' + descTxt + '</td>'
                                 + '<td class="text-center">' + actionCell + '</td>'
                                 + '</tr>';
                        });

                        html += '</tbody></table>';

                        if (data.has_success) {
                            html += '<div class="alert alert-info" style="margin-top:8px;">'
                                 +  '<i class="fa fa-info-circle"></i> È presente almeno un record consegnato/accettato: '
                                 +  'puoi adottarne l\'esito per riallineare lo stato della fattura locale.'
                                 +  '</div>';
                        } else {
                            html += '<div class="alert alert-warning" style="margin-top:8px;">'
                                 +  '<i class="fa fa-exclamation-triangle"></i> Nessun record con esito Consegnata/Accettata: '
                                 +  'nulla da adottare.'
                                 +  '</div>';
                        }

                        $modal.find('.mysond-inspect-content').html(html);
                    },
                    error: function (xhr) {
                        $modal.find('.mysond-inspect-loading').hide();
                        const r = (xhr.responseJSON || {});
                        $modal.find('.mysond-inspect-content').html(
                            '<div class="alert alert-danger">' + escapeHtml(r.message || ('Errore ' + xhr.status)) + '</div>'
                        );
                    }
                });
            });

            $(document).on('click', '.btn-adopt-mysond', function () {
                const $btn = $(this);
                const payload = JSON.parse($btn.attr('data-payload') || '{}');
                const invoiceId = $('#mysond-inspect-modal').data('invoice-id');
                if (!confirm('Adottare come esito ufficiale la notifica selezionata? Lo stato della fattura verrà impostato a "Inviata" con esito SDI ' + (payload.stato || '') + '.')) {
                    return;
                }
                $btn.prop('disabled', true).find('i').removeClass('fa-check').addClass('fa-spinner fa-spin');
                $.ajax({
                    url: '/backoffice/accounting/invoices/' + invoiceId + '/mysond-adopt',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    data: payload,
                    success: function (data) {
                        toastr.success(data.message || 'Esito adottato.', 'Operazione effettuata');
                        $('#mysond-inspect-modal').modal('hide');
                        $('.datatable_table').DataTable().ajax.reload(null, false);
                    },
                    error: function (xhr) {
                        const r = xhr.responseJSON || {};
                        toastr.error(r.message || ('Errore ' + xhr.status), 'Errore');
                        $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-check');
                    }
                });
            });

            function escapeAttr(str) {
                return escapeHtml(str).replace(/'/g, '&#039;');
            }

            // ── Modal selezione fattura per emissione Nota di Credito ─────────
            const creditNoteBaseUrl = '{{ route('accounting.credit-notes.create') }}';
            let creditNoteSearchTimer = null;

            $('#btn-open-credit-note').on('click', function () {
                $('#credit-note-source-search').val('');
                $('#credit-note-source-content').empty().hide();
                $('#credit-note-source-loading').show();
                $('#credit-note-source-modal').modal('show');
                loadCreditNoteSources('');
            });

            $('#credit-note-source-search').on('input', function () {
                const q = $(this).val();
                clearTimeout(creditNoteSearchTimer);
                creditNoteSearchTimer = setTimeout(() => loadCreditNoteSources(q), 250);
            });

            function loadCreditNoteSources(q) {
                $('#credit-note-source-loading').show();
                $('#credit-note-source-content').hide();
                $.ajax({
                    url: '{{ route('accounting.invoices.source-suggestions') }}',
                    method: 'GET',
                    data: { q: q },
                    success: function (data) {
                        renderCreditNoteSources(data);
                    },
                    error: function (xhr) {
                        $('#credit-note-source-loading').hide();
                        $('#credit-note-source-content').show().html(
                            '<div class="alert alert-danger">Errore caricamento: ' + (xhr.status || 'sconosciuto') + '</div>'
                        );
                    }
                });
            }

            function renderCreditNoteSources(data) {
                const internal = data.internal || [];
                const external = data.external || [];

                let html = '';

                function renderGroup(title, list, sourceKey, emptyMsg) {
                    html += '<h5 style="margin-top:16px;">' + escapeHtml(title) + ' <small class="text-muted">(' + list.length + ')</small></h5>';
                    if (list.length === 0) {
                        html += '<div class="text-muted" style="padding:8px 0;">' + escapeHtml(emptyMsg) + '</div>';
                        return;
                    }
                    html += '<table class="table table-striped table-bordered table-hover" style="margin-bottom:0;">'
                          + '<thead><tr><th>Numero</th><th>Data</th><th>Cliente</th><th class="text-end">Importo</th><th></th></tr></thead><tbody>';
                    list.forEach(function (row) {
                        const total = (row.total !== null && row.total !== undefined)
                            ? Number(row.total).toLocaleString('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €'
                            : '—';
                        const url = creditNoteBaseUrl + '?source=' + encodeURIComponent(sourceKey) + '&id=' + encodeURIComponent(row.id);
                        html += '<tr>'
                             + '<td><strong>' + escapeHtml(row.code || '—') + '</strong></td>'
                             + '<td>' + escapeHtml(row.date_display || '—') + '</td>'
                             + '<td>' + escapeHtml(row.customer_name || '—') + '</td>'
                             + '<td class="text-end">' + total + '</td>'
                             + '<td class="text-end"><a href="' + url + '" class="btn btn-xs btn-warning">Storna <i class="fa fa-arrow-right"></i></a></td>'
                             + '</tr>';
                    });
                    html += '</tbody></table>';
                }

                renderGroup('Fatture interne (Carlo V)', internal, 'invoice', 'Nessuna fattura interna trovata.');
                renderGroup('Fatture esterne (MySond)', external, 'mirrored', 'Nessuna fattura esterna trovata.');

                $('#credit-note-source-loading').hide();
                $('#credit-note-source-content').show().html(html);
            }

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
