<li class="{{ (Request::is('backoffice/suppliers*') || Request::is('backoffice/invoices*')) ? 'active' : '' }}">
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
        <li class="{{ (Request::is('backoffice/invoices*')) ? 'active' : '' }}">
            <a href="{{ route('invoices.index') }}">
                <i class="fas fa-file-alt"></i> Ordini / Fatture
            </a>
        </li>
    </ul>
</li>
