<!DOCTYPE html>
<html lang="en">
<head>
    <title>Carlo V - Gestionale Mobile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('app.components.css')
    <link href="{{ asset('/app/css/mobile.css') }}?v=1.0" rel="stylesheet">
    @livewireStyles
</head>
<body class="mobile-body">

    @yield('main-content')

    @include('app.components.javascript')
    <script src="{{ asset('app/js/mobile.js') }}?v=1.0"></script>
    @livewireScripts
</body>
</html>
