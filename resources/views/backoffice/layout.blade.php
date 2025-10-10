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
@include('backoffice.components.footer')
@livewireScripts

</body>
</html>
