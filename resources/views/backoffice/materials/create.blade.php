@extends('backoffice.layout', ['title' => 'Nuovo Ingrediente'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Ingredienti', 'href' => route('restaurant.materials.index')],
        'level_2' => ['label' => 'Nuovo Ingrediente'],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element" id="update-or-create-element">
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'label', 'label' => 'Ingrediente (Es. spaghetti) *', 'col' => 12])
                        </div>
                        <div class="row">
                            @include('backoffice.components.form.input',['form' => 'update-or-create-element', 'name' => 'stock', 'label' => 'Quantità *', 'col' => 2])
                            @include('backoffice.components.form.select',['form' => 'update-or-create-element', 'name' => 'stock_type', 'label' => 'Unità di misura *', 'col' => 2, 'options' => $stock_types])
                            @include('backoffice.components.form.input',['form' => 'update-or-create-element', 'name' => 'alert_threshold', 'label' => 'Soglia minima giacenza *', 'col' => 2])

                        </div>
                        <div class="row">
                            <div class="col-xs-12 col-sm-2 text-center m-t-sm">
                                @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Inserisci Ingrediente', 'dataset' => ['route' => 'restaurant/materials']])
                                <div class="col-xs-12 object-response"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
