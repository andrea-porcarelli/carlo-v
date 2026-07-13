<!DOCTYPE html>
<html lang="en">
<head>
    @php($restaurantName = \App\Models\Setting::getRestaurantName())
    <title>{{ $restaurantName }} - Gestionale Mobile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $restaurantName }}">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#000000">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('app/images/icon-192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('app/images/icon-512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('app/images/apple-touch-icon.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('app.components.css')
    <link href="{{ asset('/app/css/mobile.css') }}?v={{ config('view.assets_version') }}" rel="stylesheet">
    @livewireStyles
</head>
<body class="mobile-body">

    @yield('main-content')

    @include('app.components.javascript')
    <script src="{{ asset('app/js/mobile.js') }}?v={{ config('view.assets_version') }}"></script>
    @livewireScripts
</body>
</html>
