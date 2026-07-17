@extends('backoffice.layout', ['title' => 'Vendite',])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Vendite'],
    ])
@endsection
@section('main-content')

    <!-- Summary Stats: same KPIs as the "Log Operativo" modal (Venduto) -->
    <div class="row mt-3">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel panel-success">
                <div class="panel-body text-center">
                    <h4 class="mb-0" id="kpiContanti">€0,00</h4>
                    <small><i class="fas fa-coins"></i> Contanti</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel panel-info">
                <div class="panel-body text-center">
                    <h4 class="mb-0" id="kpiPos">€0,00</h4>
                    <small><i class="fas fa-credit-card"></i> POS</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel panel-warning">
                <div class="panel-body text-center">
                    <h4 class="mb-0" id="kpiAutoconsumo">€0,00</h4>
                    <small><i class="fas fa-utensils"></i> Autoconsumo</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel" style="border-color:#e83e8c;">
                <div class="panel-body text-center" style="background:#e83e8c; color:#fff; border-radius:4px;">
                    <h4 class="mb-0" id="kpiChiusureConto">€0,00</h4>
                    <small><i class="fas fa-gift"></i> Chiusura conto</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel" style="border-color:#fd7e14;">
                <div class="panel-body text-center" style="background:#fd7e14; color:#fff; border-radius:4px;">
                    <h4 class="mb-0" id="kpiBanco">€0,00</h4>
                    <small><i class="fas fa-store"></i> Vendite al banco</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel" style="border-color:#343a40;">
                <div class="panel-body text-center" style="background:#343a40; color:#fff; border-radius:4px; padding:14px 10px;">
                    <div style="display:flex; justify-content:space-around; align-items:center;">
                        <div>
                            <h4 class="mb-0" id="kpiScontriniCount" style="font-weight:700;">0</h4>
                            <small style="font-size:0.75rem;">Scontrini</small>
                        </div>
                        <div style="width:1px; height:32px; background:rgba(255,255,255,0.25);"></div>
                        <div>
                            <h4 class="mb-0" id="kpiFattureCount" style="font-weight:700;">0</h4>
                            <small style="font-size:0.75rem;">Fatture</small>
                        </div>
                    </div>
                    <small style="display:block; margin-top:6px; opacity:0.75;"><i class="fas fa-receipt"></i> Fiscale</small>
                </div>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="panel panel-primary">
                <div class="panel-body text-center" style="padding:14px 20px;">
                    <h2 class="mb-0" id="kpiTotaleIncassato" style="font-weight:700; letter-spacing:1px;">€0,00</h2>
                    <small style="font-size:0.9rem; text-transform:uppercase; letter-spacing:1px;">Totale Incassato (Contanti + POS + Fatture)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row g-1 advanced-search">
                                @include('backoffice.components.form.input', [
                                    'label' => 'Data da',
                                    'name' => 'date_from',
                                    'col' => 2,
                                    'class' => 'date_from',
                                    'type' => 'date',
                                    'value' => date('Y-m-d'),
                                ])
                                @include('backoffice.components.form.input', [
                                    'label' => 'Data a',
                                    'name' => 'date_to',
                                    'col' => 2,
                                    'class' => 'date_to',
                                    'type' => 'date',
                                    'value' => date('Y-m-d'),
                                ])
                                @include('backoffice.components.form.input', [
                                    'label' => 'Numero Tavolo',
                                    'name' => 'table_number',
                                    'col' => 2,
                                    'class' => 'table_number',
                                    'type' => 'number'
                                ])
                                <div class="col-xs-12 col-sm-2">
                                    <label>Metodo di pagamento</label>
                                    <select class="form-control payment_method" name="payment_method">
                                        <option value="">Tutti</option>
                                        @foreach(\App\Models\TableOrder::paymentMethodLabels() as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xs-12 col-sm-3" style="display:flex; gap: 5px">
                                    <button type="button" class="btn btn-info btn-find">Cerca</button>
                                    <button type="button" class="btn btn-warning" id="btnPrintLogs">
                                        <i class="fa fa-print"></i> Stampa Log
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-12 mt-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Info:</strong> Questa sezione mostra tutte le vendite completate e incassate.
                                Per visualizzare i dettagli di una vendita, clicca sull'icona di visualizzazione.
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Tavolo / Data</th>
                                        <th class="all">N° Prodotti</th>
                                        <th class="all">Totale</th>
                                        <th class="all">Pagamento</th>
                                        <th class="all">Cameriere</th>
                                        <th class="all">Durata</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Tavolo / Data</th>
                                        <th class="all">N° Prodotti</th>
                                        <th class="all">Totale</th>
                                        <th class="all">Pagamento</th>
                                        <th class="all">Cameriere</th>
                                        <th class="all">Durata</th>
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

    <!-- Print Logs Modal -->
    <div class="modal fade" id="printLogsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #f0ad4e; color: white;">
                    <h4 class="modal-title">
                        <i class="fa fa-print"></i> Stampa Log Operazioni
                    </h4>
                    <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 1;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="printLogsForm">
                        <div class="row">
                            <!-- Operatore -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fa fa-user"></i> Operatore</label>
                                    <select name="user_id" id="logUserSelect" class="form-control">
                                        <option value="">Tutti gli operatori</option>
                                        @php
                                            $users = \App\Models\User::orderBy('name')->get();
                                        @endphp
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Tavolo -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fa fa-chair"></i> Numero Tavolo</label>
                                    <input type="number" name="table_number" id="logTableNumber" class="form-control" placeholder="Lascia vuoto per tutti">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Data Da -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fa fa-calendar"></i> Data Da</label>
                                    <input type="date" name="date_from" id="logDateFrom" class="form-control" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>

                            <!-- Data A -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fa fa-calendar"></i> Data A</label>
                                    <input type="date" name="date_to" id="logDateTo" class="form-control" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Categorie Log -->
                        <div class="form-group">
                            <label style="font-weight: bold;"><i class="fa fa-filter"></i> Tipologia di Log</label>
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <label class="checkbox-inline" style="margin-right: 20px;">
                                        <input type="checkbox" name="log_categories[]" value="all" id="logCategoryAll" checked>
                                        <strong>Tutte le categorie</strong>
                                    </label>
                                </div>
                            </div>
                            <div class="row mt-2">
                                @php
                                    $categories = \App\Models\TableOrderLog::getAvailableCategories();
                                @endphp
                                @foreach($categories as $key => $label)
                                    <div class="col-md-3">
                                        <label class="checkbox-inline">
                                            <input type="checkbox" name="log_categories[]" value="{{ $key }}" class="log-category-checkbox">
                                            {{ $label }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <hr>

                        <!-- Stampante -->
                        <div class="form-group">
                            <label style="font-weight: bold;"><i class="fa fa-print"></i> Stampante</label>
                            <select name="printer_id" id="logPrinterSelect" class="form-control" required>
                                <option value="">-- Seleziona stampante --</option>
                                @php
                                    $printers = \App\Models\Printer::where('is_active', true)->orderBy('label')->get();
                                @endphp
                                @foreach($printers as $printer)
                                    <option value="{{ $printer->id }}">{{ $printer->label }} ({{ $printer->ip }})</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        <i class="fa fa-times"></i> Annulla
                    </button>
                    <button type="button" class="btn btn-warning" id="btnConfirmPrintLogs">
                        <i class="fa fa-print"></i> Stampa
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('custom-script')
    <script>
        $(document).ready(function(){
            let dataTable;

            function fmtMoney(v) {
                return '€' + Number(v || 0).toFixed(2).replace('.', ',');
            }

            function loadSalesKpis() {
                const filters = {
                    date_from:      $('.date_from').val()      || '',
                    date_to:        $('.date_to').val()        || '',
                    table_number:   $('.table_number').val()   || '',
                    payment_method: $('.payment_method').val() || '',
                };
                $.ajax({
                    url: '{{ route('restaurant.sales.kpis') }}',
                    method: 'GET',
                    data: { filters: filters },
                    success: function(k) {
                        $('#kpiContanti').text(fmtMoney(k.contanti));
                        $('#kpiPos').text(fmtMoney(k.pos));
                        $('#kpiAutoconsumo').text(fmtMoney(k.autoconsumo));
                        $('#kpiChiusureConto').text(fmtMoney(k.chiusure_conto));
                        $('#kpiBanco').text(fmtMoney(k.vendite_banco));
                        $('#kpiScontriniCount').text(k.scontrini_count || 0);
                        $('#kpiFattureCount').text(k.fatture_count || 0);
                        $('#kpiTotaleIncassato').text(fmtMoney(k.totale_incassato));
                    }
                });
            }

            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('restaurant.sales.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'id', width: '40px'},
                        {data: 'sale_info'},
                        {data: 'items_count', class: 'text-center'},
                        {data: 'total', class: 'text-end'},
                        {data: 'payment'},
                        {data: 'waiter'},
                        {data: 'duration', class: 'text-center'},
                    ],
                    // L'ordinamento vero è imposto lato server (updated_at DESC).
                    // Disabilitiamo l'ordinamento default del client per non generare un
                    // sort su una colonna che il server non applica comunque.
                    order: [],
                    dataForm: ['date_from', 'date_to', 'table_number', 'payment_method'],
                    serverSide: true,
                    drawCallback: function() {
                        loadSalesKpis();
                    }
                }]);

                dataTable = $('.datatable_table').DataTable();
                loadSalesKpis();
            }, 500);

            // Open print logs modal
            $('#btnPrintLogs').on('click', function() {
                // Pre-fill dates from search filters if set
                const dateFrom = $('.date_from').val();
                const dateTo = $('.date_to').val();
                const tableNumber = $('.table_number').val();

                if (dateFrom) $('#logDateFrom').val(dateFrom);
                if (dateTo) $('#logDateTo').val(dateTo);
                if (tableNumber) $('#logTableNumber').val(tableNumber);

                $('#printLogsModal').modal('show');
            });

            // Category checkboxes logic
            const logCategoryAll = document.getElementById('logCategoryAll');
            const logCategoryCheckboxes = document.querySelectorAll('.log-category-checkbox');

            logCategoryAll.addEventListener('change', function() {
                if (this.checked) {
                    logCategoryCheckboxes.forEach(cb => cb.checked = false);
                }
            });

            logCategoryCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    if (this.checked) {
                        logCategoryAll.checked = false;
                    }
                    // If no category is selected, select "all"
                    const anySelected = Array.from(logCategoryCheckboxes).some(c => c.checked);
                    if (!anySelected) {
                        logCategoryAll.checked = true;
                    }
                });
            });

            // Print logs
            $('#btnConfirmPrintLogs').on('click', function() {
                const printerId = $('#logPrinterSelect').val();
                if (!printerId) {
                    alert('Seleziona una stampante');
                    return;
                }

                // Get selected categories
                let categories = [];
                if (logCategoryAll.checked) {
                    categories = ['all'];
                } else {
                    logCategoryCheckboxes.forEach(cb => {
                        if (cb.checked) categories.push(cb.value);
                    });
                }

                if (categories.length === 0) {
                    alert('Seleziona almeno una categoria');
                    return;
                }

                const btn = $(this);
                const originalText = btn.html();
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Stampa in corso...');

                $.ajax({
                    url: '/backoffice/logs/print-logs-filtered',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    contentType: 'application/json',
                    data: JSON.stringify({
                        user_id: $('#logUserSelect').val() || null,
                        table_number: $('#logTableNumber').val() || null,
                        date_from: $('#logDateFrom').val(),
                        date_to: $('#logDateTo').val(),
                        categories: categories,
                        printer_id: printerId
                    }),
                    success: function(response) {
                        if (response.success) {
                            alert('Log inviati alla stampante! (' + response.logs_count + ' operazioni)');
                            $('#printLogsModal').modal('hide');
                        } else {
                            alert('Errore: ' + (response.message || 'Errore durante la stampa'));
                        }
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON || {};
                        alert('Errore: ' + (response.message || 'Errore durante la stampa'));
                    },
                    complete: function() {
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });
        })
    </script>
@endsection
