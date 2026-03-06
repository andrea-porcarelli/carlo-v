@extends('backoffice.layout', ['title' => $object->type === 'extra' ? 'Modifica supplemento' : 'Modifica rimozione'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Extra e Rimozioni', 'href' => route('restaurant.menu-options.index') . '?type=' . $object->type],
        'level_2' => ['label' => ($object->type === 'extra' ? 'Modifica supplemento' : 'Modifica rimozione') . ': ' . $object->label],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12 col-sm-9">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element" id="update-or-create-element">
                        <input type="hidden" name="type" value="{{ $object->type }}">
                        <div class="row">
                            @include('backoffice.components.form.input', ['name' => 'label', 'label' => 'Label *', 'col' => 6, 'value' => $object->label])
                            @if($object->type === 'extra')
                                @include('backoffice.components.form.input', ['name' => 'price', 'label' => 'Prezzo (€) *', 'col' => 3, 'type' => 'number', 'step' => '0.01', 'min' => '0', 'value' => $object->price])
                            @endif
                            @include('backoffice.components.form.input', ['name' => 'sort_order', 'label' => 'Ordine', 'col' => 3, 'type' => 'number', 'value' => $object->sort_order])
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row supplier_refunds_index">
                        <div class="col-xs-12 text-center m-t-sm">
                            @include('backoffice.components.form.button', [
                                'field' => true,
                                'col' => 12,
                                'class' => 'btn-update-or-create-element col-xs-12',
                                'label' => $object->type === 'extra' ? 'Modifica supplemento' : 'Modifica rimozione',
                                'dataset' => ['route' => 'restaurant/menu-options', 'id' => $object->id]
                            ])
                            <div class="col-xs-12 object-response"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
