@extends('backoffice.layout', ['title' => 'Fatture'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture'],
    ])
@endsection
@section('main-content')
    @if(isset($failedInvoices) && $failedInvoices->isNotEmpty())
    <div class="row" id="failed-invoices-alert">
        <div class="col-lg-12">
            <div class="alert alert-warning alert-dismissible">
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                <h4><i class="fa fa-exclamation-triangle"></i> Fatture non importate automaticamente ({{ $failedInvoices->count() }})</h4>
                <p>Le seguenti fatture ricevute da Mysond non sono state importate e richiedono attenzione manuale:</p>
                <table class="table table-condensed table-bordered" style="margin-bottom:0; background:rgba(255,255,255,0.5);">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Errore</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($failedInvoices as $fi)
                        <tr>
                            <td><small>{{ $fi->file_name }}</small></td>
                            <td><small class="text-danger">{{ \Illuminate\Support\Str::limit($fi->import_error, 100) }}</small></td>
                            <td class="text-center" style="white-space:nowrap;">
                                @if($fi->file_path)
                                <a href="{{ route('invoices.download-failed-file', $fi->id) }}" class="btn btn-xs btn-default" title="Scarica file originale">
                                    <i class="fa fa-download"></i>
                                </a>
                                @endif
                                <button class="btn btn-xs btn-warning btn-ignore-failed" data-id="{{ $fi->id }}" data-url="{{ route('invoices.ignore-failed', $fi->id) }}" title="Ignora">
                                    <i class="fa fa-eye-slash"></i> Ignora
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
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
                                @include('backoffice.components.form.select', [
                                    'label' => 'Mappatura',
                                    'name' => 'mapping',
                                    'col' => 2,
                                    'class' => 'mapping',
                                    'hide_first' => true,
                                    'first_value_text' => 'Tutte',
                                    'value' => 'da_effettuare',
                                    'options' => [
                                        ['id' => 'da_effettuare', 'label' => 'Da effettuare'],
                                        ['id' => 'effettuata',    'label' => 'Effettuata'],
                                    ],
                                ])
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
                                        <th class="all">Stato mappatura</th>
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
                                        <th class="all">Stato mappatura</th>
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
    </style>
@endsection
@section('custom-script')
    <script>
        $(document).on('click', '.btn-ignore-failed', function() {
            var btn = $(this);
            var url = btn.data('url');
            if (!confirm('Ignorare questa fattura? Non sarà più visibile nella lista degli errori.')) return;
            $.ajax({
                url: url,
                type: 'PATCH',
                data: { _token: '{{ csrf_token() }}' },
                success: function() {
                    var row = btn.closest('tr');
                    row.fadeOut(300, function() {
                        row.remove();
                        var tbody = $('#failed-invoices-alert tbody');
                        if (tbody.find('tr').length === 0) {
                            $('#failed-invoices-alert').fadeOut(300);
                        }
                    });
                },
                error: function() {
                    alert('Errore durante l\'operazione.');
                }
            });
        });

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
                    dataForm: ['invoice_number', 'supplier_id', 'date_from', 'date_to', 'mapping'],
                    serverSide: false,
                }]);
            }, 500);
        })
    </script>
@endsection
