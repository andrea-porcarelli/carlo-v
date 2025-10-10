@extends('backoffice.layout', ['title' => 'Allergeni',])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Allergeni'],
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
                                @include('backoffice.components.form.input', ['label' => 'Allergene', 'name' => 'mixed', 'col' => 2, 'class' => 'mixed'])
                                @include('backoffice.components.form.button', ['col' => 2, 'label' => 'Cerca', 'class' => 'btn-find', 'with_add' => true, 'class_btn_add' => 'btn-add-object', 'route' => 'restaurant.allergens.create'])
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Allergene</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Allergene</th>
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
                    url: '{{ route('restaurant.allergens.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'id', width: '40px'},
                        {data: 'label'},
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['mixed'],
                    serverSide: false,
                }]);
            }, 500);
        })
    </script>
@endsection
