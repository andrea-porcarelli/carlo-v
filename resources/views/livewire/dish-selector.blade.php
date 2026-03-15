<div style="display:flex; height:100%; gap:0; overflow:hidden;">

    <!-- Sinistra/Centro: campo ricerca + griglia piatti -->
    <div class="ds-dishes-area">
        <div class="ds-search">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                class="form-control form-control-sm"
                placeholder="Cerca piatto..."
                style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 8px;">
        </div>

        <div class="ds-dishes-grid">
            @forelse($dishes as $dish)
                <div class="menu-item"
                     data-item="{{ $dish->label }}"
                     data-price="{{ number_format($dish->price, 2, '.', '') }}"
                     data-dish-id="{{ $dish->id }}"
                     style="cursor: pointer;">
                    <span class="menu-item-name">{{ $dish->label }}</span>
                    <span class="menu-item-price">€{{ number_format($dish->price, 2) }}</span>
                </div>
            @empty
                <div class="text-center py-5" style="color: #6c757d; font-size: 0.95rem; grid-column: 1/-1;">
                    @if(!$selectedCategory)
                        <i class="fas fa-hand-point-right fa-2x mb-3 d-block" style="color:#dee2e6;"></i>
                        Seleziona una categoria
                    @elseif($search)
                        <i class="fas fa-search fa-2x mb-3 d-block" style="color:#dee2e6;"></i>
                        Nessun piatto trovato per "{{ $search }}"
                    @else
                        <i class="fas fa-utensils fa-2x mb-3 d-block" style="color:#dee2e6;"></i>
                        Nessun piatto disponibile
                    @endif
                </div>
            @endforelse
        </div>
    </div>

    <!-- Destra: lista categorie -->
    <div class="ds-categories-col">
        @foreach($categories as $category)
            <button class="ds-category-btn {{ $selectedCategory == $category->id ? 'active' : '' }}"
                    wire:click="selectCategory({{ $category->id }})"
                    @if($category->color)
                        style="{{ $selectedCategory == $category->id ? 'background:' . $category->color . ';' : 'border-left:10px solid ' . $category->color . ';' }}"
                    @endif>
                {{ strtoupper($category->label) }}
            </button>
        @endforeach
    </div>

</div>
