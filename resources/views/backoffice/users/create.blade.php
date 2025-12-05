@extends('backoffice.layout', ['title' => 'Crea Nuovo Utente'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Utenti', 'url' => route('users.index')],
        'level_2' => ['label' => 'Crea Nuovo Utente'],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <form id="userForm" class="form-ajax" action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fas fa-user-plus"></i> Informazioni Utente
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            @include('backoffice.components.form.input', [
                                'label' => 'Nome Completo',
                                'name' => 'name',
                                'col' => 4,
                                'required' => true,
                                'placeholder' => 'Es: Mario Rossi'
                            ])

                            @include('backoffice.components.form.input', [
                                'label' => 'Email',
                                'name' => 'email',
                                'col' => 4,
                                'type' => 'email',
                                'required' => true,
                                'placeholder' => 'Es: mario.rossi@example.com'
                            ])

                            @include('backoffice.components.form.select', [
                                'label' => 'Ruolo',
                                'name' => 'role',
                                'col' => 4,
                                'required' => true,
                                'options' => $roles
                            ])
                        </div>

                        <div class="row m-t-md">
                            <div class="col-lg-12">
                                <div class="alert alert-warning">
                                    <i class="fas fa-lock"></i>
                                    <strong>Password:</strong> La password deve essere di almeno 4 caratteri.
                                </div>
                            </div>

                            @include('backoffice.components.form.input', [
                                'label' => 'Password',
                                'name' => 'password',
                                'col' => 6,
                                'type' => 'password',
                                'required' => true,
                                'placeholder' => 'Inserisci la password'
                            ])

                            @include('backoffice.components.form.input', [
                                'label' => 'Conferma Password',
                                'name' => 'password_confirmation',
                                'col' => 6,
                                'type' => 'password',
                                'required' => true,
                                'placeholder' => 'Ripeti la password'
                            ])
                        </div>
                    </div>
                    <div class="panel-footer">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('users.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Annulla
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Crea Utente
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
@section('custom-script')
    <script>
        $(document).ready(function() {
            $('#userForm').on('submit', function(e) {
                e.preventDefault();

                const form = $(this);
                const submitBtn = form.find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creazione in corso...');

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        alert(response.message || 'Utente creato con successo');
                        window.location.href = '{{ route('users.index') }}';
                    },
                    error: function(xhr) {
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save"></i> Crea Utente');

                        let message = 'Errore nella creazione dell\'utente';
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            const errors = xhr.responseJSON.errors;
                            message = Object.values(errors).flat().join('\n');
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        alert(message);
                    }
                });
            });
        });
    </script>
@endsection
