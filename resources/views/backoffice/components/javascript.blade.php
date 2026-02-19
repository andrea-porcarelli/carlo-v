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
        $('#sync-overlay').css('display', 'flex');

        $.ajax({
            url: '{{ route("backoffice.sync.trigger") }}',
            type: 'POST',
            dataType: 'json',
            success: function (data) {
                var results = data.results || {};
                var tables = Object.keys(results);
                var failed = tables.filter(function (t) { return results[t].status === 'failed'; });

                if (failed.length > 0) {
                    toastr.warning('Errori su: ' + failed.join(', '), 'Sync completato con errori', { timeOut: 8000 });
                } else {
                    toastr.success('Sincronizzate ' + tables.length + ' tabelle', 'Sync completato', { timeOut: 5000 });
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.message || xhr.statusText, 'Errore durante il sync', { timeOut: 8000 });
            },
            complete: function () {
                $('#sync-overlay').hide();
                $(el).removeClass('disabled');
                $icon.removeClass('fa-spin');
            }
        });
    }
</script>
