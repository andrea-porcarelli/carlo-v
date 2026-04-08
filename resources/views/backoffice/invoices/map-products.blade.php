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
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4 class="panel-title" style="margin:0;">
                        <i class="fa fa-file-text-o"></i>
                        Fattura: <strong>{{ $invoice->invoice_number }}</strong>
                        &mdash;
                        {{ $invoice->supplier->company_name }}
                    </h4>
                </div>
                <div class="panel-body" style="padding: 12px 15px;">
                    <div class="row">
                        <div class="col-sm-3">
                            <span class="text-muted small">Data fattura</span><br>
                            <strong>{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '—' }}</strong>
                        </div>
                        <div class="col-sm-3">
                            <span class="text-muted small">Importo totale</span><br>
                            <strong>€ {{ number_format($invoice->amount, 2, ',', '.') }}</strong>
                        </div>
                        <div class="col-sm-3">
                            <span class="text-muted small">Prodotti in fattura</span><br>
                            <strong>{{ $invoice->products_count }}</strong>
                            <span class="text-muted small">({{ $supplierInvoiceProducts->count() }} da mappare)</span>
                        </div>
                        <div class="col-sm-3">
                            <span class="text-muted small">File</span><br>
                            <small class="text-muted">{{ $invoice->filename }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
                                                    <th width="25%">Prodotto Fattura</th>
                                                    <th width="28%" class="text-center">Quantità da caricare</th>
                                                    <th width="8%" class="text-right">Prezzo</th>
                                                    <th width="30%">Ingrediente Associato</th>
                                                    <th width="9%" class="text-center">Stato</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @foreach($supplierInvoiceProducts as $product)
                                                    <tr data-product-id="{{ $product->id }}">
                                                        <td>
                                                            <strong>{{ $product->product_name }}</strong>
                                                            <div class="qty-detect-hint" data-product-id="{{ $product->id }}"></div>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="qty-control">
                                                                <div class="qty-invoice-line">
                                                                    <span class="text-muted small">In fattura:</span>
                                                                    <strong>{{ $product->quantity }}</strong>
                                                                </div>
                                                                <div class="qty-fields">
                                                                    <div class="qty-field">
                                                                        <label class="qty-field-label">Colli</label>
                                                                        <input type="number"
                                                                               class="form-control qty-n"
                                                                               data-product-id="{{ $product->id }}"
                                                                               value="1"
                                                                               min="1"
                                                                               step="1">
                                                                    </div>
                                                                    <span class="qty-op">×</span>
                                                                    <div class="qty-field">
                                                                        <label class="qty-field-label">Dimensione</label>
                                                                        <input type="number"
                                                                               class="form-control qty-size"
                                                                               data-product-id="{{ $product->id }}"
                                                                               value=""
                                                                               min="0.001"
                                                                               step="any"
                                                                               placeholder="es. 75">
                                                                    </div>
                                                                    <div class="qty-field">
                                                                        <label class="qty-field-label">Unità</label>
                                                                        <select class="form-control qty-unit" data-product-id="{{ $product->id }}">
                                                                            <option value="">--</option>
                                                                            <option value="pz">pz</option>
                                                                            <option value="g">g → kg</option>
                                                                            <option value="kg">kg</option>
                                                                            <option value="ml">ml → cl</option>
                                                                            <option value="cl">cl</option>
                                                                            <option value="l">l → cl</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <input type="hidden"
                                                                       name="multipliers[{{ $product->id }}]"
                                                                       class="qty-multiplier"
                                                                       data-product-id="{{ $product->id }}"
                                                                       data-base-qty="{{ $product->quantity }}"
                                                                       value="{{ $product->quantity_multiplier ?? 1 }}">
                                                                <div class="qty-result-block qty-result-{{ $product->id }} qty-result-empty">
                                                                    <span class="qty-real" data-product-id="{{ $product->id }}">—</span>
                                                                    <span class="unit-label unit-label-{{ $product->id }}"></span>
                                                                </div>
                                                                <div class="qty-conversion-hint qty-conversion-{{ $product->id }}"></div>
                                                            </div>
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
                                                                        data-stock-type="{{ $material->stock_type }}"
                                                                        {{ $product->material_id == $material->id ? 'selected' : '' }}>
                                                                        {{ $material->label }}
                                                                        ({{ $material->stock }} {{ $material->stock_type }})
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:5px;">
                                                                <small class="text-muted">
                                                                    <span class="selected-info-{{ $product->id }}">
                                                                        @if($product->material_id)
                                                                            {{ $materials->find($product->material_id)->stock_type_label ?? '' }}
                                                                        @endif
                                                                    </span>
                                                                </small>
                                                                <a href="#"
                                                                   class="btn-open-new-material text-success"
                                                                   data-product-id="{{ $product->id }}"
                                                                   data-product-name="{{ $product->product_name }}"
                                                                   title="Crea nuovo ingrediente">
                                                                    <span class="glyphicon glyphicon-plus-sign"></span>
                                                                    <small>Nuovo ingrediente</small>
                                                                </a>
                                                            </div>
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

                    </form>
                </div>
            </div>
        </div>
    </div>
    {{-- Barra fissa in fondo --}}
    <div class="mapping-fixed-bar">
        <div class="mapping-fixed-bar-inner">
            <div class="mapping-fixed-left">
                <a href="{{ route('invoices.index') }}" class="btn btn-default">
                    <span class="glyphicon glyphicon-arrow-left"></span> Indietro
                </a>
            </div>
            <div class="mapping-fixed-right">
                <span class="mapping-progress-label">
                    <span class="mapping-progress-counts">
                        <strong id="mappedCount">0</strong>
                        <span class="text-muted"> / {{ $supplierInvoiceProducts->count() }}</span>
                    </span>
                    <span class="text-muted mapping-progress-text"> completati</span>
                </span>
                <div class="mapping-progress-bar-wrap">
                    <div class="mapping-progress-bar-fill" id="mappingProgressBar" style="width:0%"></div>
                </div>
                <button type="button" class="btn btn-primary btn-store-map-products" disabled data-invoice-id="{{ $invoice->id }}">
                    <span class="glyphicon glyphicon-floppy-disk"></span> Salva Mappatura
                </button>
            </div>
        </div>
    </div>

    {{-- Modal nuovo ingrediente --}}
    <div class="modal fade" id="newMaterialModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">
                        <span class="glyphicon glyphicon-plus-sign text-success"></span>
                        Nuovo Ingrediente
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" style="margin-bottom:15px;">
                        <span class="glyphicon glyphicon-tag"></span>
                        Prodotto in fattura: <strong id="newMaterialContext"></strong>
                    </div>

                    <div class="form-group">
                        <label>Nome ingrediente <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control"
                               id="newMaterialLabel"
                               placeholder="es. Prezzemolo"
                               autocomplete="off">
                        <div id="similarityResults" style="margin-top:8px;"></div>
                    </div>

                    <div class="row">
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label>Unità di misura <span class="text-danger">*</span></label>
                                <select class="form-control" id="newMaterialStockType">
                                    <option value="">-- Seleziona --</option>
                                    <option value="pz">Pezzo (pz)</option>
                                    <option value="kg">Kilogrammi (kg)</option>
                                    <option value="cl">Centilitri (cl)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label>Soglia minima giacenza</label>
                                <input type="number"
                                       class="form-control"
                                       id="newMaterialThreshold"
                                       min="0"
                                       step="any"
                                       placeholder="es. 100">
                                <small class="text-muted">Avviso sotto questa soglia</small>
                            </div>
                        </div>
                    </div>

                    <div id="newMaterialError" class="alert alert-danger" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-success" id="btnCreateMaterial" disabled>
                        <span class="glyphicon glyphicon-plus"></span> Crea Ingrediente
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('custom-script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            var materials = {!! json_encode($materials) !!};

            // ─── Conversioni verso le unità base di sistema: kg, cl, pz ────────
            // Le chiavi esterne sono le unità base (stock_type dell'ingrediente).
            // I valori interni sono i fattori per convertire l'unità della fattura → unità base.
            var conversionTable = {
                'kg': { 'kg': 1,    'g': 0.001 },
                'cl': { 'cl': 1,    'ml': 0.1,  'l': 100 },
                'pz': { 'pz': 1 },
            };

            function getConversionFactor(fromUnit, toUnit) {
                if (!fromUnit || !toUnit || fromUnit === toUnit) return 1;
                if (conversionTable[toUnit] && conversionTable[toUnit][fromUnit] !== undefined) {
                    return conversionTable[toUnit][fromUnit];
                }
                return null; // unità incompatibili
            }

            // ─── Auto-detect pattern dal nome prodotto ───────────────────────────
            function detectPattern(productName) {
                // Pattern NxM[unit]: "6x75cl", "12x33", "6X75"
                var nxm = productName.match(/(\d+)\s*[xX×]\s*(\d+(?:[.,]\d+)?)\s*(cl|ml|l|lt|g|gr|kg)?(?!\w)/i);
                if (nxm) {
                    var unit = nxm[3] ? nxm[3].toLowerCase() : '';
                    unit = unit.replace('gr', 'g').replace('lt', 'l');
                    return { n: parseInt(nxm[1]), size: parseFloat(nxm[2].replace(',', '.')), unit: unit };
                }

                // Pattern dimensione singola: "Gr400", "Kg2,5", "Lt1.5", "400g", "75cl", "1,5L"
                var sized = productName.match(
                    /(?:(g|gr|kg|lt?|ml|cl|l)\s*\.?\s*(\d+(?:[.,]\d+)?)|(\d+(?:[.,]\d+)?)\s*(g|gr|kg|lt?|ml|cl|l)(?!\w))/i
                );
                if (sized) {
                    var unit2 = (sized[1] || sized[4]).toLowerCase().replace('gr', 'g').replace('lt', 'l');
                    var size2 = parseFloat((sized[2] || sized[3]).replace(',', '.'));
                    return { n: 1, size: size2, unit: unit2 };
                }

                return null;
            }

            // ─── Calcolo e aggiornamento UI per una riga ─────────────────────────
            function computeAndUpdate(productId) {
                var baseQty  = parseFloat($('.qty-multiplier[data-product-id="' + productId + '"]').data('base-qty')) || 0;
                var n        = parseFloat($('.qty-n[data-product-id="' + productId + '"]').val());
                var size     = parseFloat($('.qty-size[data-product-id="' + productId + '"]').val());
                var unit     = $('.qty-unit[data-product-id="' + productId + '"]').val();
                var materialId = $('.material-select[data-product-id="' + productId + '"]').val();

                var resultBlock  = $('.qty-result-' + productId);
                var realSpan     = $('.qty-real[data-product-id="' + productId + '"]');
                var unitLabel    = $('.unit-label-' + productId);
                var convHint     = $('.qty-conversion-' + productId);

                // Ingrediente ignorato: nessun calcolo quantità necessario
                if (materialId === '0') {
                    resultBlock.attr('class', 'qty-result-block qty-result-' + productId + ' qty-result-empty');
                    realSpan.text('—');
                    unitLabel.text('');
                    convHint.html('');
                    updateRowStatus(productId);
                    updateGlobalSave();
                    return;
                }

                // Dati incompleti
                if (!n || !size || !unit) {
                    resultBlock.attr('class', 'qty-result-block qty-result-' + productId + ' qty-result-empty');
                    realSpan.text('—');
                    unitLabel.text('');
                    convHint.html('');
                    updateRowStatus(productId);
                    updateGlobalSave();
                    return;
                }

                // Trova ingrediente selezionato
                var material = materialId ? materials.find(function(m) { return m.id == materialId; }) : null;
                var ingredientUnit = material ? material.stock_type : null;

                var convFactor = 1;
                var convOk = true;

                if (ingredientUnit) {
                    var factor = getConversionFactor(unit, ingredientUnit);
                    if (factor === null) {
                        convOk = false;
                    } else {
                        convFactor = factor;
                    }
                }

                if (!convOk) {
                    resultBlock.attr('class', 'qty-result-block qty-result-' + productId + ' qty-result-error');
                    realSpan.text('!');
                    unitLabel.text('');
                    convHint.html('<small class="text-danger"><span class="glyphicon glyphicon-warning-sign"></span> Unità incompatibili con l\'ingrediente (' + ingredientUnit + ')</small>');
                    $('.qty-multiplier[data-product-id="' + productId + '"]').val('');
                    updateRowStatus(productId);
                    updateGlobalSave();
                    return;
                }

                var finalMultiplier = n * size * convFactor;
                var realQty = Math.round(baseQty * finalMultiplier * 1000) / 1000;

                $('.qty-multiplier[data-product-id="' + productId + '"]').val(finalMultiplier);

                resultBlock.attr('class', 'qty-result-block qty-result-' + productId + ' qty-result-ok');
                realSpan.text(realQty);
                unitLabel.text(ingredientUnit ? ' ' + ingredientUnit : ' ' + unit);

                if (ingredientUnit && unit && unit !== ingredientUnit) {
                    convHint.html('<small class="text-muted">Conversione: ' + unit + ' → ' + ingredientUnit + ' (×' + convFactor + ')</small>');
                } else {
                    convHint.html('');
                }

                updateRowStatus(productId);
                updateGlobalSave();
            }

            function isRowComplete(productId) {
                var materialId = $('.material-select[data-product-id="' + productId + '"]').val();
                if (!materialId) return false;
                if (materialId === '0') return true; // ignorato = completo

                var n    = parseFloat($('.qty-n[data-product-id="' + productId + '"]').val());
                var size = parseFloat($('.qty-size[data-product-id="' + productId + '"]').val());
                var unit = $('.qty-unit[data-product-id="' + productId + '"]').val();
                var mult = $('.qty-multiplier[data-product-id="' + productId + '"]').val();

                return n > 0 && size > 0 && unit && mult;
            }

            function updateRowStatus(productId) {
                var complete = isRowComplete(productId);
                var materialId = $('.material-select[data-product-id="' + productId + '"]').val();
                var label = $('tr[data-product-id="' + productId + '"] .label');

                if (complete && materialId !== '0') {
                    label.attr('class', 'label label-success');
                    label.html('<span class="glyphicon glyphicon-ok-circle"></span> Mappato');
                } else if (materialId === '0') {
                    label.attr('class', 'label label-default');
                    label.html('<span class="glyphicon glyphicon-minus-sign"></span> Ignorato');
                } else if (materialId) {
                    label.attr('class', 'label label-info');
                    label.html('<span class="glyphicon glyphicon-pencil"></span> Incompleto');
                } else {
                    label.attr('class', 'label label-warning');
                    label.html('<span class="glyphicon glyphicon-exclamation-sign"></span> Da mappare');
                }
            }

            function updateGlobalSave() {
                var total = $('.material-select').length;
                var complete = 0;
                $('.material-select').each(function () {
                    var pid = $(this).data('product-id');
                    if (isRowComplete(pid)) complete++;
                });
                $('#mappedCount').text(complete);
                $('.btn-store-map-products').prop('disabled', complete < total);
                var pct = total > 0 ? Math.round(complete / total * 100) : 0;
                $('#mappingProgressBar').css('width', pct + '%')
                    .toggleClass('mapping-progress-bar-done', complete === total);
            }

            // ─── Auto-detect al caricamento pagina ───────────────────────────────
            $('tr[data-product-id]').each(function () {
                var productId   = $(this).data('product-id');
                var productName = $(this).find('td:first strong').text();
                var detected    = detectPattern(productName);

                if (detected) {
                    if (detected.n && detected.n > 1) {
                        $('.qty-n[data-product-id="' + productId + '"]').val(detected.n);
                    }
                    if (detected.size) {
                        $('.qty-size[data-product-id="' + productId + '"]').val(detected.size);
                    }
                    if (detected.unit) {
                        $('.qty-unit[data-product-id="' + productId + '"]').val(detected.unit);
                    }

                    var parts = [];
                    if (detected.n > 1) parts.push(detected.n + ' colli');
                    if (detected.size) parts.push(detected.size + (detected.unit || ''));

                    if (parts.length) {
                        $('.qty-detect-hint[data-product-id="' + productId + '"]').html(
                            '<small class="text-success"><span class="glyphicon glyphicon-flash"></span> Rilevato: ' + parts.join(' × ') + '</small>'
                        );
                    }

                    computeAndUpdate(productId);
                }
            });

            // ─── Inizializza Select2 ──────────────────────────────────────────────
            $('.material-select').select2({
                placeholder: '-- Seleziona Materiale --',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function () { return "Nessun materiale trovato"; },
                    searching: function () { return "Ricerca in corso..."; }
                }
            });

            // ─── Event listeners ──────────────────────────────────────────────────
            $(document).on('input change', '.qty-n, .qty-size, .qty-unit', function () {
                computeAndUpdate($(this).data('product-id'));
            });

            $('.material-select').on('change', function () {
                var productId  = $(this).data('product-id');
                var materialId = $(this).val();
                var material   = materialId && materialId !== '0'
                    ? materials.find(function(m) { return m.id == materialId; })
                    : null;

                // Suggerisci unità in base all'ingrediente se il campo è ancora vuoto
                if (material && !$('.qty-unit[data-product-id="' + productId + '"]').val()) {
                    var stockType = material.stock_type;
                    if (['g', 'kg', 'ml', 'cl', 'l', 'pz'].includes(stockType)) {
                        $('.qty-unit[data-product-id="' + productId + '"]').val(stockType);
                    }
                }

                $('.selected-info-' + productId).text(material ? material.stock_type_label : '');

                computeAndUpdate(productId);
            });

            // Stato iniziale
            updateGlobalSave();

            // ─── Nuovo Ingrediente ────────────────────────────────────────────────

            var newMaterialForProductId = null;
            var storeUrl = '{{ route("restaurant.materials.index") }}';

            function normalizeLabel(str) {
                return str.toLowerCase().trim().replace(/\s+/g, ' ');
            }

            function findSimilarMaterials(label) {
                var norm = normalizeLabel(label);
                if (norm.length < 2) return [];

                var results = [];
                materials.forEach(function (m) {
                    var mNorm = normalizeLabel(m.label);
                    if (mNorm === norm) {
                        results.push({ material: m, type: 'exact' });
                    } else if (mNorm.includes(norm) || norm.includes(mNorm)) {
                        results.push({ material: m, type: 'contains' });
                    } else {
                        // overlap token: parole > 2 caratteri
                        var tokens  = norm.split(/\s+/).filter(function(t) { return t.length > 2; });
                        var mTokens = mNorm.split(/\s+/).filter(function(t) { return t.length > 2; });
                        if (tokens.length && mTokens.length) {
                            var overlap = tokens.filter(function(t) {
                                return mTokens.some(function(mt) { return mt.includes(t) || t.includes(mt); });
                            });
                            if (overlap.length >= Math.ceil(Math.min(tokens.length, mTokens.length) * 0.5)) {
                                results.push({ material: m, type: 'similar' });
                            }
                        }
                    }
                });
                return results;
            }

            function renderSimilarityResults(similar) {
                var container = $('#similarityResults');
                if (!similar.length) {
                    container.html('');
                    return false; // nessun blocco
                }

                var hasExact = similar.some(function(r) { return r.type === 'exact'; });
                var html = '';

                if (hasExact) {
                    html += '<div class="alert alert-danger" style="padding:8px 12px; margin:0;">' +
                        '<span class="glyphicon glyphicon-ban-circle"></span> ' +
                        '<strong>Esiste già un ingrediente con questo nome:</strong><br>';
                    similar.filter(function(r) { return r.type === 'exact'; }).forEach(function(r) {
                        html += '<span class="label label-danger">' + r.material.label + ' (' + r.material.stock_type + ')</span> ';
                    });
                    html += '</div>';
                } else {
                    html += '<div class="alert alert-warning" style="padding:8px 12px; margin:0;">' +
                        '<span class="glyphicon glyphicon-exclamation-sign"></span> ' +
                        '<strong>Ingredienti simili già esistenti — assicurati di non duplicare:</strong><br>';
                    similar.forEach(function(r) {
                        var badge = r.type === 'contains' ? 'label-warning' : 'label-info';
                        html += '<span class="label ' + badge + '">' + r.material.label + ' (' + r.material.stock_type + ')</span> ';
                    });
                    html += '</div>';
                }

                container.html(html);
                return hasExact;
            }

            function updateCreateButton() {
                var label     = $('#newMaterialLabel').val().trim();
                var stockType = $('#newMaterialStockType').val();
                var similar   = findSimilarMaterials(label);
                var hasExact  = renderSimilarityResults(similar);
                var canCreate = label.length > 0 && stockType && !hasExact;
                $('#btnCreateMaterial').prop('disabled', !canCreate);
            }

            // Apri modal
            $(document).on('click', '.btn-open-new-material', function (e) {
                e.preventDefault();
                newMaterialForProductId = $(this).data('product-id');
                var productName = $(this).data('product-name');

                // Reset form
                $('#newMaterialLabel').val('');
                $('#newMaterialStockType').val('');
                $('#newMaterialThreshold').val('');
                $('#similarityResults').html('');
                $('#newMaterialError').hide();
                $('#btnCreateMaterial').prop('disabled', true);

                // Contesto prodotto
                $('#newMaterialContext').text(productName);

                // Pre-compila unità in base all'unità della fattura → unità base sistema (kg, cl, pz)
                var unitVal = $('.qty-unit[data-product-id="' + newMaterialForProductId + '"]').val();
                var unitMap = { 'pz': 'pz', 'g': 'kg', 'kg': 'kg', 'ml': 'cl', 'cl': 'cl', 'l': 'cl' };
                if (unitVal && unitMap[unitVal]) {
                    $('#newMaterialStockType').val(unitMap[unitVal]);
                }

                $('#newMaterialModal').modal('show');
                setTimeout(function() { $('#newMaterialLabel').focus(); }, 400);
            });

            // Live similarity check
            $('#newMaterialLabel, #newMaterialStockType').on('input change', updateCreateButton);

            // Crea ingrediente
            $('#btnCreateMaterial').on('click', function () {
                var label     = $('#newMaterialLabel').val().trim();
                var stockType = $('#newMaterialStockType').val();
                var threshold = $('#newMaterialThreshold').val();

                var btn = $(this);
                btn.prop('disabled', true).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Creazione...');
                $('#newMaterialError').hide();

                $.ajax({
                    url: storeUrl,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    data: {
                        label:           label,
                        stock:           0,
                        stock_type:      stockType,
                        alert_threshold: threshold || null,
                    },
                    success: function (response) {
                        var newMaterial = response.item;

                        // Aggiungi alla lista in memoria
                        newMaterial.stock_type_label = { 'pz': 'Pezzo', 'kg': 'Kilogrammi (kg)', 'cl': 'Centilitri (cl)' }[newMaterial.stock_type] || newMaterial.stock_type;
                        materials.push(newMaterial);

                        // Aggiungi l'opzione a tutti i select della pagina
                        var newOption = '<option value="' + newMaterial.id + '" data-stock-type="' + newMaterial.stock_type + '">' +
                            newMaterial.label + ' (0 ' + newMaterial.stock_type + ')' +
                            '</option>';
                        $('.material-select').each(function () {
                            // Inserisci dopo "IGNORA MAPPATURA"
                            $(this).find('option[value="0"]').after(newOption);
                        });

                        // Reinizializza Select2 su tutti i select
                        $('.material-select').select2('destroy').select2({
                            placeholder: '-- Seleziona Materiale --',
                            allowClear: true,
                            width: '100%',
                            language: {
                                noResults: function () { return "Nessun materiale trovato"; },
                                searching: function () { return "Ricerca in corso..."; }
                            }
                        });

                        // Auto-seleziona nel select della riga che ha aperto il modal
                        if (newMaterialForProductId) {
                            var $select = $('.material-select[data-product-id="' + newMaterialForProductId + '"]');
                            $select.val(newMaterial.id).trigger('change');
                        }

                        $('#newMaterialModal').modal('hide');
                    },
                    error: function (xhr) {
                        var msg = 'Errore durante la creazione.';
                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.errors && xhr.responseJSON.errors.label) {
                                msg = xhr.responseJSON.errors.label[0];
                            } else if (xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                        }
                        $('#newMaterialError').text(msg).show();
                        btn.prop('disabled', false).html('<span class="glyphicon glyphicon-plus"></span> Crea Ingrediente');
                    }
                });
            });
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

        /* ── Quantità control ── */
        .qty-control {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .qty-invoice-line {
            font-size: 12px;
            color: #888;
        }

        .qty-fields {
            display: flex;
            align-items: flex-end;
            gap: 5px;
        }

        .qty-field {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .qty-field-label {
            font-size: 10px;
            color: #aaa;
            margin-bottom: 2px;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .qty-field .form-control {
            height: 30px;
            padding: 2px 5px;
            font-size: 13px;
            text-align: center;
        }

        .qty-n    { width: 50px !important; }
        .qty-size { width: 60px !important; }
        .qty-unit { width: 55px !important; }

        .qty-op {
            font-size: 16px;
            color: #ccc;
            padding-bottom: 4px;
        }

        /* ── Badge risultato ── */
        .qty-result-block {
            border-radius: 5px;
            padding: 4px 12px;
            font-size: 20px;
            font-weight: bold;
            min-width: 70px;
            text-align: center;
            line-height: 1.3;
            transition: background 0.2s;
        }

        .qty-result-empty {
            background: #e9e9e9;
            color: #aaa;
        }

        .qty-result-ok {
            background: #337ab7;
            color: #fff;
        }

        .qty-result-error {
            background: #d9534f;
            color: #fff;
        }

        .qty-conversion-hint {
            font-size: 11px;
            margin-top: -2px;
            min-height: 16px;
        }

        .qty-detect-hint {
            margin-top: 3px;
        }

        .btn-open-new-material {
            font-size: 12px;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-open-new-material:hover {
            text-decoration: underline;
        }

        /* ── Barra fissa in fondo ── */
        body { padding-bottom: 85px; }

        .mapping-fixed-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            background: #fff;
            border-top: 2px solid #e7eaec;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.08);
        }

        .mapping-fixed-bar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px;
        }

        .mapping-fixed-left .btn {
            font-size: 16px;
            padding: 8px 18px;
        }

        .mapping-fixed-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .mapping-progress-label {
            font-size: 18px;
            white-space: nowrap;
        }

        .mapping-progress-counts strong {
            font-size: 22px;
            color: #333;
        }

        .mapping-progress-bar-wrap {
            width: 168px;
            height: 10px;
            background: #e9e9e9;
            border-radius: 4px;
            overflow: hidden;
        }

        .mapping-fixed-right .btn-primary {
            font-size: 16px;
            padding: 8px 20px;
        }

        .mapping-progress-bar-fill {
            height: 100%;
            background: #337ab7;
            border-radius: 4px;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .mapping-progress-bar-fill.mapping-progress-bar-done {
            background: #1ab394;
        }
    </style>
@endsection
