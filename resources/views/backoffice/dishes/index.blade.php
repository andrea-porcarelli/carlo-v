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
                                @include('backoffice.components.form.input', ['label' => 'Piatto', 'name' => 'dish', 'col' => 2, 'class' => 'dish'])
                                @include('backoffice.components.form.select', ['label' => 'Categoria', 'name' => 'category_id', 'col' => 2, 'class' => 'category_id', 'options' => $categories, 'first_value_text' => 'Tutte le categorie', 'hide_first' => true])
                                @include('backoffice.components.form.select', ['label' => 'Ingrediente', 'name' => 'material_id', 'col' => 2, 'class' => 'material_id', 'options' => $materials, 'first_value_text' => 'Tutti gli ingredienti', 'hide_first' => true])
                                @include('backoffice.components.form.select', ['label' => 'Allergene', 'name' => 'allergen_id', 'col' => 2, 'class' => 'allergen_id', 'options' => $allergens, 'first_value_text' => 'Tutti gli allergeni', 'hide_first' => true])
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
                                        <th class="all">Piatto</th>
                                        <th class="all">Ingredienti</th>
                                        <th class="all text-center">Prezzo</th>
                                        <th class="all">Allergeni</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Piatto</th>
                                        <th class="all">Ingredienti</th>
                                        <th class="all text-center">Prezzo</th>
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
    <style>
        /* Colonna ingredienti: larghezza fissa, scroll interno */
        #dishes-table td:nth-child(4),
        .datatable_table td:nth-child(4) {
            max-width: 260px;
        }

        /* Badge allergeni */
        .allergen-badge {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            margin: 1px 2px 1px 0;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }

        /* Evidenziazione stock 0 */
        .stock-zero-row {
            background: #fff8f8 !important;
        }
    </style>
    <script>
        $(document).ready(function(){
            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('restaurant.dishes.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'id', width: '40px'},
                        {data: 'dish', width: '180px'},
                        {data: 'ingredients'},
                        {data: 'price', className: 'text-center', width: '80px'},
                        {
                            data: 'allergens',
                            width: '200px',
                            render: function(data) {
                                if (!data || data.trim() === '') {
                                    return '<span class="text-muted" style="font-size:11px;font-style:italic;">Nessuno</span>';
                                }
                                return data.split(',').map(function(a) {
                                    return '<span class="allergen-badge">' + a.trim() + '</span>';
                                }).join('');
                            }
                        },
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['dish', 'category_id', 'material_id', 'allergen_id'],
                    serverSide: false,
                    createdRow: function(row, data) {
                        // Evidenzia la riga se la colonna ingredienti contiene "stock 0"
                        if (data.ingredients && data.ingredients.indexOf('stock 0') !== -1) {
                            $(row).addClass('stock-zero-row');
                        }
                    },
                }]);
            }, 500);
        })
    </script>
@endsection
