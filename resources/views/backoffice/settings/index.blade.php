@extends('backoffice.layout')

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
       'level_1' => ['label' => 'Settaggi'],
   ])
@endsection

@section('main-content')
    @php
        $rendered = [];
        $posIntegrationOptions = [
            ['id' => 'none',    'label' => 'Scollegato'],
            ['id' => 'revolut', 'label' => 'Revolut Terminal'],
        ];
        $revolutEnvOptions = [
            ['id' => 'sandbox',    'label' => 'Sandbox (test)'],
            ['id' => 'production', 'label' => 'Produzione'],
        ];
        $cashDrawerOptions = [
            ['id' => 'none',    'label' => 'Scollegato'],
            ['id' => 'printer', 'label' => 'Stampante (ESC/POS)'],
        ];
        $corrispettivoProviderOptions = [
            ['id' => 'mysond', 'label' => 'Mysond (SOAP/SdI)'],
            ['id' => 'ditron', 'label' => 'Ditron (agent + cassa RT)'],
        ];
        $providerBlocks = [
            'Configurazione Mysond' => 'mysond',
            'Configurazione Ditron' => 'ditron',
        ];
        $currentProvider = (string) \App\Models\Setting::get('corrispettivo_provider', 'mysond');
    @endphp
    <div class="panel panel-default" data-ade-password-panel="1">
        <div class="panel-body">
            <a href="{{ route('restaurant.settings.ade-cambio-password') }}" class="btn btn-warning">
                <i class="fas fa-key"></i> Cambio password Agenzia Entrate
            </a>
        </div>
    </div>

    @if($currentProvider === 'ditron' && auth()->user()?->role === 'admin')
        <div class="panel panel-default" data-ditron-operations-shortcut="1">
            <div class="panel-body">
                <p class="m-b-sm">
                    <i class="fas fa-cash-register"></i>
                    <strong>Operazioni cassa Ditron</strong> — chiusura Z, lettura X, GETP e storico scontrini si trovano nel cruscotto dedicato.
                </p>
                <a href="{{ route('backoffice.ditron.receipts.index') }}" class="btn btn-primary">
                    <i class="fas fa-external-link-alt"></i> Vai al cruscotto Ditron
                </a>
            </div>
        </div>
    @endif

    <form class="needs-validation update-or-create-element" id="update-or-create-element">
        @foreach($grouped as $groupName => $settings)
            @php
                $providerBlock = $providerBlocks[$groupName] ?? null;
                $hiddenByProvider = $providerBlock && $providerBlock !== $currentProvider;
            @endphp
            <div class="panel panel-default"
                @if($providerBlock) data-corrispettivo-provider-block="{{ $providerBlock }}" @endif
                @if($hiddenByProvider) style="display:none" @endif>
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fas {{ $groupIcons[$groupName] ?? 'fa-cog' }}"></i> {{ $groupName }}
                    </h4>
                </div>
                <div class="panel-body">
                    <div class="row">
                        @foreach($settings as $setting)
                            @if(in_array($setting->key, $rendered)) @continue @endif
                            @php $rendered[] = $setting->key; @endphp

                            @if($setting->key === 'cash_drawer_integration')
                                <div class="col-xs-12 col-sm-4 m-t-sm">
                                    <label>{{ $setting->description ?? $setting->key }}</label>
                                    @include('backoffice.components.form.select', [
                                        'field' => true,
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'options' => $cashDrawerOptions,
                                        'value' => $setting->value,
                                        'hide_first' => true,
                                        'dataset' => ['cash-drawer-integration-select' => '1'],
                                    ])
                                    <small class="text-muted">{{ $setting->key }}</small>
                                </div>
                            @elseif($setting->key === 'cash_drawer_ip')
                                <div class="col-xs-12 col-sm-4 m-t-sm" data-cash-drawer-block="printer" style="{{ \App\Models\Setting::getCashDrawerIntegration() === 'printer' ? '' : 'display:none' }}">
                                    @include('backoffice.components.form.input', [
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'label' => $setting->description ?? $setting->key,
                                        'col' => 12,
                                        'type' => 'text',
                                        'value' => $setting->value,
                                        'placeholder' => '192.168.1.150',
                                        'small' => $setting->key,
                                    ])
                                </div>
                            @elseif($setting->key === 'pos_integration')
                                <div class="col-xs-12 col-sm-4 m-t-sm">
                                    <label>{{ $setting->description ?? $setting->key }}</label>
                                    @include('backoffice.components.form.select', [
                                        'field' => true,
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'options' => $posIntegrationOptions,
                                        'value' => $setting->value,
                                        'hide_first' => true,
                                        'dataset' => ['pos-integration-select' => '1'],
                                    ])
                                    <small class="text-muted">{{ $setting->key }}</small>
                                </div>
                                </div></div></div>
                                <div class="panel panel-default" data-pos-integration-block="revolut" style="{{ $setting->value === 'revolut' ? '' : 'display:none' }}">
                                    <div class="panel-heading">
                                        <h4 class="panel-title"><i class="fas fa-credit-card"></i> Configurazione Revolut</h4>
                                    </div>
                                    <div class="panel-body"><div class="row">
                            @elseif($setting->key === 'revolut.environment')
                                <div class="col-xs-12 col-sm-4 m-t-sm">
                                    <label>{{ $setting->description ?? $setting->key }}</label>
                                    @include('backoffice.components.form.select', [
                                        'field' => true,
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'options' => $revolutEnvOptions,
                                        'value' => $setting->value,
                                        'hide_first' => true,
                                    ])
                                    <small class="text-muted">{{ $setting->key }}</small>
                                </div>
                            @elseif($setting->key === 'corrispettivo_provider')
                                <div class="col-xs-12 col-sm-4 m-t-sm">
                                    <label>{{ $setting->description ?? $setting->key }}</label>
                                    @include('backoffice.components.form.select', [
                                        'field' => true,
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'options' => $corrispettivoProviderOptions,
                                        'value' => $setting->value,
                                        'hide_first' => true,
                                        'dataset' => ['corrispettivo-provider-select' => '1'],
                                    ])
                                    <small class="text-muted">{{ $setting->key }}</small>
                                </div>
                            @elseif($setting->key === 'corrispettivo_printer_id')
                                <div class="col-xs-12 col-sm-4 m-t-sm">
                                    <label>{{ $setting->description ?? $setting->key }}</label>
                                    @include('backoffice.components.form.select', [
                                        'field' => true,
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'options' => $printers,
                                        'value' => $setting->value,
                                    ])
                                    <small class="text-muted">{{ $setting->key }}</small>
                                    <div class="invalid-feedback"></div>
                                </div>
                            @elseif($setting->type === 'decimal')
                                @include('backoffice.components.form.input', [
                                    'form' => 'update-or-create-element',
                                    'name' => $setting->key,
                                    'label' => $setting->description ?? $setting->key,
                                    'col' => 4,
                                    'type' => 'number',
                                    'step' => '0.01',
                                    'value' => $setting->value,
                                    'small' => $setting->key,
                                ])
                            @elseif($setting->type === 'integer' && $setting->key === 'preconto_printer_id')
                                <div class="col-xs-12 col-sm-4 m-t-sm">
                                    <label>{{ $setting->description ?? $setting->key }}</label>
                                    @include('backoffice.components.form.select', [
                                        'field' => true,
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'options' => $printers,
                                        'value' => $setting->value,
                                    ])
                                    <small class="text-muted">{{ $setting->key }}</small>
                                    <div class="invalid-feedback"></div>
                                </div>
                            @elseif($setting->type === 'integer')
                                @include('backoffice.components.form.input', [
                                    'form' => 'update-or-create-element',
                                    'name' => $setting->key,
                                    'label' => $setting->description ?? $setting->key,
                                    'col' => 4,
                                    'type' => 'number',
                                    'step' => '1',
                                    'value' => $setting->value,
                                    'small' => $setting->key,
                                ])
                            @elseif($setting->type === 'boolean')
                                <div class="col-xs-12 col-sm-4 m-t-sm">
                                    <label>{{ $setting->description ?? $setting->key }}</label>
                                    <div class="switch">
                                        <input type="hidden" name="{{ $setting->key }}" value="0" form="update-or-create-element">
                                        <input
                                            type="checkbox"
                                            name="{{ $setting->key }}"
                                            id="{{ $setting->key }}"
                                            form="update-or-create-element"
                                            value="1"
                                            {{ filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                            @if($setting->key === 'agenzia_entrate.enabled') data-ade-enabled-toggle="1" @endif
                                        >
                                        <label for="{{ $setting->key }}"></label>
                                    </div>
                                    <small class="text-muted">{{ $setting->key }}</small>
                                </div>
                            @else
                                @include('backoffice.components.form.input', [
                                    'form' => 'update-or-create-element',
                                    'name' => $setting->key,
                                    'label' => $setting->description ?? $setting->key,
                                    'col' => 4,
                                    'value' => $setting->value,
                                    'small' => $setting->key,
                                ])
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <div class="row">
            <div class="col-xs-12 col-sm-2 text-center m-t-sm">
                @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Salva Settaggi', 'dataset' => ['route' => 'restaurant/settings']])
                <div class="col-xs-12 object-response"></div>
            </div>
        </div>
    </form>

    <script>
        (function () {
            const posSelect = document.querySelector('[data-pos-integration-select]');
            const posBlock  = document.querySelector('[data-pos-integration-block="revolut"]');
            if (posSelect && posBlock) {
                const sync = () => { posBlock.style.display = posSelect.value === 'revolut' ? '' : 'none'; };
                posSelect.addEventListener('change', sync);
                sync();
            }

            const cdSelect = document.querySelector('[data-cash-drawer-integration-select]');
            const cdBlock  = document.querySelector('[data-cash-drawer-block="printer"]');
            if (cdSelect && cdBlock) {
                const sync = () => { cdBlock.style.display = cdSelect.value === 'printer' ? '' : 'none'; };
                cdSelect.addEventListener('change', sync);
                sync();
            }

            const cpSelect = document.querySelector('[data-corrispettivo-provider-select]');
            const cpBlocks = document.querySelectorAll('[data-corrispettivo-provider-block]');
            if (cpSelect && cpBlocks.length) {
                const sync = () => {
                    cpBlocks.forEach(b => {
                        b.style.display = b.dataset.corrispettivoProviderBlock === cpSelect.value ? '' : 'none';
                    });
                };
                cpSelect.addEventListener('change', sync);
                sync();
            }

            const adeToggle = document.querySelector('[data-ade-enabled-toggle]');
            if (adeToggle) {
                const toggleName = adeToggle.getAttribute('name');
                const adeFields = document.querySelectorAll('[name^="agenzia_entrate."], [name^="agenzia_entrate_"]');
                const adePasswordPanel = document.querySelector('[data-ade-password-panel]');
                const sync = () => {
                    const visible = adeToggle.checked;
                    adeFields.forEach(field => {
                        if (field === adeToggle) return;
                        if (field.getAttribute('name') === toggleName) return;
                        const wrapper = field.closest('.col-xs-12');
                        if (wrapper) wrapper.style.display = visible ? '' : 'none';
                    });
                    if (adePasswordPanel) adePasswordPanel.style.display = visible ? '' : 'none';
                };
                adeToggle.addEventListener('change', sync);
                sync();
            }
        })();
    </script>
@endsection
