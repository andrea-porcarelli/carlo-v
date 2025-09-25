<nav class="navbar-default navbar-static-side" role="navigation">
    <div class="sidebar-collapse">
        <ul class="nav metismenu" id="side-menu">
            <li class="nav-header">
                <div class="dropdown profile-element text-center">
                    <h3 class="text-white">
                        CARLO V
                    </h3>
                </div>
                <div class="logo-element">
                    Q
                </div>
            </li>
            @if ($role === 'admin')
                @include('backoffice.components.nav-bar-item', ['route' => 'dashboard', 'icon' => 'fa-home', 'label' => 'Dashboard']))
                @include('backoffice.components.nav-bar-supplier')
                <li>
                    <a href="{{ url('backoffice/log-viewer') }}" target="_blank">
                        <i class="fa fa-cogs"></i>
                        <span class="nav-label">Logs</span>
                    </a>
                </li>
            @else
                @include('components.nav-bar-item', ['route' => 'technician.events', 'icon' => 'fa-cogs', 'label' => 'Attività di oggi'])
            @endif

        </ul>
    </div>
</nav>
