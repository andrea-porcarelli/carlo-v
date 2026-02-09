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

    function triggerSync(el) {
        if ($(el).hasClass('disabled')) return;

        var $icon = $(el).find('.fa-sync');
        $(el).addClass('disabled');
        $icon.addClass('fa-spin');

        $.ajax({
            url: '{{ route("backoffice.sync.trigger") }}',
            type: 'POST',
            dataType: 'json',
            success: function (data) {
                var results = data.results || {};
                var tables = Object.keys(results);
                var failed = tables.filter(function (t) { return results[t].status === 'failed'; });

                if (failed.length > 0) {
                    alert('Sync completato con errori su: ' + failed.join(', '));
                } else {
                    alert('Sync completato con successo (' + tables.length + ' tabelle)');
                }
            },
            error: function (xhr) {
                alert('Errore durante il sync: ' + (xhr.responseJSON?.message || xhr.statusText));
            },
            complete: function () {
                $(el).removeClass('disabled');
                $icon.removeClass('fa-spin');
            }
        });
    }
</script>
