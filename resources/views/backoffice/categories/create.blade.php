@extends('backoffice.layout', ['title' => 'Crea Categoria piatti'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Categorie', 'href' => route('restaurant.categories.index')],
        'level_2' => ['label' => 'Crea Categoria piatti'],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12 col-sm-9">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element" id="update-or-create-element">
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'label', 'label' => 'Dai un nome alla categoria *', 'col' => 6])
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        @include('backoffice.components.form.select',['form' => 'update-or-create-element', 'name' => 'printer_id', 'label' => 'Associa stampante *', 'col' => 12, 'options' => $printers])
                        <div class="col-xs-12 text-center m-t-sm">
                            @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Crea Categoria piatti', 'dataset' => ['route' => 'restaurant/categories']])
                            <div class="col-xs-12 object-response"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
