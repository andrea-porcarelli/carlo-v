@extends('backoffice.layout')

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'title' => 'Modifica fornitore',
        'level_1' => ['label' => 'Fornitori', 'href' => route('suppliers.index')],
        'level_2' => ['label' => 'Modifica fornitore: ' . $object->label],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12 col-sm-9">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element" id="update-or-create-element">
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'label', 'label' => 'Nome della categoria *', 'col' => 6])
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row supplier_refunds_index ">
                        @include('backoffice.components.form.select',['form' => 'update-or-create-element', 'name' => 'printer_id', 'label' => 'Associa stampante *', 'col' => 12, 'options' => $printers])

                        <div class="col-xs-12 text-center m-t-sm">
                            @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Modifica stampante', 'dataset' => ['route' => 'restaurant/categories', 'id' => $object->id ]])
                            <div class="col-xs-12 object-response"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
