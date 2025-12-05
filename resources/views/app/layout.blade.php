<!DOCTYPE html>
<html lang="en">
<head>
    <title>Carlo V - Gestionale</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('app.components.css')
    @livewireStyles
</head>
<body>

    @yield('main-content')

    @include('app.components.javascript')
    @livewireScripts
</body>
</html>
