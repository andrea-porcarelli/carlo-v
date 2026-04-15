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

                        <!-- Sezione Admin -->
                        <div class="row m-t-md" id="adminSection" style="display:none;">
                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-lock"></i>
                                    <strong>Password Backoffice:</strong> La password deve essere di almeno 6 caratteri.
                                </div>
                            </div>

                            @include('backoffice.components.form.input', [
                                'label' => 'Password Backoffice',
                                'name' => 'backoffice_password',
                                'col' => 6,
                                'type' => 'password',
                                'required' => true,
                                'placeholder' => 'Inserisci la password'
                            ])

                            @include('backoffice.components.form.input', [
                                'label' => 'Conferma Password Backoffice',
                                'name' => 'backoffice_password_confirmation',
                                'col' => 6,
                                'type' => 'password',
                                'required' => true,
                                'placeholder' => 'Ripeti la password'
                            ])

                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-key"></i>
                                    <strong>PIN di Autenticazione:</strong> Codice numerico univoco, da 1 a 5 cifre, per l'autenticazione operatore.
                                </div>
                            </div>

                            @include('backoffice.components.form.input', [
                                'label' => 'PIN di Autenticazione',
                                'name' => 'authentication_pin',
                                'col' => 6,
                                'type' => 'text',
                                'inputmode' => 'numeric',
                                'required' => true,
                                'placeholder' => 'Es: 12345',
                                'pattern' => '[0-9]{1,5}'
                            ])
                        </div>

                        <!-- Sezione Operator -->
                        <div class="row m-t-md" id="operatorSection" style="display:none;">
                            <div class="col-lg-12">
                                <div class="alert alert-warning">
                                    <i class="fas fa-lock"></i>
                                    <strong>Password (PIN):</strong> Codice numerico, da 1 a 5 cifre.
                                </div>
                            </div>

                            @include('backoffice.components.form.input', [
                                'label' => 'Password (PIN)',
                                'name' => 'password',
                                'col' => 6,
                                'type' => 'password',
                                'inputmode' => 'numeric',
                                'required' => true,
                                'placeholder' => 'Inserisci la password',
                                'pattern' => '[0-9]{1,5}'
                            ])

                            @include('backoffice.components.form.input', [
                                'label' => 'Conferma Password',
                                'name' => 'password_confirmation',
                                'col' => 6,
                                'type' => 'password',
                                'inputmode' => 'numeric',
                                'required' => true,
                                'placeholder' => 'Ripeti la password',
                                'pattern' => '[0-9]{1,5}'
                            ])
                        </div>

                        <div class="row m-t-md" id="permissionsSection" style="display:none;">
                            <div class="col-lg-12">
                                <hr />
                                <h5><i class="fas fa-shield-alt"></i> Permessi Operatore</h5>
                                <p class="text-muted">Seleziona le funzionalità a cui questo operatore potrà accedere.</p>
                            </div>
                            @foreach($availablePermissions as $key => $label)
                                <div class="col-xs-12 col-sm-4 m-t-sm">
                                    <label>{{ $label }}</label><br />
                                    <input type="checkbox" class="js-switch" name="permissions[]" value="{{ $key }}" />
                                    <div class="invalid-feedback"></div>
                                </div>
                            @endforeach
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
            // Show/hide sections based on role
            function toggleSections() {
                const role = $('#role').val();
                if (role === 'admin') {
                    $('#adminSection').show();
                    $('#operatorSection').hide();
                    $('#permissionsSection').hide();
                } else {
                    $('#adminSection').hide();
                    $('#operatorSection').show();
                    $('#permissionsSection').show();
                }
            }
            toggleSections();
            $('#role').on('change', toggleSections);

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
