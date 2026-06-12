@php
    $adeEnabled     = filter_var(\App\Models\Setting::get('agenzia_entrate.enabled', true), FILTER_VALIDATE_BOOLEAN);
    $adeStatus      = \App\Models\Setting::get('agenzia_entrate.check_last_status', null);
    $adeDesc        = \App\Models\Setting::get('agenzia_entrate.check_last_descrizione', '');
    $adeCodice      = \App\Models\Setting::get('agenzia_entrate.check_last_codice', '');
    $adeCheckedAtIso= \App\Models\Setting::get('agenzia_entrate.check_last_at', null);
    $adeCheckedAt   = $adeCheckedAtIso ? \Carbon\Carbon::parse($adeCheckedAtIso)->format('d/m/Y H:i') : 'mai';
    $adeBannerLabel = $adeEnabled ? match($adeStatus) {
        'warning' => 'Pacchetto MySond non attivo',
        'error'   => 'Credenziali Agenzia Entrate: verifica fallita',
        null      => 'Credenziali Agenzia Entrate: nessun controllo eseguito',
        default   => null,
    } : null;
@endphp
<div class="row border-bottom">
    <nav class="navbar navbar-static-top" role="navigation" style="margin-bottom: 0">
        <div class="navbar-header">
            <div style="display: flex" class="table-responsive">
                <button class="navbar-minimalize minimalize-styl-2 btn btn-primary " href="#"><i class="fa fa-bars"></i> </button>
            </div>
        </div>
        @if($adeBannerLabel)
        <div class="ade-navbar-alert" style="float:left; margin: 10px 0 10px 20px; padding: 8px 14px; background: rgba(255, 193, 7, 0.15); border: 1px solid #f0ad4e; border-radius: 4px; display: flex; align-items: center; max-width: calc(100% - 540px);">
            <i class="fa fa-exclamation-triangle" style="font-size:18px; color:#f0ad4e; margin-right:10px;"></i>
            <div style="color:#8a6d3b; line-height:1.2;">
                <div style="font-weight:600;">{{ $adeBannerLabel }}</div>
                <div style="font-weight:400; font-size:11px;">
                    @if($adeDesc){{ $adeDesc }}@endif
                    @if($adeCodice) <span class="text-muted">(cod. {{ $adeCodice }})</span>@endif
                    <span class="text-muted">— ultimo check: {{ $adeCheckedAt }}</span>
                </div>
            </div>
            <a href="{{ route('restaurant.settings.ade-cambio-password') }}" class="btn btn-warning btn-xs" style="margin-left:12px; white-space:nowrap;">
                <i class="fa fa-key"></i> Cambia password
            </a>
        </div>
        @endif
        <ul class="nav navbar-top-links navbar-right">
            @if(\Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->role === 'admin')
                <li class="quick-invoice-nav-item">
                    <a href="{{ route('accounting.invoices.create') }}"
                       class="quick-invoice-link"
                       title="Emetti una fattura elettronica (Mysond)">
                        <i class="fa fa-file-invoice-dollar"></i>
                        <span class="quick-invoice-label">Fattura</span>
                    </a>
                </li>
            @endif
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

<style>
    /* Quick invoice nav item — icon + small label, in linea con le icone Inspinia top-links */
    .quick-invoice-link {
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        padding: 6px 14px !important;
        line-height: 1 !important;
        color: #676a6c !important;
        transition: color .15s, background .15s;
    }
    .quick-invoice-link:hover,
    .quick-invoice-link:focus {
        color: #1ab394 !important;
        background: rgba(26, 179, 148, 0.08);
        text-decoration: none;
    }
    .quick-invoice-link .fa-file-invoice-dollar {
        font-size: 20px;
        line-height: 1;
    }
    .quick-invoice-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .4px;
        font-weight: 600;
    }
</style>

