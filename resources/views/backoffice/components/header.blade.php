<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Backoffice | {{ $title ?? 'Dashboard' }}</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/toastr/toastr.min.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/iCheck/custom.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/sweetalert/sweetalert.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/jQueryUI/jquery-ui-1.10.4.custom.min.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/switchery/switchery.css') }}" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.13.6/css/selectize.bootstrap3.css" rel="stylesheet" />

    <link href="{{ asset('/backoffice/css/plugins/dropzone/basic.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/dropzone/dropzone.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/animate.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/custom.css') }}?v=1.0" rel="stylesheet">

    <link href="{{ asset('/backoffice/css/plugins/dataTables/datatables.min.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/dataTables/rowGroup.dataTables.min.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/select2/select2.min.css') }}" rel="stylesheet">

    <link href="{{ asset('/backoffice/css/plugins/summernote/summernote.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/summernote/summernote-bs3.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/datapicker/datepicker3.css') }}" rel="stylesheet">
    <link href="{{ asset('/backoffice/css/plugins/daterangepicker/daterangepicker-bs3.css') }}" rel="stylesheet">
    <!-- Lightbox gallery -->
    <link href="{{ asset('/backoffice/css/plugins/blueimp/css/blueimp-gallery.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    @yield('custom-css')
</head>
<body>
