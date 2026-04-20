@php
    $adeStatus      = \App\Models\Setting::get('agenzia_entrate.check_last_status', null);
    $adeDesc        = \App\Models\Setting::get('agenzia_entrate.check_last_descrizione', '');
    $adeCodice      = \App\Models\Setting::get('agenzia_entrate.check_last_codice', '');
    $adeCheckedAtIso= \App\Models\Setting::get('agenzia_entrate.check_last_at', null);
    $adeCheckedAt   = $adeCheckedAtIso ? \Carbon\Carbon::parse($adeCheckedAtIso)->format('d/m/Y H:i') : 'mai';
    $adeBannerLabel = match($adeStatus) {
        'warning' => 'Pacchetto MySond non attivo',
        'error'   => 'Credenziali Agenzia Entrate: verifica fallita',
        null      => 'Credenziali Agenzia Entrate: nessun controllo eseguito',
        default   => null,
    };
@endphp
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
@if($adeBannerLabel)
<div class="row" style="padding: 10px 15px; background: rgba(255, 193, 7, 0.15); border-top: 1px solid #f0ad4e; border-bottom: 2px solid #f0ad4e;">
    <div class="col-xs-12" style="display:flex; align-items:center; color:#8a6d3b; font-weight:600;">
        <i class="fa fa-exclamation-triangle" style="font-size:20px; color:#f0ad4e; margin-right:12px;"></i>
        <div style="flex:1;">
            <div>{{ $adeBannerLabel }}</div>
            <div style="font-weight:400; font-size:12px; color:#8a6d3b;">
                @if($adeDesc){{ $adeDesc }}@endif
                @if($adeCodice) <span class="text-muted">(cod. {{ $adeCodice }})</span>@endif
                <span class="text-muted">— ultimo check: {{ $adeCheckedAt }}</span>
            </div>
        </div>
        <a href="{{ route('restaurant.settings.ade-cambio-password') }}" class="btn btn-warning btn-sm" style="margin-left:12px;">
            <i class="fa fa-key"></i> Cambia password
        </a>
    </div>
</div>
@endif
<div class="row wrapper border-bottom white-bg page-heading">
    <div class="col-lg-10">
        @yield('breadcrumb')
    </div>
    <div class="col-lg-2">
    </div>
</div>

