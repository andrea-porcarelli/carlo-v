@extends('backoffice.layout', ['title' => 'Associa prodotti agli ingredienti'])
@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture ricevute', 'href' => route('external-invoices.index')],
        'level_2' => ['label' => 'Associa prodotti agli ingredienti'],
    ])
@endsection
@section('main-content')
    <div class="row">
        <div class="col-lg-12">

            {{-- Info fattura --}}
            <div class="panel panel-default">
                <div class="panel-body" style="padding: 10px 20px;">
                    <div class="row">
                        <div class="col-sm-4">
                            <strong>Fattura n°</strong> {{ $invoice->number ?? '-' }}
                            &nbsp;&middot;&nbsp;
                            <strong>Data</strong> {{ $invoice->date ? $invoice->date->format('d/m/Y') : '-' }}
                        </div>
                        <div class="col-sm-4">
                            @if($invoice->parties->where('type','supplier')->first())
                                @php $party = $invoice->parties->where('type','supplier')->first() @endphp
                                <strong>Fornitore:</strong>
                                {{ $party->company_name ?: trim($party->first_name.' '.$party->last_name) }}
                                @if($party->vat_number)
                                    <small class="text-muted">({{ $party->vat_number }})</small>
                                @endif
                            @endif
                        </div>
                        <div class="col-sm-4 text-right">
                            <strong>Totale:</strong>
                            {{ number_format($invoice->total_amount ?? 0, 2, ',', '.') }} €
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-body">
                    <form id="mappingForm">
                        <div class="row">
                            <div class="col-xs-12">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead>
                                        <tr>
                                            <th width="25%">Prodotto Fattura</th>
                                            <th width="28%" class="text-center">Quantità da caricare</th>
                                            <th width="8%" class="text-right">Prezzo unit.</th>
                                            <th width="30%">Ingrediente Associato</th>
                                            <th width="9%" class="text-center">Stato</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($linesToMap as $line)
                                            <tr data-line-id="{{ $line->id }}">
                                                <td>
                                                    <strong>{{ $line->description }}</strong>
                                                    <div class="qty-detect-hint" data-line-id="{{ $line->id }}"></div>
                                                    <div><small class="text-muted">{{ $line->quantity }} {{ $line->unit_of_measure }}</small></div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="qty-control">
                                                        <div class="qty-invoice-line">
                                                            <span class="text-muted small">In fattura:</span>
                                                            <strong>{{ $line->quantity }}</strong>
                                                        </div>
                                                        <div class="qty-fields">
                                                            <div class="qty-field">
                                                                <label class="qty-field-label">Colli</label>
                                                                <input type="number"
                                                                       class="form-control qty-n"
                                                                       data-line-id="{{ $line->id }}"
                                                                       value="1"
                                                                       min="1"
                                                                       step="1">
                                                            </div>
                                                            <span class="qty-op">×</span>
                                                            <div class="qty-field">
                                                                <label class="qty-field-label">Dimensione</label>
                                                                <input type="number"
                                                                       class="form-control qty-size"
                                                                       data-line-id="{{ $line->id }}"
                                                                       value=""
                                                                       min="0.001"
                                                                       step="any"
                                                                       placeholder="es. 75">
                                                            </div>
                                                            <div class="qty-field">
                                                                <label class="qty-field-label">Unità</label>
                                                                <select class="form-control qty-unit" data-line-id="{{ $line->id }}">
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
                                                               name="multipliers[{{ $line->id }}]"
                                                               class="qty-multiplier"
                                                               data-line-id="{{ $line->id }}"
                                                               data-base-qty="{{ $line->quantity }}"
                                                               value="{{ $line->quantity_multiplier ?? 1 }}">
                                                        <div class="qty-result-block qty-result-{{ $line->id }} qty-result-empty">
                                                            <span class="qty-real" data-line-id="{{ $line->id }}">—</span>
                                                            <span class="unit-label unit-label-{{ $line->id }}"></span>
                                                        </div>
                                                        <div class="qty-conversion-hint qty-conversion-{{ $line->id }}"></div>
                                                    </div>
                                                </td>
                                                <td class="text-right">
                                                    € {{ number_format($line->unit_price ?? 0, 2, ',', '.') }}
                                                </td>
                                                <td>
                                                    <select
                                                        name="mappings[{{ $line->id }}]"
                                                        class="form-control material-select"
                                                        data-line-id="{{ $line->id }}"
                                                        required>
                                                        <option value="">-- Seleziona Materiale --</option>
                                                        <option value="0">>> IGNORA MAPPATURA <<</option>
                                                        @foreach($materials as $material)
                                                            <option
                                                                value="{{ $material->id }}"
                                                                data-stock-type="{{ $material->stock_type }}">
                                                                {{ $material->label }}
                                                                ({{ $material->stock }} {{ $material->stock_type }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:5px;">
                                                        <small class="text-muted">
                                                            <span class="selected-info-{{ $line->id }}"></span>
                                                        </small>
                                                        <a href="#"
                                                           class="btn-open-new-material text-success"
                                                           data-line-id="{{ $line->id }}"
                                                           data-product-name="{{ $line->description }}"
                                                           title="Crea nuovo ingrediente">
                                                            <span class="glyphicon glyphicon-plus-sign"></span>
                                                            <small>Nuovo ingrediente</small>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="label label-warning">
                                                        <span class="glyphicon glyphicon-exclamation-sign"></span> Da mappare
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row" style="margin-top: 20px;">
                            <div class="col-xs-12">
                                <div class="clearfix">
                                    <div class="pull-left">
                                        <a href="{{ route('external-invoices.index') }}" class="btn btn-default">
                                            <span class="glyphicon glyphicon-arrow-left"></span> Indietro
                                        </a>
                                    </div>
                                    <div class="pull-right">
                                        <span style="margin-right: 15px; line-height: 34px; display: inline-block;" class="text-muted">
                                            <strong id="mappedCount">0</strong>
                                            su
                                            <strong>{{ $linesToMap->count() }}</strong>
                                            completi
                                        </span>
                                        <button type="button"
                                                class="btn btn-primary btn-store-mapping"
                                                disabled
                                                data-invoice-id="{{ $invoice->id }}">
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

    {{-- Modal nuovo ingrediente --}}
    <div class="modal fade" id="newMaterialModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">
                        <span class="glyphicon glyphicon-plus-sign text-success"></span> Nuovo Ingrediente
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" style="margin-bottom:15px;">
                        <span class="glyphicon glyphicon-tag"></span>
                        Prodotto in fattura: <strong id="newMaterialContext"></strong>
                    </div>
                    <div class="form-group">
                        <label>Nome ingrediente <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newMaterialLabel" placeholder="es. Prezzemolo" autocomplete="off">
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
                                <input type="number" class="form-control" id="newMaterialThreshold" min="0" step="any" placeholder="es. 100">
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
    var storeUrl  = '{{ route("restaurant.materials.index") }}';
    var saveUrl   = '{{ route("external-invoices.store-mapping", $invoice->id) }}';

    // ── Conversioni ──────────────────────────────────────────────────────────
    var conversionTable = {
        'kg': { 'kg': 1, 'g': 0.001 },
        'cl': { 'cl': 1, 'ml': 0.1, 'l': 100 },
        'pz': { 'pz': 1 },
    };

    function getConversionFactor(fromUnit, toUnit) {
        if (!fromUnit || !toUnit || fromUnit === toUnit) return 1;
        if (conversionTable[toUnit] && conversionTable[toUnit][fromUnit] !== undefined)
            return conversionTable[toUnit][fromUnit];
        return null;
    }

    // ── Auto-detect pattern ──────────────────────────────────────────────────
    function detectPattern(productName) {
        var nxm = productName.match(/(\d+)\s*[xX×]\s*(\d+(?:[.,]\d+)?)\s*(cl|ml|l|lt|g|gr|kg)?(?!\w)/i);
        if (nxm) {
            var unit = nxm[3] ? nxm[3].toLowerCase().replace('gr','g').replace('lt','l') : '';
            return { n: parseInt(nxm[1]), size: parseFloat(nxm[2].replace(',','.')), unit: unit };
        }
        var sized = productName.match(
            /(?:(g|gr|kg|lt?|ml|cl|l)\s*\.?\s*(\d+(?:[.,]\d+)?)|(\d+(?:[.,]\d+)?)\s*(g|gr|kg|lt?|ml|cl|l)(?!\w))/i
        );
        if (sized) {
            var unit2 = (sized[1]||sized[4]).toLowerCase().replace('gr','g').replace('lt','l');
            var size2 = parseFloat((sized[2]||sized[3]).replace(',','.'));
            return { n: 1, size: size2, unit: unit2 };
        }
        return null;
    }

    // ── Calcolo quantità ─────────────────────────────────────────────────────
    function computeAndUpdate(lineId) {
        var baseQty    = parseFloat($('.qty-multiplier[data-line-id="'+lineId+'"]').data('base-qty')) || 0;
        var n          = parseFloat($('.qty-n[data-line-id="'+lineId+'"]').val());
        var size       = parseFloat($('.qty-size[data-line-id="'+lineId+'"]').val());
        var unit       = $('.qty-unit[data-line-id="'+lineId+'"]').val();
        var materialId = $('.material-select[data-line-id="'+lineId+'"]').val();

        var resultBlock = $('.qty-result-'+lineId);
        var realSpan    = $('.qty-real[data-line-id="'+lineId+'"]');
        var unitLabel   = $('.unit-label-'+lineId);
        var convHint    = $('.qty-conversion-'+lineId);

        if (materialId === '0') {
            resultBlock.attr('class','qty-result-block qty-result-'+lineId+' qty-result-empty');
            realSpan.text('—'); unitLabel.text(''); convHint.html('');
            updateRowStatus(lineId); updateGlobalSave(); return;
        }
        if (!n || !size || !unit) {
            resultBlock.attr('class','qty-result-block qty-result-'+lineId+' qty-result-empty');
            realSpan.text('—'); unitLabel.text(''); convHint.html('');
            updateRowStatus(lineId); updateGlobalSave(); return;
        }

        var material      = materialId ? materials.find(function(m){ return m.id == materialId; }) : null;
        var ingredientUnit = material ? material.stock_type : null;
        var convFactor    = 1;
        var convOk        = true;

        if (ingredientUnit) {
            var factor = getConversionFactor(unit, ingredientUnit);
            if (factor === null) { convOk = false; } else { convFactor = factor; }
        }

        if (!convOk) {
            resultBlock.attr('class','qty-result-block qty-result-'+lineId+' qty-result-error');
            realSpan.text('!'); unitLabel.text('');
            convHint.html('<small class="text-danger"><span class="glyphicon glyphicon-warning-sign"></span> Unità incompatibili ('+ingredientUnit+')</small>');
            $('.qty-multiplier[data-line-id="'+lineId+'"]').val('');
            updateRowStatus(lineId); updateGlobalSave(); return;
        }

        var finalMultiplier = n * size * convFactor;
        var realQty = Math.round(baseQty * finalMultiplier * 1000) / 1000;

        $('.qty-multiplier[data-line-id="'+lineId+'"]').val(finalMultiplier);
        resultBlock.attr('class','qty-result-block qty-result-'+lineId+' qty-result-ok');
        realSpan.text(realQty);
        unitLabel.text(ingredientUnit ? ' '+ingredientUnit : ' '+unit);

        if (ingredientUnit && unit && unit !== ingredientUnit)
            convHint.html('<small class="text-muted">Conversione: '+unit+' → '+ingredientUnit+' (×'+convFactor+')</small>');
        else
            convHint.html('');

        updateRowStatus(lineId); updateGlobalSave();
    }

    function isRowComplete(lineId) {
        var materialId = $('.material-select[data-line-id="'+lineId+'"]').val();
        if (!materialId) return false;
        if (materialId === '0') return true;
        var n    = parseFloat($('.qty-n[data-line-id="'+lineId+'"]').val());
        var size = parseFloat($('.qty-size[data-line-id="'+lineId+'"]').val());
        var unit = $('.qty-unit[data-line-id="'+lineId+'"]').val();
        var mult = $('.qty-multiplier[data-line-id="'+lineId+'"]').val();
        return n > 0 && size > 0 && unit && mult;
    }

    function updateRowStatus(lineId) {
        var complete   = isRowComplete(lineId);
        var materialId = $('.material-select[data-line-id="'+lineId+'"]').val();
        var label      = $('tr[data-line-id="'+lineId+'"] .label');

        if (complete && materialId !== '0') {
            label.attr('class','label label-success').html('<span class="glyphicon glyphicon-ok-circle"></span> Mappato');
        } else if (materialId === '0') {
            label.attr('class','label label-default').html('<span class="glyphicon glyphicon-minus-sign"></span> Ignorato');
        } else if (materialId) {
            label.attr('class','label label-info').html('<span class="glyphicon glyphicon-pencil"></span> Incompleto');
        } else {
            label.attr('class','label label-warning').html('<span class="glyphicon glyphicon-exclamation-sign"></span> Da mappare');
        }
    }

    function updateGlobalSave() {
        var total    = $('.material-select').length;
        var complete = 0;
        $('.material-select').each(function() {
            if (isRowComplete($(this).data('line-id'))) complete++;
        });
        $('#mappedCount').text(complete);
        $('.btn-store-mapping').prop('disabled', complete < total);
    }

    // ── Auto-detect al caricamento ───────────────────────────────────────────
    $('tr[data-line-id]').each(function() {
        var lineId      = $(this).data('line-id');
        var productName = $(this).find('td:first strong').text();
        var detected    = detectPattern(productName);
        if (detected) {
            if (detected.n && detected.n > 1) $('.qty-n[data-line-id="'+lineId+'"]').val(detected.n);
            if (detected.size)                $('.qty-size[data-line-id="'+lineId+'"]').val(detected.size);
            if (detected.unit)                $('.qty-unit[data-line-id="'+lineId+'"]').val(detected.unit);

            var parts = [];
            if (detected.n > 1) parts.push(detected.n+' colli');
            if (detected.size)  parts.push(detected.size+(detected.unit||''));
            if (parts.length)
                $('.qty-detect-hint[data-line-id="'+lineId+'"]').html(
                    '<small class="text-success"><span class="glyphicon glyphicon-flash"></span> Rilevato: '+parts.join(' × ')+'</small>'
                );
            computeAndUpdate(lineId);
        }
    });

    // ── Select2 ──────────────────────────────────────────────────────────────
    $('.material-select').select2({
        placeholder: '-- Seleziona Materiale --',
        allowClear: true, width: '100%',
        language: { noResults: function(){ return "Nessun materiale trovato"; }, searching: function(){ return "Ricerca in corso..."; } }
    });

    // ── Events ───────────────────────────────────────────────────────────────
    $(document).on('input change', '.qty-n, .qty-size, .qty-unit', function() {
        computeAndUpdate($(this).data('line-id'));
    });

    $('.material-select').on('change', function() {
        var lineId     = $(this).data('line-id');
        var materialId = $(this).val();
        var material   = (materialId && materialId !== '0') ? materials.find(function(m){ return m.id == materialId; }) : null;

        if (material && !$('.qty-unit[data-line-id="'+lineId+'"]').val()) {
            var st = material.stock_type;
            if (['g','kg','ml','cl','l','pz'].includes(st))
                $('.qty-unit[data-line-id="'+lineId+'"]').val(st);
        }
        $('.selected-info-'+lineId).text(material ? material.stock_type_label : '');
        computeAndUpdate(lineId);
    });

    updateGlobalSave();

    // ── Salva mappatura ──────────────────────────────────────────────────────
    $('.btn-store-mapping').on('click', function() {
        var btn  = $(this).prop('disabled', true).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Salvataggio...');
        var data = $('#mappingForm').serializeArray();
        data.push({ name: '_token', value: '{{ csrf_token() }}' });

        $.ajax({
            url: saveUrl, method: 'POST',
            data: $.param(data),
            success: function(r) {
                if (r.success) {
                    window.location.href = '{{ route("external-invoices.index") }}';
                } else {
                    alert(r.message || 'Errore durante il salvataggio.');
                    btn.prop('disabled', false).html('<span class="glyphicon glyphicon-floppy-disk"></span> Salva Mappatura');
                }
            },
            error: function() {
                alert('Errore di rete.');
                btn.prop('disabled', false).html('<span class="glyphicon glyphicon-floppy-disk"></span> Salva Mappatura');
            }
        });
    });

    // ── Nuovo Ingrediente ────────────────────────────────────────────────────
    var newMaterialForLineId = null;

    function normalizeLabel(str) { return str.toLowerCase().trim().replace(/\s+/g,' '); }

    function findSimilarMaterials(label) {
        var norm = normalizeLabel(label);
        if (norm.length < 2) return [];
        var results = [];
        materials.forEach(function(m) {
            var mNorm = normalizeLabel(m.label);
            if (mNorm === norm) { results.push({ material: m, type: 'exact' }); }
            else if (mNorm.includes(norm) || norm.includes(mNorm)) { results.push({ material: m, type: 'contains' }); }
            else {
                var tokens  = norm.split(/\s+/).filter(function(t){ return t.length>2; });
                var mTokens = mNorm.split(/\s+/).filter(function(t){ return t.length>2; });
                if (tokens.length && mTokens.length) {
                    var overlap = tokens.filter(function(t){ return mTokens.some(function(mt){ return mt.includes(t)||t.includes(mt); }); });
                    if (overlap.length >= Math.ceil(Math.min(tokens.length, mTokens.length)*0.5))
                        results.push({ material: m, type: 'similar' });
                }
            }
        });
        return results;
    }

    function renderSimilarityResults(similar) {
        var container = $('#similarityResults');
        if (!similar.length) { container.html(''); return false; }
        var hasExact = similar.some(function(r){ return r.type==='exact'; });
        var html = '';
        if (hasExact) {
            html = '<div class="alert alert-danger" style="padding:8px 12px;margin:0;"><span class="glyphicon glyphicon-ban-circle"></span> <strong>Esiste già un ingrediente con questo nome:</strong><br>';
            similar.filter(function(r){ return r.type==='exact'; }).forEach(function(r){ html += '<span class="label label-danger">'+r.material.label+' ('+r.material.stock_type+')</span> '; });
            html += '</div>';
        } else {
            html = '<div class="alert alert-warning" style="padding:8px 12px;margin:0;"><span class="glyphicon glyphicon-exclamation-sign"></span> <strong>Ingredienti simili già esistenti:</strong><br>';
            similar.forEach(function(r){ var badge = r.type==='contains'?'label-warning':'label-info'; html += '<span class="label '+badge+'">'+r.material.label+' ('+r.material.stock_type+')</span> '; });
            html += '</div>';
        }
        container.html(html);
        return hasExact;
    }

    function updateCreateButton() {
        var label    = $('#newMaterialLabel').val().trim();
        var stockType = $('#newMaterialStockType').val();
        var similar  = findSimilarMaterials(label);
        var hasExact = renderSimilarityResults(similar);
        $('#btnCreateMaterial').prop('disabled', !(label.length > 0 && stockType && !hasExact));
    }

    $(document).on('click', '.btn-open-new-material', function(e) {
        e.preventDefault();
        newMaterialForLineId = $(this).data('line-id');
        $('#newMaterialLabel').val('');
        $('#newMaterialStockType').val('');
        $('#newMaterialThreshold').val('');
        $('#similarityResults').html('');
        $('#newMaterialError').hide();
        $('#btnCreateMaterial').prop('disabled', true);
        $('#newMaterialContext').text($(this).data('product-name'));

        var unitVal = $('.qty-unit[data-line-id="'+newMaterialForLineId+'"]').val();
        var unitMap = { 'pz':'pz','g':'kg','kg':'kg','ml':'cl','cl':'cl','l':'cl' };
        if (unitVal && unitMap[unitVal]) $('#newMaterialStockType').val(unitMap[unitVal]);

        $('#newMaterialModal').modal('show');
        setTimeout(function(){ $('#newMaterialLabel').focus(); }, 400);
    });

    $('#newMaterialLabel, #newMaterialStockType').on('input change', updateCreateButton);

    $('#btnCreateMaterial').on('click', function() {
        var btn       = $(this).prop('disabled', true).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Creazione...');
        var label     = $('#newMaterialLabel').val().trim();
        var stockType = $('#newMaterialStockType').val();
        var threshold = $('#newMaterialThreshold').val();

        $.ajax({
            url: storeUrl, method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: { label: label, stock: 0, stock_type: stockType, alert_threshold: threshold || null },
            success: function(response) {
                var nm = response.item;
                nm.stock_type_label = { 'pz':'Pezzo','kg':'Kilogrammi (kg)','cl':'Centilitri (cl)' }[nm.stock_type] || nm.stock_type;
                materials.push(nm);

                var newOption = '<option value="'+nm.id+'" data-stock-type="'+nm.stock_type+'">'+nm.label+' (0 '+nm.stock_type+')</option>';
                $('.material-select').each(function(){ $(this).find('option[value="0"]').after(newOption); });
                $('.material-select').select2('destroy').select2({
                    placeholder:'-- Seleziona Materiale --', allowClear:true, width:'100%',
                    language:{ noResults:function(){ return "Nessun materiale trovato"; }, searching:function(){ return "Ricerca in corso..."; } }
                });

                if (newMaterialForLineId) {
                    $('.material-select[data-line-id="'+newMaterialForLineId+'"]').val(nm.id).trigger('change');
                }
                $('#newMaterialModal').modal('hide');
            },
            error: function(xhr) {
                var msg = 'Errore durante la creazione.';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.errors && xhr.responseJSON.errors.label) msg = xhr.responseJSON.errors.label[0];
                    else if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
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
    .qty-control { display:flex; flex-direction:column; align-items:center; gap:5px; }
    .qty-invoice-line { font-size:12px; color:#888; }
    .qty-fields { display:flex; align-items:flex-end; gap:5px; }
    .qty-field { display:flex; flex-direction:column; align-items:center; }
    .qty-field-label { font-size:10px; color:#aaa; margin-bottom:2px; font-weight:normal; text-transform:uppercase; letter-spacing:.3px; }
    .qty-field .form-control { height:30px; padding:2px 5px; font-size:13px; text-align:center; }
    .qty-n    { width:50px !important; }
    .qty-size { width:60px !important; }
    .qty-unit { width:55px !important; }
    .qty-op   { font-size:16px; color:#ccc; padding-bottom:4px; }
    .qty-result-block { border-radius:5px; padding:4px 12px; font-size:20px; font-weight:bold; min-width:70px; text-align:center; line-height:1.3; transition:background .2s; }
    .qty-result-empty { background:#e9e9e9; color:#aaa; }
    .qty-result-ok    { background:#337ab7; color:#fff; }
    .qty-result-error { background:#d9534f; color:#fff; }
    .qty-conversion-hint { font-size:11px; margin-top:-2px; min-height:16px; }
    .btn-open-new-material { font-size:12px; text-decoration:none; white-space:nowrap; }
    .btn-open-new-material:hover { text-decoration:underline; }
</style>
@endsection
