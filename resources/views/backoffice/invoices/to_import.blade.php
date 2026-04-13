@extends('backoffice.layout', ['title' => 'Fatture da importare'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture da importare'],
    ])
@endsection
@section('main-content')
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
                                <input type="hidden" name="mapping" class="mapping" value="da_effettuare">
                                <input type="hidden" name="import" class="import" value="da_effettuare">
                                <input type="hidden" name="ignored" class="ignored" value="ignorate">
                                @include('backoffice.components.form.button', ['col' => 1, 'label' => 'Cerca', 'class' => 'btn-find'])
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">Fornitore</th>
                                        <th class="all">N* fattura </th>
                                        <th class="all">Importo </th>
                                        <th class="all">Data </th>
                                        <th class="all">Prodotti</th>
                                        <th class="all">Da importare</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">Fornitore</th>
                                        <th class="all">N* fattura </th>
                                        <th class="all">Importo </th>
                                        <th class="all">Data </th>
                                        <th class="all">Prodotti</th>
                                        <th class="all">Da importare</th>
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
    <div class="modal fade" id="importStockModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fas fa-seedling"></i> Importa giacenze — <span id="importStockInvoiceLabel"></span></h4>
                </div>
                <div class="modal-body" id="importStockBody">
                    <div class="text-center text-muted" style="padding:30px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-success" id="btnConfirmImportStock">
                        <i class="fas fa-seedling"></i> Conferma importazione
                    </button>
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

        .actions { display: flex; flex-direction: column; gap: 2px; align-items: center; }
    </style>
@endsection
@section('custom-script')
    <script>
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

        var importStockInvoiceId = null;

        function renderImportStockTable(data) {
            $('#importStockInvoiceLabel').text(data.invoice.supplier + ' — Fattura ' + data.invoice.number);

            var html = '<table class="table table-condensed table-bordered import-stock-table">';
            html += '<thead><tr>';
            html += '<th>Prodotto fattura</th>';
            html += '<th class="text-center">Qtà fattura</th>';
            html += '<th class="text-center" style="width: 130px">Moltiplicatore</th>';
            html += '<th>Materiale</th>';
            html += '<th class="text-center">Giacenza prevista</th>';
            html += '<th class="text-center">Stato</th>';
            html += '</tr></thead><tbody>';

            data.products.forEach(function(p) {
                var rowClass = p.has_stock ? 'already-imported' : '';
                html += '<tr class="' + rowClass + '" data-product-id="' + p.id + '" data-material-id="' + (p.material_id || '') + '">';
                html += '<td><strong>' + p.product_name + '</strong></td>';
                html += '<td class="text-center">' + (p.quantity || '—') + (p.quantity_unit ? ' ' + p.quantity_unit : '') + '</td>';
                html += '<td class="text-center">';
                if (!p.has_stock) {
                    html += '<input type="number" style="width: 100px" class="form-control input-xs multiplier-input text-center" value="' + (p.quantity_multiplier || 1) + '" min="0.001" step="0.001" data-product-id="' + p.id + '" data-quantity="' + (p.quantity || 0) + '">';
                } else {
                    html += '<span>' + (p.quantity_multiplier || 1) + '</span>';
                }
                html += '</td>';
                html += '<td>' + (p.material_label || '—') + '</td>';
                html += '<td class="text-center qty-preview" data-product-id="' + p.id + '">';
                html += formatQty(p.quantity * (p.quantity_multiplier || 1)) + (p.stock_unit ? ' ' + p.stock_unit : '');
                html += '</td>';
                html += '<td class="text-center">';
                if (p.has_stock) {
                    html += '<span class="label label-success"><i class="fa fa-check"></i> Già importata</span>';
                } else {
                    html += '<span class="label label-default">Da importare</span>';
                }
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            $('#importStockBody').html(html);
        }

        function formatQty(val) {
            return parseFloat(val).toLocaleString('it-IT', {minimumFractionDigits: 3, maximumFractionDigits: 4});
        }

        $(document).on('click', '.btn-import-stock', function() {
            importStockInvoiceId = $(this).data('id');
            $('#importStockInvoiceLabel').text('');
            $('#importStockBody').html('<div class="text-center text-muted" style="padding:30px;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
            $('#btnConfirmImportStock').prop('disabled', false);
            $('#importStockModal').modal('show');

            $.ajax({
                url: '/backoffice/invoices/' + importStockInvoiceId + '/import-stock-preview',
                type: 'GET',
                success: function(res) {
                    if (res && res.products) {
                        renderImportStockTable(res);
                    } else {
                        $('#importStockBody').html('<p class="text-danger text-center">Errore nel caricamento dei dati.</p>');
                    }
                },
                error: function() {
                    $('#importStockBody').html('<p class="text-danger text-center">Errore durante il caricamento.</p>');
                }
            });
        });

        // Aggiorna preview giacenza in tempo reale al cambio moltiplicatore
        $(document).on('input change', '.multiplier-input', function() {
            var productId = $(this).data('product-id');
            var qty = parseFloat($(this).data('quantity')) || 0;
            var mult = parseFloat($(this).val()) || 0;
            var preview = qty * mult;
            var $cell = $('.qty-preview[data-product-id="' + productId + '"]');
            var unit = $cell.text().replace(/[\d.,\s]/g, '').trim();
            $cell.text(formatQty(preview) + (unit ? ' ' + unit : ''));
        });

        $(document).on('click', '#btnConfirmImportStock', function() {
            if (!importStockInvoiceId) return;

            var products = [];
            $('#importStockBody tbody tr').each(function() {
                var $row = $(this);
                var $multiplierInput = $row.find('.multiplier-input');

                if ($multiplierInput.length === 0) return; // già importata, skip

                products.push({
                    id: $row.data('product-id'),
                    material_id: $row.data('material-id') || null,
                    quantity_multiplier: parseFloat($multiplierInput.val()) || 1,
                });
            });

            if (products.length === 0) {
                alert('Tutte le giacenze sono già state importate.');
                return;
            }

            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importazione...');

            $.ajax({
                url: '/backoffice/invoices/' + importStockInvoiceId + '/load-invoice-stocks',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ products: products, _token: '{{ csrf_token() }}' }),
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function(res) {
                    btn.prop('disabled', false).html('<i class="fas fa-seedling"></i> Conferma importazione');
                    $('#importStockModal').modal('hide');
                    alert(res.message || 'Importazione completata.');
                    if (window.dataTable) window.dataTable.ajax.reload();
                    else window.location.reload();
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('<i class="fas fa-seedling"></i> Conferma importazione');
                    var msg = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Errore durante l\'importazione.';
                    alert(msg);
                }
            });
        });
        $(document).ready(function(){
            setTimeout(() => {
                console.log('test')
                $(document).trigger('datatable', [{
                    url: '{{ route('invoices.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'supplier_name', orderable: false},
                        {data: 'invoice_number', orderable: false},
                        {data: 'amount', orderable: false},
                        {data: 'invoice_date', orderable: false},
                        {data: 'products', class: 'text-center', orderable: false},
                        {data: 'import', class: 'text-center', orderable: false},
                    ],
                    dataForm: ['invoice_number', 'supplier_id', 'date_from', 'date_to', 'import'],
                    serverSide: true,
                }]);
            }, 500);
        })
    </script>
@endsection
