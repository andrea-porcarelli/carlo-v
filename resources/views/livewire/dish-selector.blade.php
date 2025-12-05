<div class="control-panel">
    <h5 class="section-title">
        <i class="fas fa-book-open me-2"></i> MENU
    </h5>

    @foreach($categories as $category)
        <div class="menu-category">
            <h6 class="category-header {{ $selectedCategory == $category->id ? 'active' : '' }}"
                wire:click="selectCategory({{ $category->id }})"
                data-category="{{ strtolower($category->label) }}"
                style="cursor: pointer;">
                <span>{{ strtoupper($category->label) }}</span>
                <i class="fas fa-chevron-{{ $selectedCategory == $category->id ? 'down' : 'right' }} category-arrow"></i>
            </h6>

            <div class="category-items" style="display: {{ $selectedCategory == $category->id ? 'block' : 'none' }};">
                <!-- Campo di ricerca -->
                <div class="mb-1 px-2">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        class="form-control form-control-sm"
                        placeholder="Cerca piatto..."
                        style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 8px;">
                </div>

                <!-- Lista piatti -->
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
                    <div class="text-center py-3" style="color: #6c757d; font-size: 0.9rem;">
                        <i class="fas fa-search me-2"></i>
                        @if($search)
                            Nessun piatto trovato per "{{ $search }}"
                        @else
                            Nessun piatto disponibile
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
