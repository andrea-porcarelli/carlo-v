<script src="{{ asset('backoffice/js/custom/lodash.js') }}?v=1.2" type="module"></script>
<script src="{{ asset('backoffice/js/custom/index.js') }}?v=1.3" type="module"></script>
<script>
    csrf_token = '{{ csrf_token() }}';

    $(function () {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrf_token
            }
        });
    })
</script>
