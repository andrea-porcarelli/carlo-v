@extends('backoffice.layout', ['title' => 'Modifica Utente'])

@php
$permissionMeta = [
    'take_orders'  => ['icon' => 'fa-clipboard-list', 'color' => '#1c84c6', 'desc' => 'Può aprire nuovi tavoli e aggiungere piatti alle comande'],
    'view_orders'  => ['icon' => 'fa-eye',            'color' => '#1ab394', 'desc' => 'Può consultare le comande attive sui tavoli'],
    'cash_payment' => ['icon' => 'fa-coins',          'color' => '#f8ac59', 'desc' => 'Può incassare pagamenti in contanti e aprire il cassetto'],
    'pos_payment'  => ['icon' => 'fa-credit-card',    'color' => '#23c6c8', 'desc' => 'Può processare pagamenti tramite terminale POS'],
    'invoice_payment' => ['icon' => 'fa-file-invoice',  'color' => '#6f42c1', 'desc' => 'Può emettere fatture durante il pagamento'],
];
@endphp

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Utenti', 'url' => route('users.index')],
        'level_2' => ['label' => 'Modifica Utente: ' . $_user->name],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <form id="userForm" action="{{ route('users.edit', $_user->id) }}" method="POST" novalidate>
                @csrf
                @method('PUT')

                {{-- ── Sezione comune (nome e ruolo) ──────────────────────────────── --}}
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fas fa-user-edit"></i> Informazioni Utente
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            @include('backoffice.components.form.input', [
                                'label'       => 'Nome',
                                'name'        => 'name',
                                'col'         => 6,
                                'required'    => true,
                                'value'       => $_user->name,
                                'placeholder' => 'Es: Mario Rossi',
                            ])
                            @include('backoffice.components.form.select', [
                                'label'    => 'Ruolo',
                                'name'     => 'role',
                                'col'      => 6,
                                'required' => true,
                                'options'  => $roles,
                                'value'    => $_user->role,
                            ])
                        </div>
                    </div>
                </div>

                {{-- ── Sezione Admin ───────────────────────────────────────────────── --}}
                <div class="panel panel-default" id="adminSection"
                     style="{{ $_user->role === 'admin' ? '' : 'display:none;' }}">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fas fa-lock"></i> Credenziali Backoffice
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            @include('backoffice.components.form.input', [
                                'label'       => 'Email',
                                'name'        => 'email',
                                'col'         => 6,
                                'type'        => 'email',
                                'required'    => true,
                                'value'       => $_user->email,
                                'placeholder' => 'Es: admin@example.com',
                            ])
                        </div>

                        <div class="row m-t-md">
                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-key"></i>
                                    <strong>Password Backoffice:</strong> Lascia i campi vuoti se non vuoi modificare la password.
                                </div>
                            </div>
                            @include('backoffice.components.form.input', [
                                'label'       => 'Password Backoffice',
                                'name'        => 'backoffice_password',
                                'col'         => 6,
                                'type'        => 'password',
                                'placeholder' => 'Lascia vuoto per non modificare',
                            ])
                            @include('backoffice.components.form.input', [
                                'label'       => 'Conferma Password',
                                'name'        => 'backoffice_password_confirmation',
                                'col'         => 6,
                                'type'        => 'password',
                                'placeholder' => 'Ripeti la password',
                            ])
                        </div>

                        <div class="row m-t-md">
                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-key"></i>
                                    <strong>PIN di Autenticazione:</strong> Codice numerico univoco, da 1 a 5 cifre. Lascia vuoto per non modificare.
                                </div>
                            </div>
                            @include('backoffice.components.form.input', [
                                'label'       => 'PIN di Autenticazione',
                                'name'        => 'authentication_pin',
                                'col'         => 6,
                                'type'        => 'text',
                                'inputmode'   => 'numeric',
                                'value'       => $_user->authentication_pin,
                                'placeholder' => 'Es: 12345',
                                'pattern'     => '[0-9]{1,5}',
                            ])
                        </div>

                        <div class="row m-t-md">
                            <div class="col-lg-12">
                                <div class="alert alert-secondary">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Info Account:</strong>
                                    <ul class="mb-0 m-t-xs">
                                        <li>Creato il: <strong>{{ $_user->created_at->format('d/m/Y H:i') }}</strong></li>
                                        <li>Ultimo aggiornamento: <strong>{{ $_user->updated_at->format('d/m/Y H:i') }}</strong></li>
                                        @if($_user->email_verified_at)
                                            <li>Email verificata il: <strong>{{ $_user->email_verified_at->format('d/m/Y H:i') }}</strong></li>
                                        @else
                                            <li class="text-warning">Email non ancora verificata</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Sezione Operatore ───────────────────────────────────────────── --}}
                <div class="panel panel-default" id="operatorSection"
                     style="{{ $_user->role === 'operator' ? '' : 'display:none;' }}">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fas fa-key"></i> Impostazioni Operatore
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Password (PIN):</strong> Numerica, max 5 cifre. Lascia vuoto per non modificare.
                                </div>
                            </div>
                            @include('backoffice.components.form.input', [
                                'label'       => 'Password (PIN)',
                                'name'        => 'password',
                                'col'         => 6,
                                'type'        => 'password',
                                'inputmode'   => 'numeric',
                                'placeholder' => 'Es: 12345',
                            ])
                        </div>
                    </div>
                </div>

                {{-- ── Pannello permessi (solo operatori) ──────────────────────────── --}}
                <div class="panel panel-default" id="permissionsPanel"
                     style="{{ $_user->role === 'operator' ? '' : 'display:none;' }}">

                    <div class="panel-heading"
                         style="display:flex; align-items:center; justify-content:space-between; padding:12px 20px;">
                        <h4 class="panel-title" style="margin:0; font-size:14px;">
                            <i class="fas fa-shield-alt"></i>&ensp;Permessi Operatore
                        </h4>
                        <div style="font-size:12px; line-height:1;">
                            <a href="#" id="selectAllPerms" class="text-navy"
                               style="font-weight:600; text-decoration:none;">
                                <i class="fas fa-check-double"></i> Seleziona tutti
                            </a>
                            <span style="color:#ccc; margin:0 8px;">|</span>
                            <a href="#" id="deselectAllPerms" class="text-muted"
                               style="text-decoration:none;">
                                <i class="fas fa-times"></i> Deseleziona tutti
                            </a>
                        </div>
                    </div>

                    <div class="panel-body" style="padding:0;">
                        @foreach($availablePermissions as $key => $label)
                            @php
                                $meta    = $permissionMeta[$key] ?? ['icon' => 'fa-lock', 'color' => '#aaa', 'desc' => ''];
                                $checked = in_array($key, $_user->permissions ?? []);
                            @endphp
                            <div class="perm-row"
                                 style="display:flex; align-items:center; padding:15px 20px;
                                        {{ $loop->last ? '' : 'border-bottom:1px solid #f4f4f4;' }}
                                        cursor:default; transition:background .12s;"
                                 onmouseover="this.style.background='#fafcff'"
                                 onmouseout="this.style.background=''">

                                {{-- Icona colorata --}}
                                <div style="width:44px; height:44px; border-radius:10px;
                                            background:{{ $meta['color'] }}; flex-shrink:0;
                                            display:flex; align-items:center; justify-content:center;
                                            margin-right:18px;
                                            box-shadow:0 2px 8px {{ $meta['color'] }}55;">
                                    <i class="fas {{ $meta['icon'] }}" style="color:#fff; font-size:18px;"></i>
                                </div>

                                {{-- Label + descrizione --}}
                                <div style="flex:1; min-width:0;">
                                    <div style="font-weight:600; font-size:14px; color:#2c3e50; line-height:1.3;">
                                        {{ $label }}
                                    </div>
                                    <div style="font-size:12px; color:#a0a0a0; margin-top:3px; line-height:1.4;">
                                        {{ $meta['desc'] }}
                                    </div>
                                </div>

                                {{-- Toggle switch --}}
                                <div style="margin-left:24px; flex-shrink:0;">
                                    <input type="checkbox"
                                           class="js-switch perm-switch"
                                           name="permissions[]"
                                           value="{{ $key }}"
                                           {{ $checked ? 'checked' : '' }} />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="panel-footer"
                         style="background:#f9f9f9; padding:10px 20px; border-top:1px solid #eee;">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Gli <strong>Amministratori</strong> hanno accesso completo a tutte le funzionalità, indipendentemente dai permessi degli operatori.
                        </small>
                    </div>
                </div>

                {{-- ── Footer (comune) ──────────────────────────────────────────────── --}}
                <div class="panel panel-default">
                    <div class="panel-footer">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('users.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Torna alla lista
                            </a>
                            <div>
                                @if(auth()->id() !== $_user->id)
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
$(document).ready(function () {

    // ── Inizializza Switchery ────────────────────────────────────────────────
    var switchInstances = [];
    document.querySelectorAll('#permissionsPanel .js-switch').forEach(function (el) {
        switchInstances.push(new Switchery(el, { color: '#1ab394', size: 'small' }));
    });

    // ── Mostra / nascondi sezioni ─────────────────────────────────────────────
    $('#role').on('change', function () {
        var selectedRole = $(this).val();

        if (selectedRole === 'admin') {
            $('#adminSection').slideDown(200);
            $('#operatorSection').slideUp(200);
            $('#permissionsPanel').slideUp(200);
        } else if (selectedRole === 'operator') {
            $('#adminSection').slideUp(200);
            $('#operatorSection').slideDown(200);
            $('#permissionsPanel').slideDown(200);
        }
    });

    // ── Seleziona tutti ───────────────────────────────────────────────────────
    $('#selectAllPerms').on('click', function (e) {
        e.preventDefault();
        document.querySelectorAll('#permissionsPanel .js-switch').forEach(function (el, i) {
            if (!el.checked) switchInstances[i].setPosition(false);
        });
    });

    // ── Deseleziona tutti ─────────────────────────────────────────────────────
    $('#deselectAllPerms').on('click', function (e) {
        e.preventDefault();
        document.querySelectorAll('#permissionsPanel .js-switch').forEach(function (el, i) {
            if (el.checked) switchInstances[i].setPosition(true);
        });
    });

    // ── Submit ────────────────────────────────────────────────────────────────
    $('#userForm').on('submit', function (e) {
        e.preventDefault();
        const form   = $(this);
        const submit = form.find('button[type="submit"]');
        submit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvataggio...');

        $.ajax({
            url:  form.attr('action'),
            type: 'PUT',
            data: form.serialize(),
            success: function (res) {
                alert(res.message || 'Utente aggiornato con successo');
                window.location.href = '{{ route('users.index') }}';
            },
            error: function (xhr) {
                submit.prop('disabled', false).html('<i class="fas fa-save"></i> Salva Modifiche');
                let msg = "Errore nell'aggiornamento dell'utente";
                if (xhr.responseJSON?.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                } else if (xhr.responseJSON?.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            },
        });
    });

    // ── Elimina ───────────────────────────────────────────────────────────────
    $('#deleteUserBtn').on('click', function () {
        if (!confirm('Sei sicuro di voler eliminare questo utente? Questa azione è irreversibile.')) return;
        $.ajax({
            url:     '/backoffice/users/{{ $_user->id }}',
            type:    'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                alert(res.message || 'Utente eliminato con successo');
                window.location.href = '{{ route('users.index') }}';
            },
            error: function (xhr) {
                alert(xhr.responseJSON?.message || "Errore nell'eliminazione dell'utente");
            },
        });
    });

});
</script>
@endsection
