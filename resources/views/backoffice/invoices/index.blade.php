@extends('backoffice.layout', ['title' => 'Fatture'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture'],
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
                                @include('backoffice.components.form.input', ['label' => 'Nome, Cognome, Email', 'name' => 'mixed', 'col' => 2, 'class' => 'mixed'])
                                @include('backoffice.components.form.button', ['col' => 1, 'label' => 'Cerca', 'class' => 'btn-find'])
                                @include('backoffice.components.form.button', ['col' => 1, 'label' => 'Carica fattura', 'class' => 'btn-load-invoice', 'dataset' => ['path' => route('invoices.import')]])
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
                                        <th class="all">Da mappare / Mappati / Importati</th>
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
                                        <th class="all">Da mappare / Mappati / Importati</th>
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
@section('custom-script')
    <script>
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
                    dataForm: ['mixed'],
                    serverSide: false,
                }]);
            }, 500);
        })
    </script>
@endsection
