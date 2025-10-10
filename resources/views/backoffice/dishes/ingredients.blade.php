<ul class="dish-ingredients">
    @foreach($dish->materials as $ingredient)
        <li>
            <b>{{ $ingredient->pivot->quantity }} {{ $ingredient->stock_type }}</b>
            <span>{{ $ingredient->label }}</span>
        </li>
    @endforeach
</ul>
