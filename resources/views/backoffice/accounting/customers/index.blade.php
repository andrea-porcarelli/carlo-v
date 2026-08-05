@extends('backoffice.layout', ['title' => 'Clienti'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Contabilità'],
        'level_2' => ['label' => 'Clienti'],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row advanced-search">
                        @include('backoffice.components.form.input', [
                            'label' => 'Cerca (nome, CF, P.IVA)',
                            'name'  => 'search',
                            'col'   => 4,
                            'class' => 'search',
                        ])
                        <div class="col-xs-12 col-sm-3 m-t-sm">
                            <label>Tipologia</label>
                            <select name="user_type" class="form-control user_type">
                                <option value="">Tutti</option>
                                <option value="private">Privato</option>
                                <option value="company">Azienda</option>
                                <option value="sole_trader">Ditta individuale / Libero prof.</option>
                                <option value="non_profit_entity">Ente Non Commerciale</option>
                                <option value="public_company">PA</option>
                                <option value="foreign">Soggetto Estero</option>
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
                                        <th class="all">Nome</th>
                                        <th class="all">Tipologia</th>
                                        <th class="all">Identificativi</th>
                                        <th class="all">Indirizzo</th>
                                        <th class="all">Destinatario</th>
                                        <th class="all text-center">N° fatture</th>
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
                    url: '{{ route('accounting.customers.datatable') }}',
                    columns: [
                        {data: 'full_name'},
                        {data: 'type_label', class: 'text-center', width: '100px'},
                        {data: 'identifier'},
                        {data: 'address_full'},
                        {data: 'destinatario'},
                        {data: 'invoices_count', class: 'text-center', width: '100px'},
                    ],
                    order: [[0, 'asc']],
                    dataForm: ['search', 'user_type'],
                    serverSide: true,
                }]);
            }, 300);
        });
    </script>
@endsection
