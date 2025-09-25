@extends('backoffice.layout')

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'title' => 'Registra fattura fornitore',
        'level_0' => ['label' => 'Fornitori', 'href' => route('suppliers')],
        'level_1' => ['label' => 'Fatture', 'href' => route('suppliers.invoices')],
        'level_2' => ['label' => 'Registra fattura fornitore'],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12 col-sm-9">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation create-invoice" id="create-invoice" novalidate>
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'invoice_number', 'label' => 'Codice fattura * ', 'col' => 5])
                            @include('backoffice.components.form.input',['name' => 'pieces', 'type' => 'number', 'label' => 'Pezzi in fattura', 'col' => 3])
                            @include('backoffice.components.form.input',['name' => 'amount', 'type' => 'number', 'label' => 'Totale fattura *', 'col' => 3])
                        </div>
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'invoice_at', 'label' => 'Data emissione fattura *', 'col' => 3])
                            @include('backoffice.components.form.input',['name' => 'paid_at', 'label' => 'Fattura saldata il', 'col' => 3])
                            <div class="col-xs-12">
                                <hr />
                            </div>
                            @include('backoffice.components.form.textarea',['name' => 'payment_note', 'label' => 'Annotazioni e appunti *', 'class' => 'summernote', 'col' => 12])
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row supplier_refunds_index ">
                        @include('backoffice.components.form.select',['form' => 'create-invoice', 'name' => 'supplier_id', 'label' => 'Fornitore * ', 'col' => 12, 'options' => Utils::map_collection($suppliers)])
                        @include('backoffice.components.form.select',['form' => 'create-invoice', 'name' => 'brand_id', 'label' => 'Brand * ', 'class' => 'brand_id', 'col' => 12, 'options' => []])
                        <div class="col-xs-12">
                            <h3 class="m-t-lg">Carica il PDF della fattura</h3>
                            <hr>
                            <div class="col-xs-12">
                                @include('backoffice.components.form.upload', ['type' => 'file', 'name' => 'filename', 'path' => 'suppliers/invoices', 'class' => 'upload-article-image', 'col' => 12])
                            </div>
                            <div class="col-xs-12 m-t-sm load-images"></div>
                        </div>
                        <div class="col-xs-12">
                            <hr />
                        </div>
                        <div class="col-xs-12 text-center m-t-sm">
                            @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-create-invoice', 'label' => 'Registra fattura'])
                            <div class="col-xs-12 object-response"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('custom-script')
    <script src="{{ asset('backoffice/js/plugins/summernote/summernote.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('suppliers.refunds.products') }}',
                    columns: [
                        {data: 'product'},
                    ],
                    dataForm: ['brand_id', 'season_id', 'manufacturer_code', 'barcode', 'has_flaw'],
                    order: [[0, 'desc']],
                    serverSide: false,
                    filterOfferProducts: true,
                }]);
                $(document).trigger('loadSwitchTrigger', [{container: '.supplier_refunds_index'}]);
            }, 500);
            $('.summernote').summernote({
                height: 150
            });
            new Litepicker({
                format: 'DD/MM/YYYY',
                element: document.getElementById('invoice_at'),
                singleMode: true,
                numberOfMonths: 1,
                numberOfColumns: 1,
                lang: 'it',
            });
            new Litepicker({
                format: 'DD/MM/YYYY',
                element: document.getElementById('paid_at'),
                singleMode: true,
                numberOfMonths: 1,
                numberOfColumns: 1,
                lang: 'it',
            });
        })
    </script>
@endsection
