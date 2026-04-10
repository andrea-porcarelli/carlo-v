<div class="dish-materials-manager">

    {{-- Titolo sezione --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
        <strong style="font-size: 13px; text-transform: uppercase; letter-spacing: .5px; color: #676a6c;">
            <i class="fa fa-list-ul" style="margin-right: 5px;"></i> Composizione
        </strong>
        @if(count($selectedMaterials) > 0)
            <span class="badge" style="background: #1ab394; font-size: 11px;">
                {{ count($selectedMaterials) }} ingredient{{ count($selectedMaterials) === 1 ? 'e' : 'i' }}
            </span>
        @endif
    </div>

    {{-- Barra di ricerca --}}
    <div style="position: relative; margin-bottom: 12px;">
        <div class="input-group">
            <span class="input-group-addon" style="background: #fff; border-right: none;">
                <i class="fa fa-search text-muted"></i>
            </span>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cerca e aggiungi un ingrediente..."
                class="form-control"
                autocomplete="off"
                style="border-left: none; box-shadow: none;"
            >
        </div>
        @if(count($searchResults) > 0)
            <div style="position: absolute; z-index: 1000; width: 100%; background: #fff; border: 1px solid #ddd; border-top: none; border-radius: 0 0 3px 3px; box-shadow: 0 6px 12px rgba(0,0,0,0.12);">
                @foreach($searchResults as $result)
                    <div
                        wire:click="addMaterial({{ $result['id'] }})"
                        style="padding: 9px 14px; cursor: pointer; border-bottom: 1px solid #f4f4f4; display: flex; justify-content: space-between; align-items: center;"
                        onmouseover="this.style.background='#f8f8f8'" onmouseout="this.style.background='#fff'"
                    >
                        <div>
                            <i class="fa fa-plus-circle" style="color: #1ab394; margin-right: 6px; font-size: 11px;"></i>
                            <strong style="font-size: 13px;">{{ $result['label'] }}</strong>
                        </div>
                        <span class="label label-default" style="font-size: 10px;">{{ $result['stock_type'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Lista ingredienti --}}
    @if(count($selectedMaterials) > 0)
        <div style="border: 1px solid #e7eaec; border-radius: 3px; overflow: hidden;">
            @foreach($selectedMaterials as $materialId => $material)
                @php
                    $unitType   = $material['unit_type'] ?? $material['stock_type'];
                    $qty        = (float)($material['quantity'] ?? 0);
                    $baseQty    = $qty > 0 ? $this->getBaseQuantity($qty, $unitType) : null;
                    $isDiffUnit = $unitType !== $material['stock_type'];
                    $isLast     = $loop->last;
                @endphp
                <div style="display: flex; align-items: center; padding: 8px 10px; gap: 8px; background: #fff; {{ $isLast ? '' : 'border-bottom: 1px solid #f0f0f0;' }}">

                    {{-- Numero / indice --}}
                    <span style="flex-shrink: 0; width: 20px; height: 20px; background: #e8f5f2; color: #1ab394; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700;">
                        {{ $loop->iteration }}
                    </span>

                    {{-- Nome ingrediente --}}
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $material['label'] }}
                        </div>
                        <div style="font-size: 11px; color: #999; margin-top: 1px;">
                            base:&nbsp;<strong>{{ $material['stock_type'] }}</strong>
                            @if($baseQty !== null && $isDiffUnit)
                                &nbsp;·&nbsp;
                                <span style="color: #1c84c6;">
                                    {{ number_format($baseQty, $baseQty < 1 ? 4 : 2) }}&nbsp;{{ $material['stock_type'] }}
                                </span>
                            @endif
                        </div>
                        @error('selectedMaterials.' . $materialId . '.quantity')
                            <div style="font-size: 11px; color: #ed5565;">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Quantità + Unità --}}
                    <div style="flex-shrink: 0; width: 140px;">
                        <div class="input-group input-group-sm">
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                wire:model.live="selectedMaterials.{{ $materialId }}.quantity"
                                placeholder="0"
                                class="form-control"
                                style="text-align: right;"
                                required
                            >
                            <span class="input-group-btn" style="width: auto;">
                                <select
                                    wire:model.live="selectedMaterials.{{ $materialId }}.unit_type"
                                    class="btn btn-default btn-sm"
                                    style="height: 30px; border: 1px solid #ccc; border-left: none; border-radius: 0 3px 3px 0; background: #f8f8f8; padding: 0 6px; cursor: pointer; font-size: 12px;"
                                >
                                    @foreach($this->getAvailableUnits($material['stock_type']) as $unit)
                                        <option value="{{ $unit }}" @selected($unitType === $unit)>{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </span>
                        </div>
                    </div>

                    {{-- Rimuovi --}}
                    <button
                        type="button"
                        wire:click="removeMaterial({{ $materialId }})"
                        class="btn btn-xs"
                        title="Rimuovi ingrediente"
                        style="flex-shrink: 0; width: 26px; height: 26px; padding: 0; border: 1px solid #e5e6e7; background: #fff; color: #ccc; border-radius: 50%; display: flex; align-items: center; justify-content: center;"
                        onmouseover="this.style.background='#ed5565'; this.style.color='#fff'; this.style.borderColor='#ed5565';"
                        onmouseout="this.style.background='#fff'; this.style.color='#ccc'; this.style.borderColor='#e5e6e7';"
                    >
                        <i class="fa fa-times" style="font-size: 10px;"></i>
                    </button>

                </div>
            @endforeach
        </div>
    @else
        <div style="text-align: center; padding: 28px 16px; border: 2px dashed #e7eaec; border-radius: 3px; color: #aaa;">
            <i class="fa fa-cutlery" style="font-size: 28px; display: block; margin-bottom: 10px; opacity: 0.35;"></i>
            <div style="font-size: 13px;">Nessun ingrediente aggiunto</div>
            <div style="font-size: 11px; margin-top: 4px;">Cerca un ingrediente qui sopra per iniziare</div>
        </div>
    @endif

    {{-- Campo hidden per il submit del form --}}
    <input type="hidden" name="materials" id="materials_data" form="update-or-create-element" value="{{ $this->getMaterialsJson() }}">

</div>
