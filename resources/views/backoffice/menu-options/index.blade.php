@extends('backoffice.layout', ['title' => 'Extra e Rimozioni'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Extra e Rimozioni'],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <ul class="nav nav-tabs" id="menu-options-tabs">
                        <li class="{{ $type === 'extra' ? 'active' : '' }}">
                            <a href="{{ route('restaurant.menu-options.index') }}?type=extra">Supplementi (Extra)</a>
                        </li>
                        <li class="{{ $type === 'removal' ? 'active' : '' }}">
                            <a href="{{ route('restaurant.menu-options.index') }}?type=removal">Rimozioni</a>
                        </li>
                    </ul>
                    <div class="tab-content" style="padding-top:20px;">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="row g-1 advanced-search">
                                    <input type="hidden" class="type" value="{{ $type }}">
                                    @include('backoffice.components.form.input', ['label' => 'Cerca', 'name' => 'mixed', 'col' => 2, 'class' => 'mixed'])
                                    @include('backoffice.components.form.button', [
                                        'col' => 2,
                                        'label' => 'Cerca',
                                        'class' => 'btn-find',
                                        'with_add' => true,
                                        'class_btn_add' => 'btn-add-object',
                                        'route' => 'restaurant.menu-options.create',
                                        'route_params' => ['type' => $type],
                                    ])
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="table-responsive table-responsive-amazon amazon-table">
                                    <table class="table table-striped table-bordered table-hover datatable_table">
                                        <thead>
                                        <tr>
                                            <th class="all no-sort"></th>
                                            <th class="all">#</th>
                                            <th class="all">Label</th>
                                            @if($type === 'extra')
                                                <th class="all">Prezzo</th>
                                            @endif
                                            <th class="all">Attivo</th>
                                            <th class="all">Ordine</th>
                                        </tr>
                                        </thead>
                                        <tfoot>
                                        <tr>
                                            <th class="all no-sort"></th>
                                            <th class="all">#</th>
                                            <th class="all">Label</th>
                                            @if($type === 'extra')
                                                <th class="all">Prezzo</th>
                                            @endif
                                            <th class="all">Attivo</th>
                                            <th class="all">Ordine</th>
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
    </div>
@endsection
@section('custom-script')
    <script>
        $(document).ready(function(){
            const type = '{{ $type }}';
            const columns = [
                {data: 'action', orderable: false, searchable: false, width: '70px'},
                {data: 'id', width: '40px'},
                {data: 'label'},
            ];
            if (type === 'extra') {
                columns.push({data: 'price'});
            }
            columns.push({data: 'is_active'});
            columns.push({data: 'sort_order'});

            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('restaurant.menu-options.datatable') }}',
                    columns: columns,
                    order: [[5, 'asc']],
                    dataForm: ['mixed', 'type'],
                    serverSide: false,
                }]);
            }, 500);
        })
    </script>
@endsection
