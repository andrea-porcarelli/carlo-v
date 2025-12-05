<li class="{{ (Request::is('backoffice/restaurant*')) ? 'active' : '' }}">
    <a href="#">
        <i class="fas fa-utensils"></i>
        <span class="nav-label">Ristorante</span>
        <i class="far fa-arrow-alt-circle-down"></i>
    </a>
    <ul class="nav nav-second-level collapse">
        <li class="{{ (Request::is('backoffice/restaurant/sales*')) ? 'active' : '' }}">
            <a href="{{ route('restaurant.sales.index') }}">
                <i class="fas fa-cash-register"></i> Vendite
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/restaurant/sales/tables*')) ? 'active' : '' }}">
            <a href="{{ route('restaurant.sales.tables') }}">
                <i class="fas fa-cash-register"></i> Tavoli occupati
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/restaurant/dishes')) ? 'active' : '' }}">
            <a href="{{ route('restaurant.dishes.index') }}">
                <i class="fas fa-wine-bottle"></i> Piatti
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/restaurant/categories')) ? 'active' : '' }}">
            <a href="{{ route('restaurant.categories.index') }}">
                <i class="fas fa-sitemap"></i> Categorie
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/restaurant/materials')) ? 'active' : '' }}">
            <a href="{{ route('restaurant.materials.index') }}">
                <i class="fas fa-seedling"></i> Ingredienti
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/restaurant/allergens')) ? 'active' : '' }}">
            <a href="{{ route('restaurant.allergens.index') }}">
                <i class="fas fa-virus"></i> Allergeni
            </a>
        </li>
        <li class="{{ (Request::is('backoffice/restaurant/products')) ? 'active' : '' }}">
            <a href="{{ route('restaurant.printers.index') }}">
                <i class="fas fa-print"></i> Stampanti
            </a>
        </li>
    </ul>
</li>
