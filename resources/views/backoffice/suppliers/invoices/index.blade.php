@extends('backoffice.layout')
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'title' => 'Fatture',
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
                            <div class="row g-1 advanced-search supplier_refunds_index">
                                @include('backoffice.components.form.input',['name' => 'invoice_number', 'label' => 'Codice fattura', 'col' => 2, 'class' => 'invoice_number'])
                                @include('backoffice.components.form.select',['name' => 'supplier_id', 'label' => 'Fornitore', 'col' => 2, 'options' => Utils::map_collection($suppliers)])
                                @include('backoffice.components.form.select',['name' => 'brand_id', 'label' => 'Brand', 'class' => 'brand_id', 'col' => 2, 'options' => []])
                                @include('backoffice.components.form.button', ['col' => 2, 'label' => 'Cerca', 'class' => 'btn-find m-t-23', 'with_add' => true, 'class_btn_add' => 'btn-add-object m-t-23', 'route' => 'suppliers.invoices.create'])
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">Dettagli</th>
                                        <th class="all">Riepilogo fattura</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">Dettagli</th>
                                        <th class="all">Riepilogo fattura</th>
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

    @include('backoffice.components.dynamic-modal', [
        'title' => 'Associa Bolle di consegna a questa fattura',
        'class' => 'assign-delivery-notes',
        'hide_footer' => true
    ])

@endsection
@section('custom-script')
    <script>
        $(document).ready(function(){
            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('suppliers.invoices.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '90px'},
                        {data: 'details'},
                        {data: 'delivery_notes' },
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['supplier_id', 'brand_id', 'invoice_number'],
                    serverSide: true,
                    iDisplayLength: 25,
                }]);
            }, 500);
        })
    </script>
@endsection
