$(document).ready(function() {
    // Tables are now managed by tableOrdersManager from table-orders.js
    // This file only handles UI interactions that are not managed by the unified system

    // Category dropdown toggle
    $('.category-header').click(function() {
        const category = $(this).data('category');
        const items = $(`#${category}-items`);

        // Close other categories
        $('.category-header').not(this).removeClass('active');
        $('.category-items').not(items).slideUp(300);

        // Toggle current category
        $(this).toggleClass('active');
        items.slideToggle(300);
    });

    // Menu item click - delegate to tableOrdersManager
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
        }
    });

    // Modify table - delegate to tableOrdersManager
    $('#btnModifyTable').click(function() {
        if (typeof tableOrdersManager !== 'undefined' && tableOrdersManager.currentTable) {
            // Only allow if table is occupied
            if (tableOrdersManager.currentTable.table.status === 'occupied') {
                tableOrdersManager.openModifyOverlay();
            } else {
                tableOrdersManager.showNotification('Seleziona un tavolo occupato', 'error');
            }
        } else {
            if (typeof tableOrdersManager !== 'undefined') {
                tableOrdersManager.showNotification('Seleziona prima un tavolo occupato', 'error');
            }
        }
    });

    // Close modify overlay
    $('#closeModifyBtn').click(function() {
        // Clear temporary cart when closing modify overlay
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.temporaryCart = [];
            tableOrdersManager.updateCartDisplay();
        }
        $('#modifyOrderOverlay').fadeOut(300);
    });

    // Pay bill from modify overlay
    $('#btnModifyPayBill').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.payTable();
            $('#modifyOrderOverlay').fadeOut(300);
        }
    });

    // Clear bill from modify overlay
    $('#btnModifyClearBill').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.clearTable();
            $('#modifyOrderOverlay').fadeOut(300);
        }
    });

    // Show receipt - delegate to tableOrdersManager
    $('#showReceipt').click(function() {
        if (typeof tableOrdersManager !== 'undefined' && tableOrdersManager.currentTable) {
            $('#receiptOverlay').fadeIn(300);
        } else {
            if (typeof tableOrdersManager !== 'undefined') {
                tableOrdersManager.showNotification('Seleziona prima un tavolo', 'error');
            }
        }
    });

    // Close receipt
    $('#closeReceiptBtn').click(function() {
        $('#receiptOverlay').fadeOut(300);
    });

    // Pay bill - delegate to tableOrdersManager
    $('#btnPayBill').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.payTable();
        }
    });

    // Clear bill - delegate to tableOrdersManager
    $('#btnClearBill').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.clearTable();
        }
    });

    // Free table - delegate to tableOrdersManager
    $('#btnFreeTable').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.clearTable();
        }
    });

    // Reset all tables
    $('#resetAll').click(function() {
        if (confirm('Liberare tutti i tavoli? Tutti gli ordini verranno persi!')) {
            if (typeof tableOrdersManager !== 'undefined') {
                // Clear all tables via API (would need a new endpoint)
                tableOrdersManager.showNotification('Funzione in sviluppo', 'error');
            }
        }
    });

    // Save data
    $('#saveAll').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.showNotification('Dati salvati automaticamente');
        }
    });

    // Page navigation
    $('#btnMainView').click(function() {
        $('.page-content').removeClass('active');
        $('#mainView').addClass('active');
        $('.btn-red').removeClass('active');
        $(this).addClass('active');
    });

    $('#btnAddTable').click(function() {
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
    });

    // Close modals on overlay click
    $('#receiptOverlay, #productModal').click(function(e) {
        if (e.target === this) {
            $(this).fadeOut(300);
        }
    });
});
