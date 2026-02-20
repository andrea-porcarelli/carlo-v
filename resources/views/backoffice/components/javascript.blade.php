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

    function triggerDeploy() {
        @if(config('sync.role') === 'web')
        runDeployAction('{{ route("backoffice.remote.deploy") }}', 'Deploy Carlov (git pull)');
        @endif
    }

    function triggerMigrate() {
        @if(config('sync.role') === 'web')
        runDeployAction('{{ route("backoffice.remote.migrate") }}', 'Migrate Carlov');
        @endif
    }

    function runDeployAction(url, title) {
        var $modal  = $('#deploy-output-modal');
        var $title  = $modal.find('.deploy-modal-title');
        var $output = $modal.find('.deploy-modal-output');
        var $spinner = $modal.find('.deploy-modal-spinner');
        var $status  = $modal.find('.deploy-modal-status');

        $title.text(title);
        $output.text('');
        $status.text('').removeClass('text-success text-danger');
        $spinner.show();
        $modal.modal('show');

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            success: function (data) {
                $output.text((data.lines || []).join('\n') || '(nessun output)');
                $status.text(data.message || 'Completato').addClass('text-success');
            },
            error: function (xhr) {
                var data = xhr.responseJSON || {};
                var lines = (data.lines || []).join('\n');
                $output.text(lines || xhr.responseText || xhr.statusText || '(nessun output)');
                $status.text('Errore HTTP ' + xhr.status + (data.message ? ': ' + data.message : '')).addClass('text-danger');
            },
            complete: function () {
                $spinner.hide();
            }
        });
    }

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
