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

    // ===== MANAGE TABLE MODAL =====

    // Open manage modal
    $('#btnManageTableMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined' && tableOrdersManager.currentTable) {
            openManageModal();
        }
    });

    function openManageModal() {
        if (!tableOrdersManager.currentTable) return;

        const table = tableOrdersManager.currentTable.table;
        const order = tableOrdersManager.currentTable.order;

        // Update modal header
        $('#manageTableNumberMobile').text(table.table_number);

        if (order) {
            const covers = order.covers || 0;
            if (covers === 0) {
                $('#manageCoversInfoMobile').html('<i class="fas fa-glass-cheers"></i> Solo Bevande');
            } else {
                $('#manageCoversMobile').text(covers);
                $('#manageCoversInfoMobile').html('<i class="fas fa-users"></i> ' + covers + ' coperti');
            }
            $('#manageTotalAmountMobile').text('€' + parseFloat(order.total_amount || 0).toFixed(2));
        } else {
            $('#manageCoversInfoMobile').html('');
            $('#manageTotalAmountMobile').text('€0.00');
        }

        // Update items
        updateManageReceiptItems();

        // Show modal
        $('#manageModalMobile').addClass('active').fadeIn(300);
    }

    function updateManageReceiptItems() {
        const container = $('#manageReceiptItemsMobile');
        const order = tableOrdersManager.currentTable?.order;

        if (!order || !order.items || order.items.length === 0) {
            container.html(`
                <div class="mobile-empty-state-small">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Nessun ordine</p>
                </div>
            `);
            return;
        }

        let html = '';
        order.items.forEach((item, index) => {
            const extras = item.extras && Object.keys(item.extras).length > 0
                ? '<div class="mobile-receipt-item-extras">+ ' + Object.keys(item.extras).join(', ') + '</div>'
                : '';
            const removals = item.removals && item.removals.length > 0
                ? '<div class="mobile-receipt-item-removals">- ' + item.removals.join(', ') + '</div>'
                : '';
            const notes = item.notes
                ? '<div class="mobile-receipt-item-notes"><i class="fas fa-comment"></i> ' + item.notes + '</div>'
                : '';

            const unitPrice = parseFloat(item.unit_price || 0).toFixed(2);
            const subtotal = parseFloat(item.subtotal || 0).toFixed(2);

            html += `
                <div class="mobile-receipt-item" data-item-id="${item.id}">
                    <div class="mobile-receipt-item-header">
                        <div class="mobile-receipt-item-info">
                            <div class="mobile-receipt-item-name">${item.dish?.label || item.dish?.name || 'Prodotto'}</div>
                            ${extras}
                            ${removals}
                        </div>
                        <div class="mobile-receipt-item-price-container">
                            <div class="mobile-price-display" id="priceDisplay_${item.id}">
                                <span class="mobile-receipt-item-price">€${subtotal}</span>
                                <button class="btn-edit-price" onclick="mobileEditPrice(${item.id}, ${unitPrice})">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                            </div>
                            <div class="mobile-receipt-item-price-edit" id="priceEdit_${item.id}" style="display: none;">
                                <input type="number" step="0.01" min="0" value="${unitPrice}" id="priceInput_${item.id}">
                                <button class="btn-save-price" onclick="mobileSavePrice(${item.id})">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    ${notes}
                    <div class="mobile-qty-controls">
                        <div class="mobile-qty-controls-left">
                            <button onclick="mobileUpdateQty(${item.id}, ${item.quantity - 1})">-</button>
                            <span>${item.quantity}</span>
                            <button onclick="mobileUpdateQty(${item.id}, ${item.quantity + 1})">+</button>
                        </div>
                        <button class="btn-remove-item" onclick="mobileRemoveItem(${item.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        container.html(html);
    }

    // Close manage modal
    $('#closeManageModalMobile').click(function() {
        $('#manageModalMobile').removeClass('active').fadeOut(300);
    });

    // Add product from manage modal
    $('#btnAddProductMobile').click(function() {
        // Hide manage modal temporarily
        $('#manageModalMobile').removeClass('active').hide();
        // Switch to menu view
        $('.mobile-nav-btn').removeClass('active');
        $('#btnMenuMobile').addClass('active');
        $('.mobile-page').removeClass('active');
        $('#menuViewMobile').addClass('active');
        // Show action bar
        $('#mobileActionBar').show();
    });

    // ===== ACTIONS FROM MANAGE MODAL =====

    // Marcia
    $('#btnMarciaMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.marciaTavolo();
            hapticFeedback();
        }
    });

    // PreConto
    $('#btnPrecontoMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            openPrecontoModalMobile();
        }
    });

    // Pay bill
    $('#btnPayBillMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.payTable();
            hapticFeedback([100, 50, 100, 50, 100]);
        }
    });

    // Clear bill
    $('#btnClearBillMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.clearTable();
            hapticFeedback();
        }
    });

    // Free table
    $('#btnFreeTableMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.clearTable();
            hapticFeedback();
        }
    });

    // Comunica
    $('#btnComunicaMobile').click(function() {
        openComunicaModalMobile();
    });

    // ===== PRECONTO MODAL (MOBILE) =====

    function openPrecontoModalMobile() {
        if (!tableOrdersManager.currentTable || !tableOrdersManager.currentTable.order) {
            tableOrdersManager.showNotification('Nessun ordine attivo', 'error');
            return;
        }

        const table = tableOrdersManager.currentTable.table;
        const order = tableOrdersManager.currentTable.order;

        $('#precontoTableNumberMobile').text(table.table_number);
        $('#precontoTotalAmountMobile').text('€' + parseFloat(order.total_amount || 0).toFixed(2));

        // Reset form
        $('input[name="precontoTypeMobile"][value="full"]').prop('checked', true);
        $('#splitCountContainerMobile').hide();
        $('#splitCountMobile').val(2);
        updateSplitPreviewMobile();

        $('#precontoModalMobile').addClass('active').fadeIn(300);
    }

    // Radio change
    $('input[name="precontoTypeMobile"]').change(function() {
        if ($(this).val() === 'split') {
            $('#splitCountContainerMobile').slideDown(200);
            updateSplitPreviewMobile();
        } else {
            $('#splitCountContainerMobile').slideUp(200);
        }
    });

    // Split count controls
    $('#decreaseSplitMobile').click(function() {
        const input = $('#splitCountMobile');
        const val = parseInt(input.val()) || 2;
        if (val > 2) {
            input.val(val - 1);
            updateSplitPreviewMobile();
        }
    });

    $('#increaseSplitMobile').click(function() {
        const input = $('#splitCountMobile');
        const val = parseInt(input.val()) || 2;
        if (val < 20) {
            input.val(val + 1);
            updateSplitPreviewMobile();
        }
    });

    $('#splitCountMobile').on('input', function() {
        updateSplitPreviewMobile();
    });

    function updateSplitPreviewMobile() {
        if (!tableOrdersManager.currentTable?.order) return;
        const total = parseFloat(tableOrdersManager.currentTable.order.total_amount) || 0;
        const splitCount = parseInt($('#splitCountMobile').val()) || 2;
        const perPerson = total / splitCount;
        $('#perPersonAmountMobile').text('€' + perPerson.toFixed(2));
    }

    // Close preconto modal
    $('#closePrecontoMobile, #cancelPrecontoMobile').click(function() {
        $('#precontoModalMobile').removeClass('active').fadeOut(300);
    });

    // Confirm preconto
    $('#confirmPrecontoMobile').click(async function() {
        if (!tableOrdersManager.currentTable) return;

        // Request operator authentication
        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) {
            console.log('Authentication cancelled');
            return;
        }

        const precontoType = $('input[name="precontoTypeMobile"]:checked').val();
        let splitCount = null;

        if (precontoType === 'split') {
            splitCount = parseInt($('#splitCountMobile').val()) || null;
        }

        try {
            const response = await fetch(`/api/tables/${tableOrdersManager.currentTable.table.id}/preconto`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token
                },
                body: JSON.stringify({ split_count: splitCount })
            });

            const result = await response.json();

            if (result.success) {
                tableOrdersManager.showNotification(result.message || 'PreConto stampato', 'success');
                $('#precontoModalMobile').removeClass('active').fadeOut(300);
                hapticFeedback([100, 50, 100]);
            } else {
                tableOrdersManager.showNotification(result.message || 'Errore nella stampa', 'error');
            }
        } catch (error) {
            console.error('Error printing preconto:', error);
            tableOrdersManager.showNotification('Errore nella stampa', 'error');
        }
    });

    // ===== COMUNICA MODAL (MOBILE) =====

    function openComunicaModalMobile() {
        const table = tableOrdersManager.currentTable?.table;

        // Reset form
        $('#comunicaPrinterSelectMobile').val('');
        $('#comunicaMessageMobile').val('');
        $('#comunicaTableNumberMobile').val(table ? 'Tavolo ' + table.table_number : '');

        $('#comunicaModalMobile').addClass('active').fadeIn(300);
    }

    // Close comunica modal
    $('#closeComunicaMobile, #cancelComunicaMobile').click(function() {
        $('#comunicaModalMobile').removeClass('active').fadeOut(300);
    });

    // Confirm comunica
    $('#confirmComunicaMobile').click(async function() {
        const printerId = $('#comunicaPrinterSelectMobile').val();
        const message = $('#comunicaMessageMobile').val().trim();

        if (!printerId) {
            tableOrdersManager.showNotification('Seleziona una stampante', 'error');
            return;
        }

        if (!message) {
            tableOrdersManager.showNotification('Inserisci un messaggio', 'error');
            return;
        }

        // Request operator authentication
        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) {
            console.log('Authentication cancelled');
            return;
        }

        try {
            const requestData = {
                printer_id: printerId,
                message: message
            };

            if (tableOrdersManager.currentTable?.table) {
                requestData.table_id = tableOrdersManager.currentTable.table.id;
            }

            const response = await fetch('/api/tables/comunica', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token
                },
                body: JSON.stringify(requestData)
            });

            const result = await response.json();

            if (result.success) {
                tableOrdersManager.showNotification(result.message || 'Comunicazione inviata', 'success');
                $('#comunicaModalMobile').removeClass('active').fadeOut(300);
                hapticFeedback([100, 50, 100]);
            } else {
                tableOrdersManager.showNotification(result.message || 'Errore nell\'invio', 'error');
            }
        } catch (error) {
            console.error('Error sending comunica:', error);
            tableOrdersManager.showNotification('Errore nell\'invio', 'error');
        }
    });

    // ===== CART (MOBILE) =====

    $('#confirmCartMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.confirmCart();
            hapticFeedback([100, 50, 100]);
        }
    });

    $('#clearCartMobile').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.clearTemporaryCart();
        }
    });

    // ===== CLOSE MODALS ON BACKGROUND CLICK =====

    $('.mobile-modal').click(function(e) {
        if ($(e.target).hasClass('mobile-modal')) {
            $(this).removeClass('active').fadeOut(300);
        }
    });

    // ===== HAPTIC FEEDBACK HELPER =====

    function hapticFeedback(pattern = [50]) {
        if (navigator.vibrate) {
            navigator.vibrate(pattern);
        }
    }

    // ===== TOUCH OPTIMIZATIONS =====

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

    // ===== SEGUE TOGGLE =====

    $(document).on('click', '#segueToggleMobile', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const checkbox = $('#productSegueMobile');
        const isChecked = checkbox.prop('checked');
        checkbox.prop('checked', !isChecked);

        // Update visual toggle
        const toggle = $(this);
        const switchEl = toggle.find('.segue-switch');
        const handle = toggle.find('.segue-switch-handle');

        if (!isChecked) {
            // Now checked
            switchEl.css('background', '#dc3545');
            handle.css('transform', 'translateX(26px)');
            toggle.css('border-color', '#dc3545');
        } else {
            // Now unchecked
            switchEl.css('background', '#ccc');
            handle.css('transform', 'translateX(0)');
            toggle.css('border-color', '#dee2e6');
        }

        // Haptic feedback
        if (navigator.vibrate) {
            navigator.vibrate(30);
        }
    });

    // Reset segue toggle when modal opens
    function resetSegueToggle() {
        const switchEl = $('#segueToggleMobile .segue-switch');
        const handle = $('#segueToggleMobile .segue-switch-handle');
        const toggle = $('#segueToggleMobile');

        switchEl.css('background', '#ccc');
        handle.css('transform', 'translateX(0)');
        toggle.css('border-color', '#dee2e6');
        $('#productSegueMobile').prop('checked', false);
    }

    $(document).on('click', '.menu-item', function() {
        setTimeout(resetSegueToggle, 100);
    });

    // Also reset when product modal is shown via other means
    const productModalMobile = document.getElementById('productModalMobile');
    if (productModalMobile) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'style') {
                    const display = productModalMobile.style.display;
                    if (display === 'flex' || display === 'block') {
                        setTimeout(resetSegueToggle, 50);
                    }
                }
            });
        });
        observer.observe(productModalMobile, { attributes: true });
    }

    // ===== WELCOME MESSAGE =====

    setTimeout(() => {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.showNotification('Benvenuto!', 'success');
        }
    }, 500);
});

// ===== GLOBAL FUNCTIONS FOR INLINE HANDLERS =====

async function mobileUpdateQty(itemId, newQty) {
    if (typeof tableOrdersManager === 'undefined') return;

    // If quantity is 0 or less, remove the item
    if (newQty <= 0) {
        if (confirm('Rimuovere questo prodotto dall\'ordine?')) {
            await mobileRemoveItem(itemId);
        }
        return;
    }

    // Use the increase/decrease methods which handle authentication
    const item = tableOrdersManager.currentTable?.order?.items?.find(i => i.id === itemId);
    if (!item) return;

    const currentQty = item.quantity;
    if (newQty > currentQty) {
        // Increase
        await tableOrdersManager.increaseQuantity(itemId, event);
    } else if (newQty < currentQty) {
        // Decrease
        await tableOrdersManager.decreaseQuantity(itemId, event);
    }

    // Refresh the manage modal
    setTimeout(() => {
        updateManageReceiptItemsGlobal();
        const order = tableOrdersManager.currentTable?.order;
        if (order) {
            $('#manageTotalAmountMobile').text('€' + parseFloat(order.total_amount || 0).toFixed(2));
        }
    }, 500);
}

async function mobileRemoveItem(itemId) {
    if (typeof tableOrdersManager !== 'undefined') {
        await tableOrdersManager.removeItem(itemId);
        // Refresh the manage modal
        setTimeout(() => {
            updateManageReceiptItemsGlobal();
            const order = tableOrdersManager.currentTable?.order;
            if (order) {
                $('#manageTotalAmountMobile').text('€' + parseFloat(order.total_amount || 0).toFixed(2));
            }
        }, 500);
    }
}

function mobileEditPrice(itemId, currentPrice) {
    // Hide display, show edit
    $(`#priceDisplay_${itemId}`).hide();
    $(`#priceEdit_${itemId}`).show();
    $(`#priceInput_${itemId}`).val(currentPrice).focus().select();
}

async function mobileSavePrice(itemId) {
    const newPrice = parseFloat($(`#priceInput_${itemId}`).val());

    if (isNaN(newPrice) || newPrice < 0) {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.showNotification('Prezzo non valido', 'error');
        }
        return;
    }

    // Request operator authentication
    let auth;
    try {
        auth = await operatorAuthManager.requestAuth();
        if (!auth) {
            // Restore display
            $(`#priceEdit_${itemId}`).hide();
            $(`#priceDisplay_${itemId}`).show();
            return;
        }
    } catch (error) {
        console.log('Authentication cancelled');
        $(`#priceEdit_${itemId}`).hide();
        $(`#priceDisplay_${itemId}`).show();
        return;
    }

    try {
        const response = await fetch(`/api/tables/items/${itemId}/price`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Operator-Token': auth.token
            },
            body: JSON.stringify({ unit_price: newPrice })
        });

        const result = await response.json();

        if (result.success) {
            if (typeof tableOrdersManager !== 'undefined') {
                tableOrdersManager.showNotification('Prezzo aggiornato', 'success');
                // Reload table data
                await tableOrdersManager.selectTable(tableOrdersManager.currentTable.table.id, true);
                // Update manage modal
                updateManageReceiptItemsGlobal();
                // Update total
                const order = tableOrdersManager.currentTable?.order;
                if (order) {
                    $('#manageTotalAmountMobile').text('€' + parseFloat(order.total_amount || 0).toFixed(2));
                }
            }
        } else {
            if (typeof tableOrdersManager !== 'undefined') {
                tableOrdersManager.showNotification(result.message || 'Errore aggiornamento prezzo', 'error');
            }
        }
    } catch (error) {
        console.error('Error updating price:', error);
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.showNotification('Errore aggiornamento prezzo', 'error');
        }
    }

    // Restore display
    $(`#priceEdit_${itemId}`).hide();
    $(`#priceDisplay_${itemId}`).show();
}

// Global reference to update function
function updateManageReceiptItemsGlobal() {
    const container = $('#manageReceiptItemsMobile');
    const order = tableOrdersManager?.currentTable?.order;

    if (!order || !order.items || order.items.length === 0) {
        container.html(`
            <div class="mobile-empty-state-small">
                <i class="fas fa-shopping-cart"></i>
                <p>Nessun ordine</p>
            </div>
        `);
        return;
    }

    let html = '';
    order.items.forEach((item, index) => {
        const extras = item.extras && Object.keys(item.extras).length > 0
            ? '<div class="mobile-receipt-item-extras">+ ' + Object.keys(item.extras).join(', ') + '</div>'
            : '';
        const removals = item.removals && item.removals.length > 0
            ? '<div class="mobile-receipt-item-removals">- ' + item.removals.join(', ') + '</div>'
            : '';
        const notes = item.notes
            ? '<div class="mobile-receipt-item-notes"><i class="fas fa-comment"></i> ' + item.notes + '</div>'
            : '';

        const unitPrice = parseFloat(item.unit_price || 0).toFixed(2);
        const subtotal = parseFloat(item.subtotal || 0).toFixed(2);

        html += `
            <div class="mobile-receipt-item" data-item-id="${item.id}">
                <div class="mobile-receipt-item-header">
                    <div class="mobile-receipt-item-info">
                        <div class="mobile-receipt-item-name">${item.dish?.label || item.dish?.name || 'Prodotto'}</div>
                        ${extras}
                        ${removals}
                    </div>
                    <div class="mobile-receipt-item-price-container">
                        <div class="mobile-price-display" id="priceDisplay_${item.id}">
                            <span class="mobile-receipt-item-price">€${subtotal}</span>
                            <button class="btn-edit-price" onclick="mobileEditPrice(${item.id}, ${unitPrice})">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                        </div>
                        <div class="mobile-receipt-item-price-edit" id="priceEdit_${item.id}" style="display: none;">
                            <input type="number" step="0.01" min="0" value="${unitPrice}" id="priceInput_${item.id}">
                            <button class="btn-save-price" onclick="mobileSavePrice(${item.id})">
                                <i class="fas fa-check"></i>
                            </button>
                        </div>
                    </div>
                </div>
                ${notes}
                <div class="mobile-qty-controls">
                    <div class="mobile-qty-controls-left">
                        <button onclick="mobileUpdateQty(${item.id}, ${item.quantity - 1})">-</button>
                        <span>${item.quantity}</span>
                        <button onclick="mobileUpdateQty(${item.id}, ${item.quantity + 1})">+</button>
                    </div>
                    <button class="btn-remove-item" onclick="mobileRemoveItem(${item.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });

    container.html(html);
}
