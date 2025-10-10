@extends('backoffice.layout', ['title' => 'Piatti del menu',])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Piatti del menu'],
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
                                @include('backoffice.components.form.button', ['col' => 2, 'label' => 'Cerca', 'class' => 'btn-find', 'with_add' => true, 'class_btn_add' => 'btn-add-object', 'route' => 'restaurant.dishes.create'])
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Nome del piatto</th>
                                        <th class="all">Ingredienti</th>
                                        <th class="all">Prezzo</th>
                                        <th class="all">Allergeni</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Nome del piatto</th>
                                        <th class="all">Ingredienti</th>
                                        <th class="all">Prezzo</th>
                                        <th class="all">Allergeni</th>
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
            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('restaurant.dishes.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'id', width: '40px'},
                        {data: 'dish'},
                        {data: 'ingredients'},
                        {data: 'price', class: 'text-center'},
                        {data: 'allergens',},
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['mixed'],
                    serverSide: false,
                }]);
            }, 500);
        })
    </script>
@endsection
