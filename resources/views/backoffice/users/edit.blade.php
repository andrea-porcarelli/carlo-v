@extends('backoffice.layout', ['title' => 'Modifica Utente'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Utenti', 'url' => route('users.index')],
        'level_2' => ['label' => 'Modifica Utente: ' . $user->name],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <form id="userForm" class="form-ajax" action="{{ route('users.edit', $user->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fas fa-user-edit"></i> Informazioni Utente
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            @include('backoffice.components.form.input', [
                                'label' => 'Nome Completo',
                                'name' => 'name',
                                'col' => 6,
                                'required' => true,
                                'value' => $user->name,
                                'placeholder' => 'Es: Mario Rossi'
                            ])

                            @include('backoffice.components.form.input', [
                                'label' => 'Email',
                                'name' => 'email',
                                'col' => 6,
                                'type' => 'email',
                                'required' => true,
                                'value' => $user->email,
                                'placeholder' => 'Es: mario.rossi@example.com'
                            ])

                            @include('backoffice.components.form.select', [
                                'label' => 'Ruolo',
                                'name' => 'role',
                                'col' => 6,
                                'required' => true,
                                'options' => $roles,
                                'value' => $user->role
                            ])
                        </div>

                        <div class="row mt-4">
                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-key"></i>
                                    <strong>Cambio Password:</strong> Lascia i campi vuoti se non vuoi modificare la password.
                                </div>
                            </div>

                            @include('backoffice.components.form.input', [
                                'label' => 'Nuova Password',
                                'name' => 'password',
                                'col' => 6,
                                'type' => 'password',
                                'required' => false,
                                'placeholder' => 'Lascia vuoto per non modificare'
                            ])

                            @include('backoffice.components.form.input', [
                                'label' => 'Conferma Nuova Password',
                                'name' => 'password_confirmation',
                                'col' => 6,
                                'type' => 'password',
                                'required' => false,
                                'placeholder' => 'Ripeti la nuova password'
                            ])
                        </div>

                        <div class="row mt-3">
                            <div class="col-lg-12">
                                <div class="alert alert-secondary">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Info Account:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Utente creato il: <strong>{{ $user->created_at->format('d/m/Y H:i') }}</strong></li>
                                        <li>Ultimo aggiornamento: <strong>{{ $user->updated_at->format('d/m/Y H:i') }}</strong></li>
                                        @if($user->email_verified_at)
                                            <li>Email verificata il: <strong>{{ $user->email_verified_at->format('d/m/Y H:i') }}</strong></li>
                                        @else
                                            <li class="text-warning">Email non ancora verificata</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('users.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Torna alla lista
                            </a>
                            <div>
                                @if(auth()->id() !== $user->id)
                                    <button type="button" class="btn btn-danger" id="deleteUserBtn">
                                        <i class="fas fa-trash"></i> Elimina Utente
                                    </button>
                                @endif
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salva Modifiche
                                </button>
                            </div>
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
            // Handle form submission
            $('#userForm').on('submit', function(e) {
                e.preventDefault();

                const form = $(this);
                const submitBtn = form.find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvataggio in corso...');

                $.ajax({
                    url: form.attr('action'),
                    type: 'PUT',
                    data: form.serialize(),
                    success: function(response) {
                        alert(response.message || 'Utente aggiornato con successo');
                        window.location.href = '{{ route('users.index') }}';
                    },
                    error: function(xhr) {
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save"></i> Salva Modifiche');

                        let message = 'Errore nell\'aggiornamento dell\'utente';
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

            // Handle delete
            $('#deleteUserBtn').on('click', function() {
                if (confirm('Sei sicuro di voler eliminare questo utente? Questa azione è irreversibile.')) {
                    $.ajax({
                        url: '/backoffice/users/{{ $user->id }}',
                        type: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            alert(response.message || 'Utente eliminato con successo');
                            window.location.href = '{{ route('users.index') }}';
                        },
                        error: function(xhr) {
                            const message = xhr.responseJSON?.message || 'Errore nell\'eliminazione dell\'utente';
                            alert(message);
                        }
                    });
                }
            });
        });
    </script>
@endsection
