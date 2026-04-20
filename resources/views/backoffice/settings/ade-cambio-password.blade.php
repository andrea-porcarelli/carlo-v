@extends('backoffice.layout')

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Settaggi', 'url' => route('restaurant.settings.index')],
        'level_2' => ['label' => 'Cambio password Agenzia Entrate'],
    ])
@endsection

@section('main-content')
    <form class="needs-validation update-or-create-element" id="update-or-create-element" autocomplete="off">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-key"></i> Cambio password Agenzia delle Entrate
                </h4>
            </div>
            <div class="panel-body">
                <p class="text-muted">
                    La password AdE ha una scadenza di <strong>90 giorni</strong>. Dopo il cambio, la nuova password viene salvata automaticamente nelle impostazioni.
                    @if($changedAt)
                        <br>Ultimo cambio registrato: <strong>{{ $changedAt }}</strong>
                        @if($daysSinceChange !== null)
                            ({{ $daysSinceChange }} giorni fa)
                        @endif
                    @else
                        <br>Nessun cambio password registrato.
                    @endif
                </p>
                <div class="row">
                    @include('backoffice.components.form.input', [
                        'form'  => 'update-or-create-element',
                        'name'  => 'utenza',
                        'label' => 'Utenza AdE',
                        'col'   => 4,
                        'value' => $utenza,
                        'required' => true,
                    ])
                    @include('backoffice.components.form.input', [
                        'form'  => 'update-or-create-element',
                        'name'  => 'vecchia_password',
                        'label' => 'Vecchia password',
                        'col'   => 4,
                        'type'  => 'password',
                        'required' => true,
                    ])
                </div>
                <div class="row">
                    @include('backoffice.components.form.input', [
                        'form'  => 'update-or-create-element',
                        'name'  => 'nuova_password',
                        'label' => 'Nuova password',
                        'col'   => 4,
                        'type'  => 'password',
                        'required' => true,
                        'min'   => 8,
                    ])
                    @include('backoffice.components.form.input', [
                        'form'  => 'update-or-create-element',
                        'name'  => 'conferma_password',
                        'label' => 'Conferma nuova password',
                        'col'   => 4,
                        'type'  => 'password',
                        'required' => true,
                        'min'   => 8,
                    ])
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xs-12 col-sm-3 text-center m-t-sm">
                @include('backoffice.components.form.button', [
                    'field'   => true,
                    'col'     => 12,
                    'class'   => 'btn-update-or-create-element col-xs-12',
                    'label'   => 'Cambia password',
                    'dataset' => ['route' => 'restaurant/settings/agenzia-entrate/cambio-password'],
                ])
                <div class="col-xs-12 object-response"></div>
            </div>
        </div>
    </form>
@endsection
