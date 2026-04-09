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
    @if (in_array('pdf-invoice', $options) && $item->filename)
        <a href="{{ route('invoices.pdf', $item->id) }}" target="_blank" title="Scarica PDF fattura">
            <button class="btn btn-xs btn-danger">
                Mostra fattura
            </button>
        </a>
    @endif
    @if (in_array('ignore-invoice', $options))
        <button
            class="btn btn-xs {{ $item->ignored_at ? 'btn-warning' : 'btn-default' }} btn-toggle-ignore-invoice"
            title="{{ $item->ignored_at ? 'Ripristina fattura' : 'Ignora fattura' }}"
            data-id="{{ $item->id }}"
            data-url="{{ route('invoices.toggle-ignore', $item->id) }}"
        >
            <span class="fa {{ $item->ignored_at ? 'fa-undo' : 'fa-eye-slash' }}"></span>
            Ignora fattura
        </button>
    @endif
    @if (in_array('mapping-product', $options))
        <a href="{{ route('invoices.mapping_products', $item->id) }}">
            <button
                class="btn btn-primary btn-xs"
                title="Associa prodotti agli ingredienti"
            >
                <i class="fas fa-seedling"></i> Mappa prodotti
            </button>
        </a>
    @endif
    @if (in_array('import-stock', $options))
        @php
        $to_map = $item->products()->whereDoesntHave('material', function ($query) {
            $query->whereColumn('mapping_products.quantity_multiplier', 'supplier_invoice_products.quantity_multiplier')
                ->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
        })->where('ignore_mapping', 0)->count();
        $to_import = $item->products()->where('ignore_mapping', 0)->whereDoesntHave('stock')->count();
        @endphp
        @if($to_map == 0 && $to_import > 0)
            <button
                class="btn btn-primary btn-xs btn-import-stock"
                title="Importa giacenze dei prodotti mappati"
                data-id="{{ $item->id }}"
            >
                <i class="fas fa-seedling"></i> Importa giacenze
            </button>
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
