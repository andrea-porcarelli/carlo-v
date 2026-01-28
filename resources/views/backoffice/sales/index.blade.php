@extends('backoffice.layout', ['title' => 'Vendite',])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Vendite'],
    ])
@endsection
@section('main-content')

    <!-- Summary Stats -->
    <div class="row mt-3">
        <div class="col-lg-3">
            <div class="panel panel-primary">
                <div class="panel-body text-center">
                    <h3 class="mb-0" id="totalSales">€0.00</h3>
                    <small>Totale Vendite</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="panel panel-info">
                <div class="panel-body text-center">
                    <h3 class="mb-0" id="totalOrders">0</h3>
                    <small>Numero Ordini</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="panel panel-success">
                <div class="panel-body text-center">
                    <h3 class="mb-0" id="avgOrder">€0.00</h3>
                    <small>Media per Ordine</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="panel panel-warning">
                <div class="panel-body text-center">
                    <h3 class="mb-0" id="totalItems">0</h3>
                    <small>Prodotti Venduti</small>
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
                                    'type' => 'date'
                                ])
                                @include('backoffice.components.form.input', [
                                    'label' => 'Data a',
                                    'name' => 'date_to',
                                    'col' => 2,
                                    'class' => 'date_to',
                                    'type' => 'date'
                                ])
                                @include('backoffice.components.form.input', [
                                    'label' => 'Numero Tavolo',
                                    'name' => 'table_number',
                                    'col' => 2,
                                    'class' => 'table_number',
                                    'type' => 'number'
                                ])
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

            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('restaurant.sales.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'id', width: '40px'},
                        {data: 'sale_info'},
                        {data: 'items_count', class: 'text-center'},
                        {data: 'total', class: 'text-end'},
                        {data: 'waiter'},
                        {data: 'duration', class: 'text-center'},
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['date_from', 'date_to', 'table_number'],
                    serverSide: true,
                    drawCallback: function(settings) {
                        // Calculate totals from current data
                        let api = this.api();
                        let data = api.rows({search: 'applied'}).data();

                        let totalSales = 0;
                        let totalOrders = data.length;
                        let totalItems = 0;

                        data.each(function(row) {
                            totalSales += parseFloat(row.total_amount || 0);
                            totalItems += parseInt(row.items_count || 0);
                        });

                        let avgOrder = totalOrders > 0 ? totalSales / totalOrders : 0;

                        // Update summary cards
                        $('#totalSales').text('€' + totalSales.toFixed(2));
                        $('#totalOrders').text(totalOrders);
                        $('#avgOrder').text('€' + avgOrder.toFixed(2));
                        $('#totalItems').text(totalItems);
                    }
                }]);

                dataTable = $('.datatable_table').DataTable();
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
