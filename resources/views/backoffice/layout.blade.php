<!DOCTYPE html>
<html lang="en">
@section('header')
    @include('backoffice.components.header')
@show

<body class=" @if(isset($mini)) mini-navbar @endif">
<div id="wrapper">
    @include('backoffice.components.nav-bar')
    <div id="page-wrapper" class="gray-bg   ">
        @include('backoffice.components.top-header')
        <div class="m-t-sm">
            @yield('main-content')
        </div>
    </div>
    @include('backoffice.components.right-sidebar')
</div>

{{--@include('backoffice.components.modals')--}}

{{-- Sync loader overlay --}}
<div id="sync-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:8px; padding:36px 52px; text-align:center; box-shadow:0 6px 32px rgba(0,0,0,0.25);">
        <i class="fa fa-sync fa-spin fa-3x text-navy" style="margin-bottom:18px;"></i>
        <div style="font-size:17px; font-weight:600; color:#333;">Sincronizzazione in corso...</div>
        <div style="font-size:13px; color:#888; margin-top:6px;">Attendere il completamento</div>
    </div>
</div>

{{-- Deploy / Migrate output modal --}}
<div class="modal fade" id="deploy-output-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title deploy-modal-title">Output</h4>
            </div>
            <div class="modal-body">
                <div class="deploy-modal-spinner text-center" style="display:none; margin-bottom:10px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                </div>
                <pre class="deploy-modal-output" style="max-height:400px; overflow-y:auto; background:#1e1e1e; color:#d4d4d4; padding:12px; border-radius:4px; font-size:12px; white-space:pre-wrap; word-break:break-all;"></pre>
                <div class="deploy-modal-status" style="margin-top:8px; font-weight:600;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

@include('backoffice.components.footer')
@livewireScripts

</body>
</html>
