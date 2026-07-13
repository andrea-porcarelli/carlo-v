
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>

<script src="{{ asset('app/js/operator-auth.js') }}?v={{ config('view.assets_version') }}"></script>
<script src="{{ asset('app/js/covers-manager.js') }}?v={{ config('view.assets_version') }}"></script>
<script src="{{ asset('app/js/table-orders.js') }}?v={{ config('view.assets_version') }}"></script>
<script src="{{ asset('app/js/app.js') }}?v={{ config('view.assets_version') }}"></script>

<style>
    @media all and (display-mode: standalone), all and (display-mode: fullscreen) {
        .js-fullscreen-btn { display: none !important; }
    }
</style>
<script>
    function openFullscreen() {
        var elem = document.documentElement;
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        }
    }
</script>
