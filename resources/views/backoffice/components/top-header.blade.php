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
            @php
                $adeStatus      = \App\Models\Setting::get('agenzia_entrate.check_last_status', null);
                $adeDesc        = \App\Models\Setting::get('agenzia_entrate.check_last_descrizione', '');
                $adeCodice      = \App\Models\Setting::get('agenzia_entrate.check_last_codice', '');
                $adeCheckedAtIso= \App\Models\Setting::get('agenzia_entrate.check_last_at', null);
                $adeCheckedAt   = $adeCheckedAtIso ? \Carbon\Carbon::parse($adeCheckedAtIso)->format('d/m/Y H:i') : 'mai';
                $adeIcon        = match($adeStatus) {
                    'ok'      => 'fa-check-circle text-success',
                    'warning' => 'fa-exclamation-circle text-warning',
                    'error'   => 'fa-exclamation-triangle text-danger',
                    default   => 'fa-question-circle text-muted',
                };
                $adeLabel       = match($adeStatus) {
                    'ok'      => 'Credenziali AdE OK',
                    'warning' => 'Pacchetto MySond non attivo',
                    'error'   => 'Credenziali AdE: problema',
                    default   => 'Credenziali AdE: nessun check',
                };
                $adeTitle       = $adeLabel . ' — ultimo check: ' . $adeCheckedAt . ($adeDesc ? ' | ' . $adeDesc : '') . ($adeCodice ? ' (cod. ' . $adeCodice . ')' : '');
            @endphp
            <li title="{{ $adeTitle }}">
                <span class="navbar-text">
                    <i class="fa {{ $adeIcon }}"></i>
                    <span class="hidden-xs">AdE</span>
                </span>
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

