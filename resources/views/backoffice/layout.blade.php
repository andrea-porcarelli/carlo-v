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

@include('backoffice.components.footer')
@livewireScripts

</body>
</html>
