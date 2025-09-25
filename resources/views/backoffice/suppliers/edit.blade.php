@extends('backoffice.layout')

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'title' => 'Modifica fornitore',
        'level_1' => ['label' => 'Fornitori', 'href' => route('suppliers')],
        'level_2' => ['label' => 'Modifica fornitore: ' . $object->label],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12 col-sm-9">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation edit-supplier" id="edit-supplier" novalidate data-id="{{ $object->id }}">
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'company_name', 'label' => 'Ragione sociale *', 'col' => 6])
                            @include('backoffice.components.form.input',['name' => 'fiscal_code', 'type' => 'number', 'label' => 'Codice fiscale *', 'col' => 3])
                            @include('backoffice.components.form.input',['name' => 'vat_number', 'type' => 'number', 'label' => 'Partita IVA *', 'col' => 3])
                        </div>
                        <div class="row">
                            <div class="col-xs-12">
                                <hr />
                                <h4>Dati della sede</h4>
                            </div>
                            @include('backoffice.components.form.input',['name' => 'address', 'label' => 'Indirizzo', 'col' => 4])
                            @include('backoffice.components.form.input',['name' => 'number', 'label' => 'Numero civico', 'col' => 2])
                            @include('backoffice.components.form.input',['name' => 'zip_code', 'label' => 'CAP', 'col' => 2])
                            <div class="col-xs-12"></div>
                            @include('backoffice.components.form.input',['name' => 'city', 'label' => 'Città', 'col' => 4])
                            @include('backoffice.components.form.input',['name' => 'province', 'label' => 'Provincia', 'col' => 4])
                            @include('backoffice.components.form.input',['name' => 'nation', 'label' => 'Nazione', 'col' => 4, 'value' => 'Italia'])
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
                            @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-edit-supplier col-xs-12', 'label' => 'Modifica fornitore', 'id' => $object->id])
                            <div class="col-xs-12 object-response"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
