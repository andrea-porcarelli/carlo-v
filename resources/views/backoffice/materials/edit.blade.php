@extends('backoffice.layout')

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'title' => 'Modifica Ingrediente',
        'level_1' => ['label' => 'Ingredienti', 'href' => route('restaurant.materials.index')],
        'level_2' => ['label' => 'Modifica Ingrediente: ' . $object->label],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12 col-sm-9">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form class="needs-validation update-or-create-element" id="update-or-create-element">
                        <div class="row">
                            @include('backoffice.components.form.input',['name' => 'label', 'label' => 'Ingrediente (Es. spaghetti) *', 'col' => 6])
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-3">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row supplier_refunds_index ">
                        @include('backoffice.components.form.input',['form' => 'update-or-create-element', 'name' => 'stock', 'label' => 'Quantità *', 'col' => 6])
                        @include('backoffice.components.form.select',['form' => 'update-or-create-element', 'name' => 'stock_type', 'label' => 'Unità di misura *', 'col' => 6, 'options' => $stock_types])

                        <div class="col-xs-12 text-center m-t-sm">
                            @include('backoffice.components.form.button', ['field' => true, 'col' => 12, 'class' => 'btn-update-or-create-element col-xs-12', 'label' => 'Modifica Ingrediente', 'dataset' => ['route' => 'restaurant/materials', 'id' => $object->id ]])
                            <div class="col-xs-12 object-response"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row" style="margin-bottom: 20px;">
                        <div class="col-sm-3">
                            <div class="panel panel-success">
                                <div class="panel-body text-center">
                                    <div class="h3" style="margin:0">{{ number_format($stockSummary['imported'], 2, ',', '.') }} {{ $object->stock_type }}</div>
                                    <div class="text-muted">Importato</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="panel panel-warning">
                                <div class="panel-body text-center">
                                    <div class="h3" style="margin:0">{{ number_format($stockSummary['consumed'], 2, ',', '.') }} {{ $object->stock_type }}</div>
                                    <div class="text-muted">Consumato</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="panel panel-{{ $stockSummary['current'] < 0 ? 'danger' : 'info' }}">
                                <div class="panel-body text-center">
                                    <div class="h3" style="margin:0">{{ number_format($stockSummary['current'], 2, ',', '.') }} {{ $object->stock_type }}</div>
                                    <div class="text-muted">Giacenza Attuale</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="panel panel-{{ $stockSummary['is_low'] ? 'danger' : 'default' }}">
                                <div class="panel-body text-center">
                                    <div class="h3" style="margin:0">
                                        @if($stockSummary['is_low'])
                                            <span class="text-danger"><i class="fa fa-exclamation-triangle"></i> BASSO</span>
                                        @else
                                            <span class="text-success"><i class="fa fa-check"></i> OK</span>
                                        @endif
                                    </div>
                                    <div class="text-muted">Stato</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4>Movimentazioni</h4>
                    <button
                        class="btn btn-sm btn-warning btn-add-stock m-b-sm"
                        title="Aggiungi giacenza"
                        data-id="{{ $object->id }}"
                        data-label="{{ $object->label }}"
                        data-stock-type="{{ $object->stock_type }}"
                    >
                        <span class="fa fa-plus-circle"></span> Aggiungi quantità
                    </button>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover">
                            <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th class="text-right">Quantità</th>
                                <th>Dettaglio</th>
                                <th>Note</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($movements as $mov)
                                <tr class="{{ $mov->type === 'consumption' ? 'warning' : 'success' }}">
                                    <td>{{ \Carbon\Carbon::parse($mov->date)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @if($mov->type === 'load')
                                            <span class="label label-success"><i class="fa fa-arrow-down"></i> Carico</span>
                                        @else
                                            <span class="label label-warning"><i class="fa fa-arrow-up"></i> Consumo</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if($mov->type === 'load')
                                            <strong class="text-success">+{{ number_format($mov->quantity, 2, ',', '.') }}</strong>
                                        @else
                                            <strong class="text-danger">-{{ number_format($mov->quantity, 2, ',', '.') }}</strong>
                                        @endif
                                        {{ $object->stock_type }}
                                    </td>
                                    <td>
                                        @if($mov->type === 'load')
                                            @if($mov->invoice_product)
                                                <i class="fa fa-file-text-o"></i> Fattura: {{ $mov->invoice_product }}
                                            @else
                                                <i class="fa fa-pencil"></i> Inserimento manuale
                                            @endif
                                            @if($mov->purchase_price)
                                                - € {{ number_format($mov->purchase_price, 2, ',', '.') }}
                                            @endif
                                        @else
                                            <i class="fa fa-cutlery"></i> {{ $mov->dish_name }} (x{{ $mov->dish_qty }}) - Tavolo: {{ $mov->table_name }}
                                        @endif
                                    </td>
                                    <td>{{ $mov->notes ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">Nessuna movimentazione trovata</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Aggiungi Giacenza -->
    <x-load-material-modal />
@endsection
