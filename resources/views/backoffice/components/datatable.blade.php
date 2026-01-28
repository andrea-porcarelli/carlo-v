<div class="actions">
    @if (in_array('edit', $options))
        <a href="{{ route( ($route ?? $model) . '.show', $item->id) }}" title="Modifica">
            <button class="btn  btn-xs btn-info white">
                <span class="fa fa-edit"></span>
            </button>
        </a>
    @endif
    @if (in_array('remove', $options))
        <button
            class="btn btn-xs btn-danger btn-remove"
            title="Elimina"
            data-model="{{ $model }}"
            data-id="{{ $item->id }}"
        >
            <span class="fa fa-trash"></span>
        </button>
    @endif
    @if (in_array('status', $options))
        <button
            class="btn btn-xs {{ (!$item->is_active) ? 'btn-danger' : 'btn-success' }} btn-status"
            title="{{ (!$item->is_active) ? 'Attiva' : 'Disattiva' }}"
            data-model="{{ str_replace('.', '/', $route) }}"
            data-id="{{ $item->id }}"
        >
            <span class="fa {{ (!$item->is_active) ? 'fa-times' : 'fa-check' }}"></span>
        </button>
    @endif
    @if (in_array('mapping-product', $options))
        @if ($item->products()->whereDoesntHave('material')->count() > 0)
            <a href="{{ route('invoices.mapping_products', $item->id) }}">
                <button
                    class="btn btn-primary btn-xs"
                    title="Associa prodotti agli ingredienti"
                >
                    <i class="fas fa-seedling"></i>
                </button>
            </a>
        @endif
    @endif
    @if (in_array('add-stock', $options))
        <button
            class="btn btn-xs btn-warning btn-add-stock"
            title="Aggiungi giacenza"
            data-id="{{ $item->id }}"
            data-label="{{ $item->label }}"
            data-stock-type="{{ $item->stock_type }}"
        >
            <span class="fa fa-plus-circle"></span>
        </button>
    @endif
</div>
<small>ID: {{ $item->id }}</small>
