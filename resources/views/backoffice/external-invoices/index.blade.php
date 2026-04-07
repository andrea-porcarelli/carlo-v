@extends('backoffice.layout', ['title' => 'Fatture ricevute'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture ricevute'],
    ])
@endsection


@section('main-content')
    <div class="row">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="row g-1 advanced-search">
                                @include('backoffice.components.form.input', ['label' => 'Fornitore', 'name' => 'supplier', 'col' => 2, 'class' => 'supplier'])
                                @include('backoffice.components.form.select', ['label' => 'Tipo documento', 'name' => 'document_type', 'col' => 2, 'class' => 'document_type', 'options' => $document_types, 'first_value_text' => 'Tutti'])
                                @include('backoffice.components.form.select', ['label' => 'Stato SDI', 'name' => 'status', 'col' => 2, 'class' => 'status', 'options' => $statuses, 'first_value_text' => 'Tutti'])
                                @include('backoffice.components.form.select', ['label' => 'Mese', 'name' => 'month', 'col' => 2, 'class' => 'month', 'options' => $months, 'first_value_text' => 'Tutti'])
                                @include('backoffice.components.form.select', ['label' => 'Anno', 'name' => 'year', 'col' => 2, 'class' => 'year', 'options' => $years, 'first_value_text' => 'Tutti', 'value' => Carbon::now()->format('Y')])
                                @include('backoffice.components.form.button', ['col' => 2, 'label' => 'Cerca', 'class' => 'btn-find'])
                            </div>
                        </div>
                        <div class="col-xs-12 m-t-sm">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">Numero / Data</th>
                                        <th class="all">Fornitore</th>
                                        <th class="all">Tipo doc.</th>
                                        <th class="all">Righe</th>
                                        <th class="all">Totale</th>
                                        <th class="all">Stato SDI</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">Numero / Data</th>
                                        <th class="all">Fornitore</th>
                                        <th class="all">Tipo doc.</th>
                                        <th class="all">Righe</th>
                                        <th class="all">Totale</th>
                                        <th class="all">Stato SDI</th>
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
        $(document).ready(function () {
            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('external-invoices.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '60px'},
                        {data: 'number_date'},
                        {data: 'supplier'},
                        {data: 'document_type'},
                        {data: 'lines_col', orderable: false, searchable: false},
                        {data: 'total', orderable: false, searchable: false, width: '110px'},
                        {data: 'status'},
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['supplier', 'document_type', 'status', 'month', 'year'],
                    searchBar: false,
                }]);
            }, 500);
        });
    </script>
@endsection
