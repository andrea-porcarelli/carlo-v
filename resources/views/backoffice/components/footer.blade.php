

    <script src="{{ asset('/backoffice/js/jquery-3.1.1.min.js') }}"></script>
    <script src="{{ asset('/backoffice/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('/backoffice/js/plugins/metisMenu/jquery.metisMenu.js') }}"></script>
    <script src="{{ asset('/backoffice/js/plugins/slimscroll/jquery.slimscroll.min.js') }}"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js"></script>

    <script>
        window.FontAwesomeConfig = {
            searchPseudoElements: true
        }
    </script>

    <!-- DROPZONE -->
    <script src="{{ asset('/backoffice/js/plugins/dropzone/dropzone.js') }}"></script>

    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

    @include('backoffice.components.javascript')

    <!-- switchery -->
    <script src="{{ asset('/backoffice/js/plugins/switchery/switchery.js') }}"></script>

    <!-- Flot -->
    <script src="{{ asset('backoffice/js/plugins/flot/jquery.flot.js') }}"></script>
    <script src="{{ asset('backoffice/js/plugins/flot/jquery.flot.tooltip.min.js') }}"></script>
    <script src="{{ asset('backoffice/js/plugins/flot/jquery.flot.spline.js') }}"></script>
    <script src="{{ asset('backoffice/js/plugins/flot/jquery.flot.resize.js') }}"></script>
    <script src="{{ asset('backoffice/js/plugins/flot/jquery.flot.pie.js') }}"></script>

    <!-- Peity -->
    <script src="{{ asset('backoffice/js/plugins/peity/jquery.peity.min.js') }}"></script>
    <!-- Custom and plugin javascript -->
    <script src="{{ asset('backoffice/js/plugins/pace/pace.min.js') }}"></script>

    <!-- jQuery UI -->
    <script src="{{ asset('backoffice/js/plugins/jquery-ui/jquery-ui.min.js') }}"></script>

    <!-- Toastr -->
    <script src="{{ asset('backoffice/js/plugins/toastr/toastr.min.js') }}"></script>
    <!-- Sweet alert -->
    <script src="{{ asset('backoffice/js/plugins/sweetalert/sweetalert.min.js') }}"></script>

    <script src="{{ asset('backoffice/js/inspinia.js') }}"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.13.6/js/standalone/selectize.js"></script>


    <script src="{{ asset('/backoffice/js/plugins/dataTables/datatables.min.js') }}"></script>
    <script src="{{ asset('/backoffice/js/plugins/dataTables/datatables.rowReorder.min.js') }}"></script>
    <script src="{{ asset('/backoffice/js/plugins/dataTables/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('/backoffice/js/plugins/dataTables/dataTables.rowGroup.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>
    <script src="{{ asset('/backoffice/js/plugins/select2/select2.full.min.js') }}"></script>

    @yield('custom-script')


