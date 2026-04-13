@extends('backoffice.layout', ['title' => 'Modifica Mappatura'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fornitori', 'url' => route('suppliers.index')],
        'level_2' => ['label' => 'Mappature', 'url' => route('mapping-products.index')],
        'level_3' => ['label' => 'Modifica: ' . $mappingProduct->product_name],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            <form id="mappingForm" action="{{ route('mapping-products.update', $mappingProduct->id) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- ── Dati mappatura ────────────────────────────────────────────── --}}
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fas fa-map-marked-alt"></i> Modifica Mappatura
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            {{-- Fornitore (read-only) --}}
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Fornitore</label>
                                    <div class="form-control-static">
                                        @if($mappingProduct->supplier)
                                            <span class="badge badge-info" style="background-color: #1c84c6; padding: 6px 12px; font-size: 12px;">
                                                {{ $mappingProduct->supplier->company_name }}
                                            </span>
                                        @else
                                            <span class="text-muted">(nessuno)</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Prodotto attuale (read-only) --}}
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Prodotto in fattura (attuale)</label>
                                    <div class="form-control-static">
                                        <code>{{ $mappingProduct->product_name }}</code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row m-t-md">
                            {{-- Nuovo nome prodotto --}}
                            @include('backoffice.components.form.input', [
                                'label'       => 'Nuovo nome prodotto',
                                'name'        => 'product_name',
                                'col'         => 6,
                                'required'    => true,
                                'value'       => $mappingProduct->product_name,
                                'placeholder' => 'Es: Olio EVO',
                            ])

                            {{-- Materiale --}}
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="material_id">Materiale <span class="text-danger">*</span></label>
                                    <select class="form-control" id="material_id" name="material_id" required>
                                        <option value="">-- Seleziona --</option>
                                        @foreach($materials as $material)
                                            <option value="{{ $material->id }}"
                                                {{ $mappingProduct->material_id === $material->id ? 'selected' : '' }}>
                                                {{ $material->label }} ({{ $material->stock_type }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row m-t-md">
                            {{-- Moltiplicatore quantità --}}
                            @include('backoffice.components.form.input', [
                                'label'       => 'Moltiplicatore quantità',
                                'name'        => 'quantity_multiplier',
                                'col'         => 6,
                                'type'        => 'number',
                                'step'        => '0.000001',
                                'value'       => $mappingProduct->quantity_multiplier,
                                'placeholder' => 'Es: 1.5 (opzionale)',
                            ])
                        </div>
                    </div>
                    <div class="panel-footer">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('mapping-products.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Torna alla lista
                            </a>
                            <div>
                                <button type="button" class="btn btn-danger" id="deleteBtn">
                                    <i class="fas fa-trash"></i> Elimina
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salva Modifiche
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
@endsection

@section('custom-script')
<script>
$(document).ready(function () {

    // ── Submit ────────────────────────────────────────────────────────────
    $('#mappingForm').on('submit', function (e) {
        e.preventDefault();
        const form   = $(this);
        const submit = form.find('button[type="submit"]');
        submit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvataggio...');

        $.ajax({
            url:  form.attr('action'),
            type: 'PUT',
            data: form.serialize(),
            success: function (res) {
                alert(res.message || 'Mappatura aggiornata con successo');
                window.location.href = '{{ route('mapping-products.index') }}';
            },
            error: function (xhr) {
                submit.prop('disabled', false).html('<i class="fas fa-save"></i> Salva Modifiche');
                let msg = "Errore nell'aggiornamento della mappatura";
                if (xhr.responseJSON?.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                } else if (xhr.responseJSON?.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            },
        });
    });

    // ── Elimina ───────────────────────────────────────────────────────────
    $('#deleteBtn').on('click', function () {
        if (!confirm('Sei sicuro di voler eliminare questa mappatura? Questa azione è irreversibile.')) return;

        $.ajax({
            url:     '/backoffice/mapping-products/{{ $mappingProduct->id }}',
            type:    'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                alert(res.message || 'Mappatura eliminata con successo');
                window.location.href = '{{ route('mapping-products.index') }}';
            },
            error: function (xhr) {
                alert(xhr.responseJSON?.message || "Errore nell'eliminazione della mappatura");
            },
        });
    });

});
</script>
@endsection
