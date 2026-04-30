<div style="display:flex; flex-direction:column; height:100%; overflow:hidden;">

    <!-- Strip categorie scrollabile orizzontalmente -->
    <div class="dsm-categories-strip">
        @foreach($categories as $category)
            <button class="dsm-cat-btn {{ $selectedCategory == $category->id ? 'active' : '' }}"
                    wire:click="selectCategory({{ $category->id }})"
                    @if($category->color)
                        style="{{ $selectedCategory == $category->id
                            ? 'background:' . $category->color . '; border-color:' . $category->color . ';'
                            : 'border-color:' . $category->color . '; color:' . $category->color . ';' }}"
                    @endif>
                {{ strtoupper($category->label) }}
            </button>
        @endforeach
    </div>

    <!-- Lista piatti verticale scrollabile -->
    <div class="dsm-dishes-list">
        @forelse($dishes as $dish)
            <div class="dsm-dish-row menu-item"
                 wire:key="dish-{{ $dish->id }}"
                 data-item="{{ $dish->label }}"
                 data-price="{{ number_format($dish->price, 2, '.', '') }}"
                 data-dish-id="{{ $dish->id }}">
                <span class="dsm-dish-name">{{ $dish->print_name }}</span>
            </div>
        @empty
            <div class="dsm-empty">
                @if(!$selectedCategory)
                    <i class="fas fa-hand-point-up"></i>
                    <p>Seleziona una categoria</p>
                @else
                    <i class="fas fa-utensils"></i>
                    <p>Nessun piatto disponibile</p>
                @endif
            </div>
        @endforelse
    </div>

</div>
