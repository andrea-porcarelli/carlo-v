@extends('backoffice.layout', ['title' => 'Fatture da mappare'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture da mappare'],
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
                                <input type="hidden" name="ignored" class="ignored" value="tutte">
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
                                        <th class="all">Stato mappatura</th>
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
                                        <th class="all">Da mappare</th>
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
        $(document).ready(function(){
            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('invoices.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'supplier_name'},
                        {data: 'invoice_number'},
                        {data: 'amount'},
                        {data: 'invoice_date'},
                        {data: 'products', class: 'text-center'},
                        {data: 'mapping', class: 'text-center'},
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['invoice_number', 'supplier_id', 'date_from', 'date_to', 'mapping', 'ignored'],
                    serverSide: true,
                }]);
            }, 500);
        })
    </script>
@endsection
