@extends('backoffice.layout', ['title' => 'Utenti',])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Utenti'],
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
                                @include('backoffice.components.form.input', [
                                    'label' => 'Nome o Email',
                                    'name' => 'search',
                                    'col' => 3,
                                    'class' => 'search'
                                ])
                                @include('backoffice.components.form.select', [
                                    'label' => 'Ruolo',
                                    'name' => 'role',
                                    'col' => 2,
                                    'class' => 'role',
                                    'options' => Utils::key_value([
                                        '' => 'Tutti',
                                        'admin' => 'Amministratore',
                                        'operator' => 'Operatore'
                                    ])
                                ])
                                @include('backoffice.components.form.button', [
                                    'col' => 2,
                                    'label' => 'Cerca',
                                    'class' => 'btn-find',
                                    'with_add' => true,
                                    'class_btn_add' => 'btn-add-object',
                                    'route' => 'users.create'
                                ])
                            </div>
                        </div>
                        <div class="col-lg-12 m-t-md">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Info:</strong> Da questa sezione puoi gestire gli utenti del sistema.
                                Gli utenti con ruolo <strong>Amministratore</strong> hanno accesso completo,
                                mentre gli <strong>Operatori</strong> hanno accesso limitato alle funzionalità.
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="table-responsive table-responsive-amazon amazon-table">
                                <table class="table table-striped table-bordered table-hover datatable_table">
                                    <thead>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Utente</th>
                                        <th class="all">Ruolo</th>
                                        <th class="all">Data Creazione</th>
                                        <th class="all no-sort">Azioni</th>
                                    </tr>
                                    </thead>
                                    <tfoot>
                                    <tr>
                                        <th class="all no-sort"></th>
                                        <th class="all">#</th>
                                        <th class="all">Utente</th>
                                        <th class="all">Ruolo</th>
                                        <th class="all">Data Creazione</th>
                                        <th class="all no-sort">Azioni</th>
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
            let dataTable;

            setTimeout(() => {
                $(document).trigger('datatable', [{
                    url: '{{ route('users.datatable') }}',
                    columns: [
                        {data: 'action', orderable: false, searchable: false, width: '70px'},
                        {data: 'id', width: '40px'},
                        {data: 'user_info'},
                        {data: 'role_label', class: 'text-center'},
                        {data: 'created', class: 'text-center'},
                        {data: 'actions_custom', orderable: false, searchable: false, class: 'text-center'},
                    ],
                    order: [[1, 'desc']],
                    dataForm: ['search', 'role'],
                    serverSide: true,
                }]);

                dataTable = $('.datatable_table').DataTable();
            }, 500);

            // Handle delete user
            $(document).on('click', '.btn-delete-user', function(e) {
                e.preventDefault();
                const userId = $(this).data('id');

                if (confirm('Sei sicuro di voler eliminare questo utente?')) {
                    $.ajax({
                        url: '/backoffice/users/' + userId,
                        type: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            alert(response.message || 'Utente eliminato con successo');
                            dataTable.ajax.reload();
                        },
                        error: function(xhr) {
                            const message = xhr.responseJSON?.message || 'Errore nell\'eliminazione dell\'utente';
                            alert(message);
                        }
                    });
                }
            });
        })
    </script>
@endsection
