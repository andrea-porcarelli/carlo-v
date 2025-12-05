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
                                @include('backoffice.components.form.button', [
                                    'col' => 2,
                                    'label' => 'Cerca',
                                    'class' => 'btn-find'
                                ])
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
        })
    </script>
@endsection
