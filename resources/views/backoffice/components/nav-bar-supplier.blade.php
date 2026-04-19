<li class="{{ (Request::is('backoffice/suppliers*') || Request::is('backoffice/invoices*') || Request::is('backoffice/mapping-products*')) ? 'active' : '' }}">
    <a href="#">
        <i class="fas fa-shipping-fast"></i>
        <span class="nav-label">Fornitori</span>
        <i class="far fa-arrow-alt-circle-down"></i>
    </a>
    <ul class="nav nav-second-level collapse">
        <li class="{{ (Request::is('backoffice/suppliers')) ? 'active' : '' }}">
            <a href="{{ route('suppliers.index') }}">
                <i class="fas fa-box-open"></i> Gestione
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/invoices')) ? 'active' : '' }}">
            <a href="{{ route('invoices.index') }}">
                <i class="fas fa-file-alt"></i> Fatture
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/invoices/to-map')) ? 'active' : '' }}">
            <a href="{{ route('invoices.to-map') }}">
                <i class="fas fa-file-alt"></i> Da mappare
                @if(($productsToMapCount ?? 0) > 0)
                    <span class="badge badge-warning">{{ $productsToMapCount }}</span>
                @endif
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/invoices/to-import')) ? 'active' : '' }}">
            <a href="{{ route('invoices.to-import') }}">
                <i class="fas fa-file-alt"></i> Da importare
                @if(($productsToImportCount ?? 0) > 0)
                    <span class="badge badge-primary">{{ $productsToImportCount }}</span>
                @endif
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/suppliers/product-comparison*')) ? 'active' : '' }}">
            <a href="{{ route('suppliers.product-comparison') }}">
                <i class="fas fa-balance-scale"></i> Comparazione
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/mapping-products*')) ? 'active' : '' }}">
            <a href="{{ route('mapping-products.index') }}">
                <i class="fas fa-map-marked-alt"></i> Mappature
            </a>
        </li>
    </ul>
</li>
