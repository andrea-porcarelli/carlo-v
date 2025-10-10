@extends('backoffice.layout', ['title' => 'Crea nuovo piatto del menu'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Piatti del menu', 'href' => route('restaurant.dishes.index')],
        'level_2' => ['label' => 'Crea nuovo piatto del menu'],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12 col-sm-9">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element" id="update-or-create-element">
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'label', 'label' => 'Dai un nome al piatto *', 'col' => 12])
                            @include('backoffice.components.form.textarea',['name' => 'description', 'class' => 'summernote', 'label' => 'Descrizione', 'col' => 12])
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-12 text-center m-t-sm">
                            @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Crea piatto', 'dataset' => ['route' => 'restaurant/dishes']])
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
                            @livewire('dish-materials-manager')
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-12">
                            @livewire('dish-allergens-manager')
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
