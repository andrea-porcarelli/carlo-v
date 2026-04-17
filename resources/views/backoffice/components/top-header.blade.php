<div class="row border-bottom">
    <nav class="navbar navbar-static-top" role="navigation" style="margin-bottom: 0">
        <div class="navbar-header">
            <div style="display: flex" class="table-responsive">
                <button class="navbar-minimalize minimalize-styl-2 btn btn-primary " href="#"><i class="fa fa-bars"></i> </button>
            </div>
        </div>
        <ul class="nav navbar-top-links navbar-right">
            <li>
                <span class="m-r-sm text-muted welcome-message"></span>
            </li>
            @if(Auth::id() == 1 && in_array(config('sync.role'), ['web', 'local']))
            <li>
                <a href="#" onclick="triggerDeploy(); return false;" title="Deploy (git pull)">
                    <i class="fa fa-code-branch"></i>
                </a>
            </li>
            <li>
                <a href="#" onclick="triggerMigrate(); return false;" title="Migrate">
                    <i class="fa fa-database"></i>
                </a>
            </li>
            @endif
            <li>
                <a href="#" class=" btn-logout">
                    ({{ Auth::id() }}) {{ Auth::user()->name }} -
                    <i class="fa fa-outdent"></i> Log out
                </a>
            </li>
        </ul>
    </nav>
</div>
<div class="row wrapper border-bottom white-bg page-heading">
    <div class="col-lg-10">
        @yield('breadcrumb')
    </div>
    <div class="col-lg-2">
    </div>
</div>

