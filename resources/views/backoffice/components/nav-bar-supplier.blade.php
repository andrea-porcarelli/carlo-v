<li class="{{ (Request::is('backoffice/suppliers*')) ? 'active' : '' }}">
    <a href="#">
        <i class="fas fa-shipping-fast"></i>
        <span class="nav-label">Fornitori</span>
        <i class="far fa-arrow-alt-circle-down"></i>
    </a>
    <ul class="nav nav-second-level collapse">
        <li class="{{ (Request::is('backoffice/suppliers')) ? 'active' : '' }}">
            <a href="{{ route('suppliers') }}">
                <i class="fas fa-box-open"></i> Gestione
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/suppliers/orders*')) ? 'active' : '' }}">
            <a href="{{ route('suppliers.orders') }}">
                <i class="fas fa-shopping-cart"></i> Ordini
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/suppliers/invoices*')) ? 'active' : '' }}">
            <a href="{{ route('suppliers.invoices') }}">
                <i class="fas fa-file-alt"></i> Fatture
            </a>
        </li>
    </ul>
</li>
