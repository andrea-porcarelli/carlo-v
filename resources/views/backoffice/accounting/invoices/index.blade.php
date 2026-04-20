@extends('backoffice.layout', ['title' => 'Contabilità'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Contabilità'],
        'level_2' => ['label' => 'Fatture'],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row advanced-search">
                        @include('backoffice.components.form.input', [
                            'label' => 'Codice fattura',
                            'name'  => 'invoice_code',
                            'col'   => 3,
                            'class' => 'invoice_code',
                        ])
                        <div class="col-xs-12 col-sm-3 m-t-sm">
                            <label>Cliente</label>
                            <select name="customer_id" class="form-control customer_id">
                                <option value="">Tutti</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xs-12 col-sm-3 m-t-sm">
                            <label>Stato</label>
                            <select name="status" class="form-control status">
                                <option value="">Tutti</option>
                                <option value="pending">In coda</option>
                                <option value="sent">Inviata</option>
                                <option value="error">Errore</option>
                            </select>
                        </div>
                        <div class="col-xs-12 col-sm-3 m-t-sm" style="display:flex; align-items:flex-end;">
                            <button type="button" class="btn btn-info btn-find">Cerca</button>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-lg-12">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort">Azioni</th>
                                        <th class="all">Codice</th>
                                        <th class="all">Cliente</th>
                                        <th class="all">Importo</th>
                                        <th class="all">Stato</th>
                                        <th class="all">Creata</th>
                                        <th class="all">Inviata</th>
                                        <th class="all">Risposta MySond</th>
                                    </tr>
                                    </thead>
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
            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('accounting.invoices.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '90px'},
                        {data: 'code'},
                        {data: 'customer_name'},
                        {data: 'amount_fmt', class: 'text-end'},
                        {data: 'status_badge', class: 'text-center'},
                        {data: 'created_fmt'},
                        {data: 'sent_fmt'},
                        {data: 'mysond_desc'},
                    ],
                    order: [[5, 'desc']],
                    dataForm: ['invoice_code', 'customer_id', 'status'],
                    serverSide: true,
                }]);
            }, 300);

            $(document).on('click', '.btn-resend-invoice', function () {
                const id = $(this).data('id');
                if (!confirm('Re-inviare la fattura #' + id + ' a MySond?')) return;
                $.ajax({
                    url: '/backoffice/accounting/invoices/' + id + '/resend',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function () {
                        App.sweet('Fattura re-accodata per invio.', 'Operazione effettuata');
                        $('.datatable_table').DataTable().ajax.reload(null, false);
                    },
                    error: function (xhr) {
                        const r = xhr.responseJSON || {};
                        App.sweet(r.message || 'Errore', 'Errore');
                    }
                });
            });
        });
    </script>
@endsection
