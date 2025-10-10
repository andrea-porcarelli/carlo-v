<div class="dish-materials-manager">
    {{-- Sezione Ricerca --}}
    <div class="form-group">
        <label for="search">Cerca Ingrediente</label>
        <div style="position: relative;">
            <input
                type="text"
                id="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Digita almeno 2 caratteri..."
                class="form-control"
            >

            {{-- Risultati Ricerca --}}
            @if(count($searchResults) > 0)
                <div class="list-group" style="position: absolute; z-index: 1000; width: 100%; margin-top: 2px; max-height: 300px; overflow-y: auto; background: #FFF">
                    @foreach($searchResults as $result)
                        <button
                            type="button"
                            wire:click="addMaterial({{ $result['id'] }})"
                            class="list-group-item list-group-item-action"
                        >
                            <div class="font-weight-bold">{{ $result['label'] }}</div>
                            <small class="text-muted">{{ $this->getStockTypeLabel($result['stock_type']) }}</small>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Lista Ingredienti Selezionati --}}
    <div class="form-group">
        <label>Ingredienti Selezionati ({{ count($selectedMaterials) }})</label>

        @if(count($selectedMaterials) > 0)
            <div class="border rounded p-2">
                @foreach($selectedMaterials as $materialId => $material)
                    <div class="card mb-2">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                {{-- Nome Ingrediente --}}
                                <div class="col-md-5">
                                    <div class="font-weight-bold">{{ $material['label'] }}</div>
                                    <small class="text-muted">{{ $this->getStockTypeLabel($material['stock_type']) }}</small>
                                </div>

                                {{-- Input Quantità --}}
                                <div class="col-md-5">
                                    <div class="input-group input-group-sm">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            wire:model.blur="selectedMaterials.{{ $materialId }}.quantity"
                                            placeholder="Quantità"
                                            class="form-control @error('selectedMaterials.' . $materialId . '.quantity') is-invalid @enderror"
                                            required

                                        >
                                    </div>
                                    @error('selectedMaterials.' . $materialId . '.quantity')
                                    <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- Pulsante Rimuovi --}}
                                <div class="col-md-2 text-right">
                                    <button
                                        type="button"
                                        wire:click="removeMaterial({{ $materialId }})"
                                        class="btn btn-sm btn-outline-danger"
                                        title="Rimuovi"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-light text-center" role="alert">
                <p class="mb-0">Nessun ingrediente selezionato. Usa la ricerca per aggiungerne.</p>
            </div>
        @endif
    </div>

    {{-- Campo Hidden per il Submit del Form --}}
    <input type="hidden" name="materials" id="materials_data" form="update-or-create-element" value="{{ $this->getMaterialsJson() }}">
</div>
