@extends('backoffice.layout', ['title' => 'Categorie piatti',])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Categorie piatti'],
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
                                @include('backoffice.components.form.button', ['col' => 2, 'label' => 'Cerca', 'class' => 'btn-find', 'with_add' => true, 'class_btn_add' => 'btn-add-object', 'route' => 'restaurant.categories.create'])
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Categoria</th>
                                        <th class="all">Le comande si stampano su</th>
                                        <th class="all text-center">Piatti della categoria</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Categoria</th>
                                        <th class="all">Le comande si stampano su</th>
                                        <th class="all text-center">Piatti della categoria</th>
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
                    url: '{{ route('restaurant.categories.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'id', width: '40px'},
                        {data: 'label'},
                        {data: 'printer'},
                        {data: 'dishes', class: 'text-center'},
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['mixed'],
                    serverSide: false,
                }]);
            }, 500);
        })
    </script>
@endsection
