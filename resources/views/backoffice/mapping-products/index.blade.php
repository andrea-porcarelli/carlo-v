@extends('backoffice.layout', ['title' => 'Mappature Prodotti'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fornitori', 'url' => route('suppliers.index')],
        'level_2' => ['label' => 'Mappature'],
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
                                @include('backoffice.components.form.select', [
                                    'label' => 'Fornitore',
                                    'name' => 'supplier_id',
                                    'col' => 2,
                                    'options' => \App\Facades\Utils::map_key($suppliers->toArray()),
                                    'class' => 'supplier_id',
                                ])
                                @include('backoffice.components.form.input', [
                                    'label' => 'Prodotto',
                                    'name' => 'search',
                                    'col' => 2,
                                    'class' => 'search',
                                    'placeholder' => 'Nome prodotto...',
                                ])
                                @include('backoffice.components.form.button', [
                                    'col' => 2,
                                    'label' => 'Cerca',
                                    'class' => 'btn-find',
                                ])
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort" style="width:70px;"></th>
                                        <th class="all">Fornitore</th>
                                        <th class="all">Prodotto in fattura</th>
                                        <th class="all">Materiale</th>
                                        <th class="all text-center">Moltiplicatore</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort" style="width:70px;"></th>
                                        <th class="all">Fornitore</th>
                                        <th class="all">Prodotto in fattura</th>
                                        <th class="all">Materiale</th>
                                        <th class="all text-center">Moltiplicatore</th>
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
                    url: '{{ route('mapping-products.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'supplier_name'},
                        {data: 'product_name'},
                        {data: 'material_label'},
                        {data: 'quantity_multiplier', class: 'text-center'},
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['supplier_id', 'search'],
                    serverSide: true,
                }]);
            }, 500);

            // ── Delete via AJAX ────────────────────────────────────────────
            $(document).on('click', '.btn-delete-mapping', function() {
                if (!confirm('Sei sicuro di voler eliminare questa mappatura? Questa azione è irreversibile.')) return;

                const id = $(this).data('id');
                const btn = $(this);

                $.ajax({
                    url: '/backoffice/mapping-products/' + id,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function (res) {
                        alert(res.message || 'Mappatura eliminata con successo');
                        $('.datatable_table').DataTable().ajax.reload();
                    },
                    error: function (xhr) {
                        alert(xhr.responseJSON?.message || "Errore nell'eliminazione della mappatura");
                    },
                });
            });
        })
    </script>
@endsection
