@extends('backoffice.layout', ['title' => 'Modifica piatto del menu: ' . $object->label])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Piatti del menu', 'href' => route('restaurant.dishes.index')],
        'level_2' => ['label' => 'Modifica piatto del menu: ' . $object->label],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12 col-sm-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4>Nome del piatto e Ingredienti </h4>
                </div>
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element" id="update-or-create-element">
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'label', 'label' => 'Dai un nome al piatto *', 'col' => 12])
                            <div class="col-xs-12 m-t-lg">
                                @livewire('dish-materials-manager', [
                                'dishId' => $object->id,
                                'existingMaterials' => $object->materials
                                ])
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4>Descrizione </h4>
                </div>
                <div class="panel-body">
                    <div class="row">
                        @include('backoffice.components.form.textarea',['form' => 'update-or-create-element', 'name' => 'description', 'class' => 'summernote', 'label' => 'Descrizione', 'col' => 12])
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-12 text-center m-t-sm">
                            @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Modifica piatto', 'dataset' => ['route' => 'restaurant/dishes', 'id' => $object->id]])
                            <div class="col-xs-12 object-response"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        @include('backoffice.components.form.select',['form' => 'update-or-create-element', 'name' => 'category_id', 'label' => 'Categoria *', 'col' => 12, 'options' => $categories])
                        @include('backoffice.components.form.input',['form' => 'update-or-create-element', 'name' => 'price', 'label' => 'Prezzo *', 'col' => 12])
                    </div>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-12">
                            @livewire('dish-allergens-manager', [
                                'dishId' => $object->id,
                                'existingAllergens' => $object->allergens
                            ])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('custom-script')
    <script src="{{ asset('backoffice/js/plugins/summernote/summernote.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.summernote').summernote({
                height: 250
            });
        });
    </script>
@endsection
