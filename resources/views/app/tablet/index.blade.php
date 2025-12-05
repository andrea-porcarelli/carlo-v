@extends('app.layout')

@section('main-content')
    <!-- Tablet version uses desktop layout with mobile optimizations -->
    <div class="tablet-mode">
        <style>
            /* Tablet-specific optimizations */
            .tablet-mode .main-container {
                padding: 10px;
            }

            .tablet-mode .navbar {
                padding: 15px 20px;
            }

            .tablet-mode .btn-red {
                padding: 12px 20px;
                font-size: 14px;
            }

            .tablet-mode .table-item {
                width: 90px;
                height: 90px;
            }

            .tablet-mode .table-number {
                font-size: 28px;
            }

            .tablet-mode .menu-item {
                padding: 12px;
                font-size: 14px;
            }

            /* Touch-friendly sizes */
            .tablet-mode button,
            .tablet-mode .control-panel,
            .tablet-mode .action-btn {
                min-height: 44px; /* iOS recommended touch target */
            }

            /* Prevent text selection */
            .tablet-mode * {
                -webkit-user-select: none;
                -moz-user-select: none;
                user-select: none;
            }

            .tablet-mode input,
            .tablet-mode textarea {
                -webkit-user-select: auto;
                -moz-user-select: auto;
                user-select: auto;
            }
        </style>

        @include('app.index')
    </div>

    <script>
        // Tablet-specific enhancements
        $(document).ready(function() {
            // Prevent double-tap zoom
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function(e) {
                const now = Date.now();
                if (now - lastTouchEnd <= 300) {
                    e.preventDefault();
                }
                lastTouchEnd = now;
            }, false);

            // Add haptic feedback for tablet
            $('button, .table-item, .menu-item').on('touchstart', function() {
                if (navigator.vibrate) {
                    navigator.vibrate(30);
                }
            });
        });
    </script>
@endsection
