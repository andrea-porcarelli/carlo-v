@extends('backoffice.layout', ['title' => 'Associa prodotti agli ingredienti'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture', 'href' => route('invoices.index')],
        'level_2' => ['label' => 'Associa prodotti agli ingredienti'],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <form id="mappingForm">
                        <div class="row">
                            <div class="col-xs-12">
                                <div class="panel panel-default">
                                    <div class="panel-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-striped">
                                                <thead>
                                                <tr>
                                                    <th width="30%">Prodotto Fattura</th>
                                                    <th width="10%" class="text-right">Quantità</th>
                                                    <th width="10%" class="text-right">Prezzo</th>
                                                    <th width="40%">Ingrediente Associato</th>
                                                    <th width="10%" class="text-center">Stato</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @foreach($supplierInvoiceProducts as $product)
                                                    <tr>
                                                        <td>
                                                            <strong>{{ $product->product_name }}</strong>
                                                        </td>
                                                        <td class="text-right">
                                                            {{ $product->quantity }}
                                                        </td>
                                                        <td class="text-right">
                                                            € {{ number_format($product->price, 2, ',', '.') }}
                                                        </td>
                                                        <td>
                                                            <select
                                                                name="mappings[{{ $product->id }}]"
                                                                class="form-control material-select"
                                                                data-product-id="{{ $product->id }}"
                                                                {{ $product->material_id ? '' : 'required' }}>
                                                                <option value="">-- Seleziona Materiale --</option>
                                                                <option value="0">>> IGNORA MAPPATURA <<</option>
                                                                @foreach($materials as $material)
                                                                    <option
                                                                        value="{{ $material->id }}"
                                                                        {{ $product->material_id == $material->id ? 'selected' : '' }}>
                                                                        {{ $material->label }}
                                                                        ({{ $material->stock }} {{ $material->stock_type }})
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            <small class="text-muted" style="display: block; margin-top: 5px;">
                                                                <span class="selected-info-{{ $product->id }}">
                                                                    @if($product->material_id)
                                                                        {{ $materials->find($product->material_id)->stock_type_label ?? '' }}
                                                                    @endif
                                                                </span>
                                                            </small>
                                                        </td>
                                                        <td class="text-center">
                                                            @if($product->material_id)
                                                                <span class="label label-success">
                                                                    <span class="glyphicon glyphicon-ok-circle"></span> Mappato
                                                                </span>
                                                            @else
                                                                <span class="label label-warning">
                                                                    <span class="glyphicon glyphicon-exclamation-sign"></span> Da mappare
                                                                </span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row" style="margin-top: 20px;">
                            <div class="col-xs-12">
                                <div class="clearfix">
                                    <div class="pull-left">
                                        <a href="{{ route('invoices.index') }}" class="btn btn-default">
                                            <span class="glyphicon glyphicon-arrow-left"></span> Indietro
                                        </a>
                                    </div>
                                    <div class="pull-right">
                                        <span style="margin-right: 15px; line-height: 34px; display: inline-block;" class="text-muted">
                                            <strong id="mappedCount">{{ $supplierInvoiceProducts->where('material_id', '!=', null)->count() }}</strong>
                                            su
                                            <strong>{{ $supplierInvoiceProducts->count() }}</strong>
                                            mappati
                                        </span>
                                        <button type="button" class="btn btn-primary btn-store-map-products" disabled data-invoice-id="{{ $invoice->id }}">
                                            <span class="glyphicon glyphicon-floppy-disk"></span> Salva Mappatura
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('custom-script')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var materials = {!! json_encode($materials) !!};

                // Inizializza Select2 su tutte le select dei materiali
                $('.material-select').select2({
                    placeholder: '-- Seleziona Materiale --',
                    allowClear: true,
                    width: '100%',
                    language: {
                        noResults: function() {
                            return "Nessun materiale trovato";
                        },
                        searching: function() {
                            return "Ricerca in corso...";
                        }
                    }
                });

                // Gestione cambio selezione
                $('.material-select').on('change', function() {
                    var productId = $(this).data('product-id');
                    var materialId = $(this).val();
                    var infoSpan = $('.selected-info-' + productId);

                    if (materialId) {
                        var material = null;
                        for (var j = 0; j < materials.length; j++) {
                            if (materials[j].id == materialId) {
                                material = materials[j];
                                break;
                            }
                        }

                        if (material) {
                            infoSpan.text(material.stock_type_label);
                        }

                        // Aggiorna label stato
                        var label = $(this).closest('tr').find('.label');
                        label.attr('class', 'label label-success');
                        label.html('<span class="glyphicon glyphicon-ok-circle"></span> Mappato');
                    } else {
                        infoSpan.text('');

                        // Aggiorna label stato
                        var label = $(this).closest('tr').find('.label');
                        label.attr('class', 'label label-warning');
                        label.html('<span class="glyphicon glyphicon-exclamation-sign"></span> Da mappare');
                    }

                    updateMappedCount();
                });

                function updateMappedCount() {
                    var mapped = 0;
                    $('.material-select').each(function() {
                        if ($(this).val() !== '') {
                            mapped++;
                        }
                    });
                    if (mapped === 0) {
                        $(`.btn-store-map-products`).prop('disabled', true);
                    } else {
                        $(`.btn-store-map-products`).prop('disabled', false);
                    }
                    $('#mappedCount').text(mapped);
                }
            });
        </script>
@endsection
@section('custom-css')
        <style>
            .table tbody tr:hover {
                background-color: #f8f9fa;
            }

            .material-select {
                max-width: 100%;
            }

            .card {
                border: none;
                border-radius: 0.25rem;
            }

            .badge {
                padding: 0.5em 0.75em;
                font-weight: 500;
                font-size: 90%;
            }

            .shadow-sm {
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
            }
        </style>
@endsection
