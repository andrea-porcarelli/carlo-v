@extends('backoffice.layout', ['title' => 'Crea stampante'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Stampanti', 'href' => route('restaurant.printers.index')],
        'level_2' => ['label' => 'Crea stampante'],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12 col-sm-9">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element">
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'label', 'label' => 'Dai un nome alla stampante *', 'col' => 6])
                            @include('backoffice.components.form.input',['name' => 'ip', 'label' => 'IP sulla rete *', 'col' => 3])
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
                            @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Registra stampante', 'dataset' => ['route' => 'restaurant/printers']])
                            <div class="col-xs-12 object-response"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
