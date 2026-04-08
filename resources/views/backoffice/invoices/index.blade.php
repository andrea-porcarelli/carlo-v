@extends('backoffice.layout', ['title' => 'Fatture'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture'],
    ])
@endsection
@section('main-content')
    @if(isset($failedInvoices) && $failedInvoices->isNotEmpty())
    <div class="row" id="failed-invoices-alert">
        <div class="col-lg-12">
            <div class="alert alert-warning alert-dismissible">
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>

                <p>
                    <h4><i class="fa fa-exclamation-triangle"></i> Fatture non importate automaticamente ({{ $failedInvoices->count() }})</h4>
                    Le seguenti fatture ricevute da Mysond non sono state importate e richiedono attenzione manuale:
                </p>
                <table class="table table-condensed table-bordered" style="margin-bottom:0; background:rgba(255,255,255,0.5);">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Errore</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($failedInvoices as $fi)
                        <tr>
                            <td><small>{{ $fi->file_name }}</small></td>
                            <td><small class="text-danger">{{ \Illuminate\Support\Str::limit($fi->import_error, 100) }}</small></td>
                            <td class="text-center" style="white-space:nowrap;">
                                @if($fi->file_path)
                                <a href="{{ route('invoices.download-failed-file', $fi->id) }}" class="btn btn-xs btn-default" title="Scarica file originale">
                                    <i class="fa fa-download"></i>
                                </a>
                                @endif
                                <button class="btn btn-xs btn-warning btn-ignore-failed" data-id="{{ $fi->id }}" data-url="{{ route('invoices.ignore-failed', $fi->id) }}" title="Ignora">
                                    <i class="fa fa-eye-slash"></i> Ignora
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row g-1 advanced-search">
                                @include('backoffice.components.form.input', ['label' => 'Codice fattura', 'name' => 'invoice_number', 'col' => 2, 'class' => 'invoice_number'])
                                @include('backoffice.components.form.select', ['label' => 'Fornitore', 'name' => 'supplier_id', 'col' => 2, 'class' => 'supplier_id', 'options' => $suppliers, 'first_value_text' => 'Tutti i fornitori', 'hide_first' => true])
                                @include('backoffice.components.form.input', ['label' => 'Da data', 'name' => 'date_from', 'col' => 2, 'class' => 'date_from', 'type' => 'date'])
                                @include('backoffice.components.form.input', ['label' => 'A data', 'name' => 'date_to', 'col' => 2, 'class' => 'date_to', 'type' => 'date'])
                                @include('backoffice.components.form.select', [
                                    'label' => 'Mappatura',
                                    'name' => 'mapping',
                                    'col' => 2,
                                    'class' => 'mapping',
                                    'hide_first' => true,
                                    'first_value_text' => 'Tutte',
                                    'value' => 'da_effettuare',
                                    'options' => [
                                        ['id' => 'da_effettuare', 'label' => 'Da effettuare'],
                                        ['id' => 'effettuata',    'label' => 'Effettuata'],
                                    ],
                                ])
                                @include('backoffice.components.form.select', [
                                    'label' => 'Visualizza',
                                    'name' => 'ignored',
                                    'col' => 2,
                                    'class' => 'ignored',
                                    'hide_first' => true,
                                    'first_value_text' => 'Attive',
                                    'options' => [
                                        ['id' => 'ignorate', 'label' => 'Ignorate'],
                                    ],
                                ])
                                @include('backoffice.components.form.button', ['col' => 1, 'label' => 'Cerca', 'class' => 'btn-find'])
                                @include('backoffice.components.form.button', ['col' => 1, 'label' => 'Carica fattura', 'class' => 'btn-load-invoice', 'dataset' => ['path' => route('invoices.import')]])
                                @if($importLogsCount > 0)
                                <div class="col-lg-2">
                                    <label>&nbsp;</label>
                                    <button type="button" class="btn btn-info btn-block btn-show-import-logs">
                                        <i class="fa fa-list-alt"></i> Log import
                                        <span class="badge" style="background:#fff;color:#31708f;">{{ $importLogsCount }}</span>
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Fornitore</th>
                                        <th class="all">N* fattura </th>
                                        <th class="all">Importo </th>
                                        <th class="all">Data </th>
                                        <th class="all">Prodotti</th>
                                        <th class="all">Stato mappatura</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Fornitore</th>
                                        <th class="all">N* fattura </th>
                                        <th class="all">Importo </th>
                                        <th class="all">Data </th>
                                        <th class="all">Prodotti</th>
                                        <th class="all">Stato mappatura</th>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-modal title="Carica nuova fattura" class="import-invoice" />

    {{-- Modal Log Import Automatico --}}
    <div class="modal fade" id="importLogsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-list-alt"></i> Log import automatico</h4>
                </div>
                <div class="modal-body" id="importLogsBody">
                    <div class="text-center text-muted" id="importLogsSpinner">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('custom-css')
    <style>
        .mapping-summary { min-width: 200px; }
        .mapping-badges .label { display: inline-block; margin-bottom: 3px; font-size: 11px; }
        .mapping-badges .label-success { background-color: #1ab394; }

        .import-log-invoice { margin-bottom: 16px; border: 1px solid #e7eaec; border-radius: 4px; overflow: hidden; }
        .import-log-invoice .log-invoice-header { background: #f5f5f5; padding: 8px 12px; border-bottom: 1px solid #e7eaec; display: flex; justify-content: space-between; align-items: center; }
        .import-log-invoice .log-invoice-header strong { font-size: 14px; }
        .import-log-row { display: flex; align-items: baseline; gap: 8px; padding: 5px 12px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        .import-log-row:last-child { border-bottom: none; }
        .import-log-row .log-status { width: 26px; text-align: center; flex-shrink: 0; }
        .import-log-row .log-product { flex: 1; font-weight: 600; }
        .import-log-row .log-material { flex: 1; color: #555; }
        .import-log-row .log-qty { width: 120px; text-align: right; color: #333; white-space: nowrap; }
        .import-log-row .log-notes { flex: 2; color: #999; font-size: 11px; font-style: italic; }
        .import-log-row.status-auto_mapped    { background: #f0faf5; }
        .import-log-row.status-partial_mapping { background: #fffaf0; }
        .import-log-row.status-to_map          { background: #fffdf0; }
    </style>
@endsection
@section('custom-script')
    <script>
        $(document).on('click', '.btn-toggle-ignore-invoice', function() {
            var btn = $(this);
            var isIgnored = btn.hasClass('btn-warning');
            var msg = isIgnored
                ? 'Ripristinare questa fattura? Tornerà visibile nella lista normale.'
                : 'Ignorare questa fattura? Sarà nascosta dalla lista normale.';
            if (!confirm(msg)) return;
            $.ajax({
                url: btn.data('url'),
                type: 'PATCH',
                data: { _token: '{{ csrf_token() }}' },
                success: function() {
                    if (window.dataTable) window.dataTable.ajax.reload();
                    else window.location.reload();
                },
                error: function() { alert('Errore durante l\'operazione.'); }
            });
        });

        $(document).on('click', '.btn-ignore-failed', function() {
            var btn = $(this);
            var url = btn.data('url');
            if (!confirm('Ignorare questa fattura? Non sarà più visibile nella lista degli errori.')) return;
            $.ajax({
                url: url,
                type: 'PATCH',
                data: { _token: '{{ csrf_token() }}' },
                success: function() {
                    var row = btn.closest('tr');
                    row.fadeOut(300, function() {
                        row.remove();
                        var tbody = $('#failed-invoices-alert tbody');
                        if (tbody.find('tr').length === 0) {
                            $('#failed-invoices-alert').fadeOut(300);
                        }
                    });
                },
                error: function() {
                    alert('Errore durante l\'operazione.');
                }
            });
        });

        var statusIcon = {
            'auto_mapped':     '<i class="fa fa-check-circle text-success" title="Auto-mappato"></i>',
            'partial_mapping': '<i class="fa fa-exclamation-circle text-warning" title="Mappatura parziale"></i>',
            'to_map':          '<i class="fa fa-question-circle text-danger" title="Da mappare"></i>',
            'ignored':         '<i class="fa fa-minus-circle text-muted" title="Ignorato"></i>',
        };

        $(document).on('click', '.btn-show-import-logs', function() {
            var $body = $('#importLogsBody');
            $body.html('<div class="text-center text-muted" style="padding:30px;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
            $('#importLogsModal').modal('show');

            $.ajax({
                url: '{{ route("invoices.import-logs") }}',
                type: 'GET',
                success: function(data) {
                    if (!data.length) {
                        $body.html('<p class="text-muted text-center">Nessun log disponibile.</p>');
                        return;
                    }

                    var html = '';
                    data.forEach(function(invoice) {
                        html += '<div class="import-log-invoice">';
                        html += '<div class="log-invoice-header">';
                        html += '<strong><i class="fa fa-file-text-o"></i> ' + invoice.supplier_name + ' — Fattura ' + invoice.invoice_number + '</strong>';
                        html += '<span class="text-muted" style="font-size:12px;">' + (invoice.invoice_date || '') + '</span>';
                        html += '<span>';
                        if (invoice.auto_mapped)  html += ' <span class="label label-success">' + invoice.auto_mapped + ' auto-mappati</span>';
                        if (invoice.partial)      html += ' <span class="label label-warning">' + invoice.partial + ' parziali</span>';
                        if (invoice.to_map)       html += ' <span class="label label-danger">'  + invoice.to_map   + ' da mappare</span>';
                        html += '</span>';
                        html += '</div>';

                        invoice.rows.forEach(function(row) {
                            html += '<div class="import-log-row status-' + row.status + '">';
                            html += '<span class="log-status">' + (statusIcon[row.status] || '') + '</span>';
                            html += '<span class="log-product">' + row.product_name + '</span>';
                            html += '<span class="log-material">' + (row.material_name || '<em class="text-muted">—</em>') + '</span>';

                            if (row.status === 'auto_mapped') {
                                html += '<span class="log-qty"><strong>+' + parseFloat(row.stock_added).toLocaleString('it-IT', {minimumFractionDigits: 3}) + '</strong> ' + (row.stock_unit || '') + '</span>';
                            } else {
                                html += '<span class="log-qty text-muted">qty: ' + (row.qty_invoice || '—') + '</span>';
                            }

                            if (row.notes) {
                                html += '<span class="log-notes">' + row.notes + '</span>';
                            }
                            html += '</div>';
                        });

                        html += '</div>';
                    });

                    $body.html(html);
                },
                error: function() {
                    $body.html('<p class="text-danger text-center">Errore durante il caricamento dei log.</p>');
                }
            });
        });

        $(document).ready(function(){
            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('invoices.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'id', width: '40px'},
                        {data: 'supplier_name'},
                        {data: 'invoice_number'},
                        {data: 'amount'},
                        {data: 'invoice_date'},
                        {data: 'products', class: 'text-center'},
                        {data: 'mapping', class: 'text-center'},
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['invoice_number', 'supplier_id', 'date_from', 'date_to', 'mapping', 'ignored'],
                    serverSide: false,
                }]);
            }, 500);
        })
    </script>
@endsection
