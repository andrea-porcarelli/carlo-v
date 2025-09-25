<div class="actions">
    @if (in_array('edit', $options))
        <a href="{{ route( ($path ?? $model) . '.show', $item->id) }}" title="Modifica">
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
            data-model="{{ $route ?? ($path ?? $model) }}"
            data-id="{{ $item->id }}"
        >
            <span class="fa {{ (!$item->is_active) ? 'fa-times' : 'fa-check' }}"></span>
        </button>
    @endif

    @if (in_array('order', $options))
        <button
            class="btn btn-xs btn-info white sort-row"
            title="Ordina"
            data-id="{{ $item->id }}"
        >
            <i class="fas fa-sort"></i>
        </button>
    @endif
    @if (in_array('impersonate', $options) && $item->id !== Auth::id() && $role === 'admin' && $item->is_active)
        <a href="{{ route('impersonate', $item->id) }}">
            <button
                class="btn btn-xs"
                title="Impersonifica utente"
                data-id="{{ $item->id }}"
            >
                <i class="fas fa-user-secret"></i>
            </button>
        </a>
    @endif
    @if (in_array('invoice', $options))
        <button
            class="btn btn-primary btn-xs btn-show-invoices"
            title="Mostra fatture"
            data-id="{{ $item->id }}"
        >
            <i class="fas fa-file-invoice-dollar"></i>
        </button>
    @endif
    @if (in_array('code', $options))
        @if ($item->has_warehouse)
            <button
                class="btn btn-primary btn-xs btn-load-code"
                title="Genera codici prodotti"
                data-id="{{ $item->id }}"
            >
                <i class="fas fa-barcode"></i>
            </button>
        @endif
    @endif
    @if (in_array('invoices', $options))
        @if ($item->has_warehouse)
            <button
                class="btn btn-primary btn-xs btn-product-invoices"
                title="Visualizza fatture di acquisto"
                data-id="{{ $item->id }}"
            >
                <i class="fas fa-file-pdf"></i>
            </button>
        @endif
    @endif
</div>
<small>ID: {{ $item->id }}</small>
