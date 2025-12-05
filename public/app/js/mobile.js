/* ===== MOBILE JAVASCRIPT FOR CARLO V ===== */

$(document).ready(function() {
    // Tables are now managed by tableOrdersManager from table-orders.js
    // This file only handles mobile-specific UI interactions

    let currentView = 'main';

    // Mobile navigation
    $('.mobile-nav-btn').click(function() {
        const btnId = $(this).attr('id');

        if (btnId === 'btnAddTableMobile') {
            // Prompt user for number of tables to add
            const count = prompt('Quanti tavoli vuoi aggiungere?', '1');

            if (count === null) return; // User cancelled

            const tableCount = parseInt(count);
            if (isNaN(tableCount) || tableCount < 1 || tableCount > 50) {
                if (typeof tableOrdersManager !== 'undefined') {
                    tableOrdersManager.showNotification('Numero non valido. Inserisci un numero tra 1 e 50', 'error');
                } else {
                    alert('Numero non valido. Inserisci un numero tra 1 e 50');
                }
                return;
            }

            // Send AJAX request to add tables
            $.ajax({
                url: '/api/tables/add-batch',
                method: 'POST',
                data: {
                    count: tableCount
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        if (typeof tableOrdersManager !== 'undefined') {
                            tableOrdersManager.showNotification(response.message, 'success');
                            // Refresh tables to show new ones
                            tableOrdersManager.loadTables();
                        } else {
                            alert(response.message);
                            location.reload();
                        }

                        // Haptic feedback
                        if (navigator.vibrate) {
                            navigator.vibrate([100, 50, 100]);
                        }
                    } else {
                        if (typeof tableOrdersManager !== 'undefined') {
                            tableOrdersManager.showNotification(response.message || 'Errore nell\'aggiunta dei tavoli', 'error');
                        } else {
                            alert(response.message || 'Errore nell\'aggiunta dei tavoli');
                        }
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Errore nell\'aggiunta dei tavoli';
                    if (typeof tableOrdersManager !== 'undefined') {
                        tableOrdersManager.showNotification(message, 'error');
                    } else {
                        alert(message);
                    }
                }
            });

            return; // Don't change view
        }

        $('.mobile-nav-btn').removeClass('active');
        $(this).addClass('active');

        $('.mobile-page').removeClass('active');

        if (btnId === 'btnMainViewMobile') {
            $('#mainViewMobile').addClass('active');
            currentView = 'main';
        } else if (btnId === 'btnMenuMobile') {
            $('#menuViewMobile').addClass('active');
            currentView = 'menu';
        }

        // Hide action bar when changing views
        $('#mobileActionBar').fadeOut(200);
    });

    // Menu item click (mobile) - delegate to tableOrdersManager
    $(document).on('click', '.menu-item', function() {
        const dishId = $(this).data('dish-id');
        const dishName = $(this).data('item');
        const dishPrice = parseFloat($(this).data('price'));

        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.openProductModal({
                id: dishId,
                name: dishName,
                price: dishPrice
            });

            // Haptic feedback (if available)
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
        }
    });

    // Show receipt (mobile) - delegate to tableOrdersManager
    $('#showReceiptMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined' && tableOrdersManager.currentTable) {
            $('#receiptModalMobile').addClass('active').fadeIn(300);
        } else {
            if (typeof tableOrdersManager !== 'undefined') {
                tableOrdersManager.showNotification('Seleziona prima un tavolo', 'error');
            }
        }
    });

    // Close receipt (mobile)
    $('#closeReceiptMobile').click(function() {
        $('#receiptModalMobile').removeClass('active').fadeOut(300);
    });

    // Pay bill (mobile) - delegate to tableOrdersManager
    $('#btnPayBillMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.payTable();

            // Haptic feedback
            if (navigator.vibrate) {
                navigator.vibrate([100, 50, 100, 50, 100]);
            }
        }
    });

    // Clear bill (mobile) - delegate to tableOrdersManager
    $('#btnClearBillMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.clearTable();
        }
    });

    // Free table (mobile) - delegate to tableOrdersManager
    $('#btnFreeTableMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.clearTable();

            // Haptic feedback
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
        }
    });

    // Close modals on background click (mobile)
    $('.mobile-modal').click(function(e) {
        if ($(e.target).hasClass('mobile-modal')) {
            $(this).removeClass('active').fadeOut(300);
        }
    });

    // Prevent pull-to-refresh on iOS
    document.body.addEventListener('touchmove', function(e) {
        if (e.target.closest('.mobile-modal-body')) {
            return; // Allow scrolling in modal
        }
        if (document.body.scrollTop === 0) {
            e.preventDefault();
        }
    }, { passive: false });

    // Prevent zoom on double-tap
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function(e) {
        const now = Date.now();
        if (now - lastTouchEnd <= 300) {
            e.preventDefault();
        }
        lastTouchEnd = now;
    }, false);

    // Show a welcome message
    setTimeout(() => {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.showNotification('Benvenuto nella versione mobile!');
        }
    }, 500);
});
