@extends('backoffice.layout')

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
       'level_1' => ['label' => 'Settaggi'],
   ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element" id="update-or-create-element">
                        @foreach($settings as $setting)
                            <div class="row">
                                @if($setting->type === 'decimal')
                                    @include('backoffice.components.form.input', [
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'label' => $setting->description ?? $setting->key,
                                        'col' => 4,
                                        'type' => 'number',
                                        'step' => '0.01',
                                        'value' => $setting->value,
                                    ])
                                @elseif($setting->type === 'integer' && $setting->key === 'preconto_printer_id')
                                    @include('backoffice.components.form.select', [
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'label' => $setting->description ?? $setting->key,
                                        'col' => 4,
                                        'options' => $printers,
                                        'value' => $setting->value,
                                    ])
                                @elseif($setting->type === 'integer')
                                    @include('backoffice.components.form.input', [
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'label' => $setting->description ?? $setting->key,
                                        'col' => 4,
                                        'type' => 'number',
                                        'step' => '1',
                                        'value' => $setting->value,
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
                                            >
                                            <label for="{{ $setting->key }}"></label>
                                        </div>
                                    </div>
                                @else
                                    @include('backoffice.components.form.input', [
                                        'form' => 'update-or-create-element',
                                        'name' => $setting->key,
                                        'label' => $setting->description ?? $setting->key,
                                        'col' => 4,
                                        'value' => $setting->value,
                                    ])
                                @endif
                            </div>
                        @endforeach
                        <div class="row">
                            <div class="col-xs-12 col-sm-2 text-center m-t-sm">
                                @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Salva Settaggi', 'dataset' => ['route' => 'restaurant/settings']])
                                <div class="col-xs-12 object-response"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
