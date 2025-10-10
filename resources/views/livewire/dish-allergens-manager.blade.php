<div class="dish-allergens-manager">
    <div class="form-group">
        <label>Allergeni ({{ count($selectedAllergens) }} selezionati)</label>

        @if(count($availableAllergens) > 0)
            <div class="border rounded p-3">
                <div class="row">
                    @foreach($availableAllergens as $allergen)
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="allergen_{{ $allergen['id'] }}"
                                    wire:change="toggleAllergen({{ $allergen['id'] }})"
                                    @if($this->isSelected($allergen['id'])) checked @endif
                                >
                                <label class="form-check-label" for="allergen_{{ $allergen['id'] }}">
                                    {{ $allergen['label'] }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle"></i> Nessun allergene disponibile nel sistema.
            </div>
        @endif
    </div>

    {{-- Campo Hidden per il Submit del Form --}}
    <input type="hidden" form="update-or-create-element" name="allergens" value="{{ json_encode($selectedAllergens) }}">
</div>
