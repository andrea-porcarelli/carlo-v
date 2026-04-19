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
        .mapping-summary { min-width: 260px; }
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
        .mb-to-map    { background: #fff3cd; color: #8a6d3b; border-color: #f0d58a; }
        .mb-mapped    { background: #d9edf7; color: #31708f; border-color: #bce8f1; }
        .mb-ignored   { background: #f5f5f5; color: #777;    border-color: #ddd; }
        .mb-to-import { background: #fff3cd; color: #8a6d3b; border-color: #f0d58a; }
        .mb-imported  { background: #dff0d8; color: #3c763d; border-color: #c9e2b3; }

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
                        {data: 'supplier_name', orderable: false},
                        {data: 'invoice_number', orderable: false},
                        {data: 'amount', orderable: false},
                        {data: 'invoice_date', orderable: false},
                        {data: 'products', class: 'text-center', orderable: false},
                        {data: 'mapping', class: 'text-center', orderable: false},
                    ],
                    dataForm: ['invoice_number', 'supplier_id', 'date_from', 'date_to', 'mapping', 'ignored'],
                    serverSide: true,
                }]);
            }, 500);
        })
    </script>
@endsection
