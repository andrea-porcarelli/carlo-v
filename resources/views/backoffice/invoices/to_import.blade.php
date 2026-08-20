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
                                        <th class="all no-sort">Fornitore</th>
                                        <th class="all no-sort">N* fattura </th>
                                        <th class="all no-sort">Importo </th>
                                        <th class="all">Data </th>
                                        <th class="all no-sort">Da importare</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all no-sort">Fornitore</th>
                                        <th class="all no-sort">N* fattura </th>
                                        <th class="all no-sort">Importo </th>
                                        <th class="all">Data </th>
                                        <th class="all no-sort">Da importare</th>
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
        <div class="modal-dialog" role="document" style="max-width: 1400px; width: 95%;">
            <div class="modal-content">
                <div class="modal-header" style="background:#f8f9fa;">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title" style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin:0;">
                        <span>
                            <i class="fas fa-seedling"></i> Importa giacenze
                            <span id="importStockInvoiceLabel" class="text-muted" style="font-weight:normal;"></span>
                        </span>
                        <a href="#" target="_blank" id="importStockPdfLink" class="btn btn-sm btn-danger" style="display:none; margin-right:28px;">
                            <i class="fa fa-file-pdf"></i> Apri PDF fattura
                        </a>
                    </h4>
                </div>
                <div class="modal-body" id="importStockBody">
                    <div class="text-center text-muted" style="padding:30px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="importStockFooterSummary" class="text-left" style="flex:1; display:none;"></div>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-success" id="btnConfirmImportStock" disabled>
                        <i class="fas fa-seedling"></i> Conferma importazione
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('custom-css')
    <style>
        .mapping-summary { min-width: 260px; }
        .import-hero {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 4px 10px; margin-bottom: 8px;
            border-radius: 6px; border: 1px solid transparent;
        }
        .import-hero.has-pending {
            background: #fff3cd; border-color: #f0ad4e; color: #8a6d3b;
        }
        .import-hero.all-done {
            background: #dff0d8; border-color: #5cb85c; color: #3c763d;
        }
        .import-hero-number {
            font-size: 20px; font-weight: 700; line-height: 1;
        }
        .import-hero-label {
            font-size: 11px; text-transform: uppercase;
            letter-spacing: .3px; font-weight: 600;
        }
        .mapping-total {
            font-size: 13px; color: #555; margin-bottom: 8px;
            padding-bottom: 6px; border-bottom: 1px solid #eee;
        }
        .mapping-total strong { font-size: 16px; color: #222; }
        .mapping-badges { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; }
        .mb-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 10px; border-radius: 6px;
            font-size: 12px; line-height: 1;
            border: 1px solid transparent;
        }
        .mb-badge strong { font-size: 18px; font-weight: 700; }
        .mb-badge span { opacity: .85; }
        .mb-badge.is-zero { opacity: .35; filter: grayscale(1); }
        .mb-to-map    { background: #f2dede; color: #a94442; border-color: #ebccd1; }
        .mb-mapped    { background: #d9edf7; color: #31708f; border-color: #bce8f1; }
        .mb-ignored   { background: #f5f5f5; color: #777;    border-color: #ddd; }
        .mb-to-import { background: #fff3cd; color: #8a6d3b; border-color: #f0d58a; }
        .mb-imported  { background: #dff0d8; color: #3c763d; border-color: #c9e2b3; }

        .import-stock-table tr.unmapped {
            background-color: #fbe9e9 !important;
        }
        .import-stock-table tr.unmapped td {
            border-color: #ebccd1;
        }
        .import-stock-table tr.has-alert {
            background-color: #fffaf0;
        }
        .import-unmapped-alert { margin-bottom: 12px; }

        .actions { display: flex; flex-direction: column; gap: 2px; align-items: center; }

        /* ── Import modal header ─────────────────────────────────────────── */
        .isp-invoice-panel {
            background:#f5f7fa; border:1px solid #e3e8ee; border-radius:6px;
            padding:12px 16px; margin-bottom:12px;
            display:flex; flex-wrap:wrap; gap:18px;
        }
        .isp-invoice-panel .isp-item { min-width:140px; }
        .isp-invoice-panel .isp-item small { color:#7b8897; font-size:11px; text-transform:uppercase; letter-spacing:.3px; display:block; }
        .isp-invoice-panel .isp-item strong { font-size:15px; color:#222; }

        .isp-summary-bar {
            display:flex; gap:10px; margin-bottom:12px; flex-wrap:wrap;
        }
        .isp-summary-badge {
            flex:1; min-width:130px;
            padding:8px 12px; border-radius:6px;
            background:#fff; border:1px solid #e3e8ee;
            text-align:center;
        }
        .isp-summary-badge .isp-value { font-size:20px; font-weight:700; line-height:1.2; }
        .isp-summary-badge .isp-label { font-size:11px; color:#7b8897; text-transform:uppercase; letter-spacing:.3px; }
        .isp-summary-badge.imported   { background:#dff0d8; border-color:#c9e2b3; color:#3c763d; }
        .isp-summary-badge.to-import  { background:#fff3cd; border-color:#f0d58a; color:#8a6d3b; }
        .isp-summary-badge.unmapped   { background:#f2dede; border-color:#ebccd1; color:#a94442; }
        .isp-summary-badge.alerts     { background:#fdecea; border-color:#f5c6cb; color:#721c24; }
        .isp-summary-badge.value      { background:#eef4fb; border-color:#bce8f1; color:#31708f; }

        /* ── Row cell formatting ─────────────────────────────────────────── */
        .import-stock-table .isp-formula {
            font-size:12px; color:#555; line-height:1.3;
        }
        .import-stock-table .isp-formula .isp-qty-val,
        .import-stock-table .isp-formula .isp-mult-val {
            font-weight:600; color:#222;
        }
        .import-stock-table .isp-preview {
            font-size:16px; font-weight:700; color:#1ab394;
        }
        .import-stock-table .isp-price-stack {
            line-height:1.3;
        }
        .import-stock-table .isp-price-stack .isp-price-main { font-weight:600; font-size:13px; }
        .import-stock-table .isp-price-stack .isp-price-sub  { font-size:11px; color:#7b8897; }

        .isp-last-box {
            font-size:12px; line-height:1.35;
        }
        .isp-last-box .isp-last-price { font-weight:600; }
        .isp-last-box .isp-last-meta  { color:#7b8897; font-size:11px; }
        .isp-delta-up   { color:#a94442; font-weight:700; }
        .isp-delta-down { color:#3c763d; font-weight:700; }
        .isp-delta-neutral { color:#7b8897; }

        .isp-stock-arrow {
            display:inline-block; padding:0 4px; color:#7b8897;
        }
        .isp-stock-after { font-weight:700; color:#337ab7; }

        .isp-alerts { display:flex; flex-wrap:wrap; gap:3px; }
        .isp-alert-badge {
            display:inline-block; font-size:10px; font-weight:700;
            padding:2px 6px; border-radius:3px; letter-spacing:.3px;
            text-transform:uppercase; cursor:help;
        }
        .isp-alert-badge.alert-first      { background:#eef4fb; color:#31708f; border:1px solid #bce8f1; }
        .isp-alert-badge.alert-price      { background:#fdecea; color:#721c24; border:1px solid #f5c6cb; }
        .isp-alert-badge.alert-multiplier { background:#fff3cd; color:#8a6d3b; border:1px solid #f0d58a; }
        .isp-alert-badge.alert-unit       { background:#fce4ec; color:#880e4f; border:1px solid #f8bbd0; }

        .import-stock-table th {
            background:#f8f9fa; font-size:11px;
            text-transform:uppercase; letter-spacing:.3px;
            color:#555;
        }
        .import-stock-table td { vertical-align:middle !important; }

        .isp-skip-col { text-align:center; width:40px; }
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
        var importStockData = null;

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function(m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
            });
        }

        function renderAlertBadges(alerts) {
            if (!alerts || !alerts.length) return '';
            var map = {
                'first_purchase':     { cls:'alert-first',      label:'1° acquisto', title:'Primo acquisto di questo materiale' },
                'multiplier_changed': { cls:'alert-multiplier', label:'mult. cambiato', title:'Il moltiplicatore differisce dall\'ultima importazione' },
                'unit_mismatch':      { cls:'alert-unit',       label:'unità diversa', title:'L\'unità in fattura non coincide con lo stock_type del materiale' },
            };
            var html = '<div class="isp-alerts">';
            alerts.forEach(function(a) {
                var m = map[a];
                if (m) html += '<span class="isp-alert-badge '+m.cls+'" title="'+escapeHtml(m.title)+'">'+m.label+'</span>';
            });
            html += '</div>';
            return html;
        }

        function renderLastPurchaseCell(lp) {
            if (!lp) return '<span class="text-muted" style="font-size:12px;">—</span>';
            var parts = [];
            if (lp.quantity !== null && lp.quantity !== undefined) {
                parts.push('<div class="isp-last-price"><span class="isp-qty-val">' + lp.quantity + '</span>' + (lp.quantity_unit ? ' ' + lp.quantity_unit : '') + ' × <span class="isp-mult-val">' + (lp.multiplier ?? '1') + '</span></div>');
            }
            var meta = [];
            if (lp.date) meta.push(lp.date);
            if (lp.invoice_number) meta.push('fatt. ' + escapeHtml(lp.invoice_number));
            if (meta.length) parts.push('<div class="isp-last-meta">' + meta.join(' · ') + '</div>');
            return '<div class="isp-last-box">' + parts.join('') + '</div>';
        }

        function renderHeader(data) {
            $('#importStockInvoiceLabel').text('— ' + data.invoice.supplier + ' · Fattura ' + data.invoice.number);

            var html = '<div class="isp-invoice-panel">';
            html += '<div class="isp-item"><small>Fornitore</small><strong>' + escapeHtml(data.invoice.supplier) + '</strong></div>';
            html += '<div class="isp-item"><small>N° fattura</small><strong>' + escapeHtml(data.invoice.number) + '</strong></div>';
            html += '<div class="isp-item"><small>Data</small><strong>' + (data.invoice.date || '—') + '</strong></div>';
            html += '<div class="isp-item"><small>Importo fattura</small><strong>' + data.invoice.amount + '</strong></div>';
            html += '</div>';

            var s = data.summary;
            html += '<div class="isp-summary-bar">';
            html += '<div class="isp-summary-badge"><div class="isp-value">'+s.lines_total+'</div><div class="isp-label">Righe tot.</div></div>';
            html += '<div class="isp-summary-badge to-import"><div class="isp-value">'+s.lines_to_import+'</div><div class="isp-label">Da importare</div></div>';
            html += '<div class="isp-summary-badge imported"><div class="isp-value">'+s.lines_already_imported+'</div><div class="isp-label">Già importate</div></div>';
            html += '<div class="isp-summary-badge unmapped"><div class="isp-value">'+s.lines_unmapped+'</div><div class="isp-label">Non mappate</div></div>';
            html += '<div class="isp-summary-badge alerts"><div class="isp-value">'+s.lines_with_alerts+'</div><div class="isp-label">Con alert</div></div>';
            html += '</div>';
            return html;
        }

        function renderImportStockTable(data) {
            importStockData = data;
            var unmappedCount = data.summary.lines_unmapped;

            var html = renderHeader(data);

            if (unmappedCount > 0) {
                html += '<div class="alert alert-warning import-unmapped-alert">';
                html += '<i class="fa fa-exclamation-triangle"></i> ';
                html += '<strong>' + unmappedCount + (unmappedCount === 1 ? ' prodotto non mappato' : ' prodotti non mappati') + '</strong> ';
                html += '(righe evidenziate in rosso). Mappa questi prodotti prima di importare le giacenze.';
                html += '</div>';
            }
            if (data.summary.lines_with_alerts > 0) {
                html += '<div class="alert alert-info" style="margin-bottom:12px;">';
                html += '<i class="fa fa-info-circle"></i> ';
                html += '<strong>' + data.summary.lines_with_alerts + (data.summary.lines_with_alerts === 1 ? ' riga richiede attenzione' : ' righe richiedono attenzione') + '</strong> — verifica i valori prima di confermare.';
                html += '</div>';
            }

            html += '<div style="overflow-x:auto;">';
            html += '<table class="table table-condensed table-bordered import-stock-table">';
            html += '<thead><tr>';
            html += '<th class="isp-skip-col" title="Deseleziona per escludere dall\'import"><i class="fa fa-check-square"></i></th>';
            html += '<th>Prodotto fattura</th>';
            html += '<th class="text-center">Qtà × Molt. = Giacenza</th>';
            html += '<th>Materiale</th>';
            html += '<th>Ultima importazione</th>';
            html += '<th class="text-center">Magazzino</th>';
            html += '<th>Alert</th>';
            html += '<th class="text-center">Stato</th>';
            html += '</tr></thead><tbody>';

            data.products.forEach(function(p) {
                var isUnmapped = !p.has_stock && !p.material_id;
                var hasAlerts = (p.alerts || []).length > 0;
                var rowClasses = [];
                if (p.has_stock)  rowClasses.push('already-imported');
                if (isUnmapped)   rowClasses.push('unmapped');
                if (hasAlerts && !p.has_stock && !isUnmapped) rowClasses.push('has-alert');

                html += '<tr class="' + rowClasses.join(' ') + '" data-product-id="' + p.id + '" data-material-id="' + (p.material_id || '') + '" data-quantity="' + p.quantity + '" data-stock-unit="' + (p.stock_unit || '') + '">';

                // skip checkbox
                html += '<td class="isp-skip-col">';
                if (!p.has_stock && !isUnmapped) {
                    html += '<input type="checkbox" class="isp-skip-cb" checked data-product-id="'+p.id+'">';
                }
                html += '</td>';

                // product
                html += '<td><strong>' + escapeHtml(p.product_name) + '</strong></td>';

                // calcolo
                html += '<td class="text-center">';
                html += '<div class="isp-formula">';
                html += '<span class="isp-qty-val">' + p.quantity + '</span> ' + (p.quantity_unit || '') + ' × ';
                if (!p.has_stock) {
                    html += '<input type="number" class="form-control input-xs multiplier-input" style="width:70px; display:inline-block; text-align:center;" value="'+p.quantity_multiplier+'" min="0.001" step="0.001" data-product-id="'+p.id+'"' + (isUnmapped ? ' disabled' : '') + '>';
                } else {
                    html += '<span class="isp-mult-val">' + p.quantity_multiplier + '</span>';
                }
                html += '</div>';
                html += '<div class="isp-preview qty-preview" data-product-id="'+p.id+'">';
                if (isUnmapped) {
                    html += '<span class="text-muted">—</span>';
                } else {
                    html += '= ' + formatQty(p.stock_preview) + ' ' + (p.stock_unit || '');
                }
                html += '</div>';
                html += '</td>';

                // materiale
                html += '<td>';
                if (isUnmapped) {
                    html += '<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Non mappato</span>';
                } else {
                    html += '<strong>' + escapeHtml(p.material_label || '—') + '</strong>';
                    if (p.stock_unit) html += '<br><small class="text-muted">unità: '+p.stock_unit+'</small>';
                }
                html += '</td>';

                // ultima importazione (qty + mult + data, no prezzi)
                html += '<td>' + renderLastPurchaseCell(p.last_purchase) + '</td>';

                // magazzino
                html += '<td class="text-center">';
                if (isUnmapped || p.current_material_stock === null) {
                    html += '<span class="text-muted">—</span>';
                } else {
                    html += '<span style="font-size:12px;">'+formatQty(p.current_material_stock)+'</span>';
                    html += '<span class="isp-stock-arrow">→</span>';
                    html += '<span class="isp-stock-after stock-after-cell" data-product-id="'+p.id+'">'+formatQty(p.stock_after_import)+'</span>';
                    html += '<br><small class="text-muted">'+(p.stock_unit || '')+'</small>';
                }
                html += '</td>';

                // alerts
                html += '<td>' + renderAlertBadges(p.alerts) + '</td>';

                // stato
                html += '<td class="text-center">';
                if (p.has_stock) {
                    html += '<span class="label label-success"><i class="fa fa-check"></i> Importata</span>';
                } else if (isUnmapped) {
                    html += '<span class="label label-danger">Da mappare</span>';
                } else {
                    html += '<span class="label label-default">Da importare</span>';
                }
                html += '</td>';

                html += '</tr>';
            });

            html += '</tbody></table></div>';
            $('#importStockBody').html(html);

            updateImportFooter();
        }

        function updateImportFooter() {
            var selected = $('#importStockBody .isp-skip-cb:checked').length;
            var unmappedCount = importStockData ? importStockData.summary.lines_unmapped : 0;
            var canImport = unmappedCount === 0 && selected > 0;

            var $summary = $('#importStockFooterSummary');
            if (selected > 0) {
                $summary.html('<span class="text-muted">Pronte all\'import: <strong>'+selected+'</strong> righe</span>').show();
            } else {
                $summary.hide();
            }

            $('#btnConfirmImportStock').prop('disabled', !canImport);
            if (unmappedCount > 0) {
                $('#btnConfirmImportStock').attr('title', 'Mappa tutti i prodotti prima di importare le giacenze');
            } else if (selected === 0) {
                $('#btnConfirmImportStock').attr('title', 'Seleziona almeno una riga');
            } else {
                $('#btnConfirmImportStock').removeAttr('title');
            }
        }

        function formatQty(val) {
            return parseFloat(val).toLocaleString('it-IT', {minimumFractionDigits: 3, maximumFractionDigits: 4});
        }

        $(document).on('click', '.btn-import-stock', function() {
            importStockInvoiceId = $(this).data('id');
            $('#importStockInvoiceLabel').text('');
            $('#importStockBody').html('<div class="text-center text-muted" style="padding:30px;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
            $('#btnConfirmImportStock').prop('disabled', false);
            $('#importStockPdfLink')
                .attr('href', '/backoffice/invoices/' + importStockInvoiceId + '/pdf')
                .show();
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

        // Aggiorna preview giacenza + prezzo/unità + stock dopo, in tempo reale al cambio moltiplicatore
        $(document).on('input change', '.multiplier-input', function() {
            var productId = $(this).data('product-id');
            var mult = parseFloat($(this).val()) || 0;
            var $row = $('#importStockBody tr[data-product-id="' + productId + '"]');
            var qty = parseFloat($row.data('quantity')) || 0;
            var stockUnit = $row.data('stock-unit') || '';

            // find product in data
            var product = importStockData && importStockData.products.find(function(p){return p.id == productId;});
            if (!product) return;

            var preview = qty * mult;
            $row.find('.qty-preview').html('= ' + formatQty(preview) + ' ' + stockUnit);

            // stock after
            if (product.current_material_stock !== null) {
                var after = product.current_material_stock + preview;
                $row.find('.stock-after-cell').text(formatQty(after));
            }
        });

        // Toggle skip checkbox → disabilita/abilita riga
        $(document).on('change', '.isp-skip-cb', function() {
            var $row = $(this).closest('tr');
            $row.css('opacity', this.checked ? '1' : '0.45');
            $row.find('.multiplier-input').prop('disabled', !this.checked);
            updateImportFooter();
        });

        $(document).on('click', '#btnConfirmImportStock', function() {
            if (!importStockInvoiceId) return;

            var products = [];
            $('#importStockBody tbody tr').each(function() {
                var $row = $(this);
                var $multiplierInput = $row.find('.multiplier-input');
                var $skipCb = $row.find('.isp-skip-cb');

                if ($multiplierInput.length === 0) return; // già importata, skip
                if ($skipCb.length && !$skipCb.is(':checked')) return; // utente ha deselezionato

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
                    $(document).trigger('reloadDatatable');
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
                        {data: 'invoice_date'},
                        {data: 'import', class: 'text-center', orderable: false},
                    ],
                    order: [[4, 'desc']],
                    dataForm: ['invoice_number', 'supplier_id', 'date_from', 'date_to', 'import'],
                    serverSide: true,
                }]);
            }, 500);
        })
    </script>
@endsection
