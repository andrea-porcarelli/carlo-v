@extends('backoffice.layout', ['title' => 'Modifica allergene'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Allergeni', 'href' => route('restaurant.allergens.index')],
        'level_2' => ['label' => 'Modifica allergene: ' . $object->label],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12 col-sm-9">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element" id="update-or-create-element">
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'label', 'label' => 'Allergene *', 'col' => 6])
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row supplier_refunds_index ">
                        <div class="col-xs-12 text-center m-t-sm">
                            @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Modifica allergene', 'dataset' => ['route' => 'restaurant/allergens', 'id' => $object->id ]])
                            <div class="col-xs-12 object-response"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
