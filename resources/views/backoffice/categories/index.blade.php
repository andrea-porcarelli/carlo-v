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
                        <div class="col-lg-12 mb-2">
                            <button id="btn-save-order" class="btn btn-success" style="display:none;">
                                <i class="fa fa-save"></i> Salva ordine
                            </button>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all no-sort" style="width:30px;"></th>
                                        <th class="all">#</th>
                                        <th class="all">Categoria</th>
                                        <th class="all">Le comande si stampano su</th>
                                        <th class="all text-center">Piatti della categoria</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
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
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        $(document).ready(function(){
            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('restaurant.categories.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'sort_order', orderable: false, searchable: false, width: '30px', render: function() {
                            return '<i class="fa fa-bars drag-handle" style="cursor:grab; color:#999;"></i>';
                        }},
                        {data: 'id', width: '40px'},
                        {data: 'label'},
                        {data: 'printer'},
                        {data: 'dishes', class: 'text-center'},
                    ],
                    order: [[0, 'asc']],
                    dataForm: ['mixed'],
                    serverSide: false,
                    initComplete: function() {
                        initSortable();
                    },
                    drawCallback: function() {
                        initSortable();
                    },
                }]);
            }, 500);

            function initSortable() {
                var tbody = document.querySelector('.datatable_table tbody');
                if (!tbody || tbody._sortable) return;

                tbody._sortable = Sortable.create(tbody, {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: function() {
                        $('#btn-save-order').show();
                    }
                });
            }

            $('#btn-save-order').on('click', function() {
                var order = [];
                $('.datatable_table tbody tr').each(function(index) {
                    var id = $(this).find('td').eq(2).text().trim();
                    if (id && !isNaN(id)) {
                        order.push({id: parseInt(id), sort_order: index});
                    }
                });

                $.ajax({
                    url: '{{ route('restaurant.categories.reorder') }}',
                    method: 'PUT',
                    data: JSON.stringify({order: order}),
                    contentType: 'application/json',
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function() {
                        $('#btn-save-order').hide();
                    },
                    error: function() {
                        alert('Errore durante il salvataggio dell\'ordine');
                    }
                });
            });
        })
    </script>
@endsection
