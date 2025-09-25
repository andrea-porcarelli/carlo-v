$(document).ready(function() {
    let selectedTable = null;
    let nextTableId = 21;
    let tableData = {};
    let newTables = [];
    let currentProduct = null;

    // Initialize tables
    function initializeTables() {
        const container = $('#tablesContainer');
        container.empty();

        for (let i = 1; i <= 20; i++) {
            const table = $(`
                        <div class="table-item table-free" data-table="${i}">
                            <div class="table-number">${i}</div>
                            <div class="table-status">LIBERO</div>
                        </div>
                    `);
            container.append(table);
            tableData[i] = { occupied: false, items: [], total: 0 };
        }
        updateStats();
    }

    // Update statistics
    function updateStats() {
        let occupied = 0;
        let free = 0;
        Object.values(tableData).forEach(table => {
            if (table.occupied) occupied++;
            else free++;
        });
        $('#occupiedCount').text(occupied);
        $('#freeCount').text(free);
    }

    // Show notification
    function showNotification(message) {
        $('#notificationText').text(message);
        $('#notification').addClass('show');
        setTimeout(() => {
            $('#notification').removeClass('show');
        }, 3000);
    }

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

    // Table selection
    $(document).on('click', '.table-item', function() {
        const tableNum = $(this).data('table');

        $('.table-item').removeClass('table-selected');
        $(this).addClass('table-selected');
        selectedTable = tableNum;

        updateReceiptDisplay();
    });

    // Menu item click - open modal
    $(document).on('click', '.menu-item', function() {
        if (!selectedTable) {
            showNotification('Seleziona prima un tavolo!');
            return;
        }

        const itemName = $(this).data('item');
        const itemPrice = parseFloat($(this).data('price'));

        currentProduct = {
            name: itemName,
            basePrice: itemPrice
        };

        openProductModal(itemName, itemPrice);
    });

    // Open product modal
    function openProductModal(name, price) {
        $('#modalProductName').text(name);
        $('#modalProductPrice').text(`€${price.toFixed(2)}`);
        $('#productQuantity').val(1);
        $('#productNotes').val('');

        $('.extra-checkbox, .removal-checkbox').prop('checked', false);

        updateModalTotal();
        $('#productModal').fadeIn(300);
    }

    // Update modal total
    function updateModalTotal() {
        if (!currentProduct) return;

        const quantity = parseInt($('#productQuantity').val()) || 1;
        let total = currentProduct.basePrice * quantity;

        $('.extra-checkbox:checked').each(function() {
            total += parseFloat($(this).val()) * quantity;
        });

        $('#modalTotal').text(`€${total.toFixed(2)}`);
    }

    // Modal controls
    $('#decreaseQty').click(function() {
        const input = $('#productQuantity');
        let val = parseInt(input.val()) || 1;
        if (val > 1) {
            input.val(val - 1);
            updateModalTotal();
        }
    });

    $('#increaseQty').click(function() {
        const input = $('#productQuantity');
        let val = parseInt(input.val()) || 1;
        input.val(val + 1);
        updateModalTotal();
    });

    $('#productQuantity, .extra-checkbox').on('change input', function() {
        updateModalTotal();
    });

    // Close product modal
    $('#closeProductModalBtn, #cancelProductBtn').click(function() {
        $('#productModal').fadeOut(300);
        currentProduct = null;
    });

    // Add product to order
    $('#addProductBtn').click(function() {
        if (!selectedTable || !currentProduct) return;

        const quantity = parseInt($('#productQuantity').val()) || 1;
        const notes = $('#productNotes').val().trim();

        let extras = [];
        $('.extra-checkbox:checked').each(function() {
            extras.push({
                name: $(this).data('name'),
                price: parseFloat($(this).val())
            });
        });

        let removals = [];
        $('.removal-checkbox:checked').each(function() {
            removals.push($(this).data('name'));
        });

        let itemTotal = currentProduct.basePrice * quantity;
        extras.forEach(extra => {
            itemTotal += extra.price * quantity;
        });

        const orderItem = {
            name: currentProduct.name,
            basePrice: currentProduct.basePrice,
            quantity: quantity,
            notes: notes,
            extras: extras,
            removals: removals,
            total: itemTotal
        };

        tableData[selectedTable].items.push(orderItem);
        tableData[selectedTable].occupied = true;

        $(`.table-item[data-table="${selectedTable}"]`)
            .removeClass('table-free')
            .addClass('table-occupied')
            .find('.table-status')
            .text('OCCUPATO');

        updateStats();
        updateReceiptDisplay();
        $('#productModal').fadeOut(300);
        currentProduct = null;

        showNotification(`${orderItem.name} aggiunto al tavolo ${selectedTable}`);
    });

    // Show receipt
    $('#showReceipt').click(function() {
        if (!selectedTable) {
            showNotification('Seleziona prima un tavolo!');
            return;
        }
        $('#receiptOverlay').fadeIn(300);
    });

    // Close receipt
    $('#closeReceiptBtn').click(function() {
        $('#receiptOverlay').fadeOut(300);
    });

    // Update receipt display
    function updateReceiptDisplay() {
        if (!selectedTable || !tableData[selectedTable]) {
            return;
        }

        const table = tableData[selectedTable];
        $('#selectedTableNumber').text(selectedTable);

        let itemsHtml = '';
        let total = 0;

        if (table.items.length === 0) {
            itemsHtml = `
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <p>Nessun ordine</p>
                        </div>
                    `;
        } else {
            table.items.forEach((item, index) => {
                total += item.total;

                let itemDetails = `${item.name} x${item.quantity}`;
                if (item.extras.length > 0) {
                    itemDetails += `<br><small style="color: #6c757d;">+ ${item.extras.map(e => e.name).join(', ')}</small>`;
                }
                if (item.removals.length > 0) {
                    itemDetails += `<br><small style="color: #6c757d;">- ${item.removals.join(', ')}</small>`;
                }
                if (item.notes) {
                    itemDetails += `<br><small style="color: #dc3545; font-weight: 600;">Note: ${item.notes}</small>`;
                }

                itemsHtml += `
                            <div class="receipt-item">
                                <div>
                                    <div class="receipt-item-name">${itemDetails}</div>
                                </div>
                                <div class="receipt-item-controls">
                                    <span class="receipt-item-price">€${item.total.toFixed(2)}</span>
                                    <button class="btn-remove" data-index="${index}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        `;
            });
        }

        $('#receiptItems').html(itemsHtml);
        $('#totalAmount').text(`€${total.toFixed(2)}`);
        tableData[selectedTable].total = total;
    }

    // Remove item from bill
    $(document).on('click', '.btn-remove', function() {
        const index = $(this).data('index');
        if (selectedTable && tableData[selectedTable]) {
            tableData[selectedTable].items.splice(index, 1);

            if (tableData[selectedTable].items.length === 0) {
                tableData[selectedTable].occupied = false;
                $(`.table-item[data-table="${selectedTable}"]`)
                    .removeClass('table-occupied')
                    .addClass('table-free')
                    .find('.table-status')
                    .text('LIBERO');
            }

            updateStats();
            updateReceiptDisplay();
        }
    });

    // Pay bill
    $('#btnPayBill').click(function() {
        if (!selectedTable) return;

        const total = tableData[selectedTable].total;
        if (total > 0) {
            if (confirm(`Incassare €${total.toFixed(2)} per il tavolo ${selectedTable}?`)) {
                tableData[selectedTable] = { occupied: false, items: [], total: 0 };
                $(`.table-item[data-table="${selectedTable}"]`)
                    .removeClass('table-occupied table-selected')
                    .addClass('table-free')
                    .find('.table-status')
                    .text('LIBERO');

                selectedTable = null;
                updateStats();
                updateReceiptDisplay();
                $('#receiptOverlay').fadeOut(300);

                showNotification('Pagamento incassato con successo!');
            }
        }
    });

    // Clear bill
    $('#btnClearBill').click(function() {
        if (!selectedTable) return;

        if (confirm(`Svuotare il conto del tavolo ${selectedTable}?`)) {
            tableData[selectedTable].items = [];
            tableData[selectedTable].total = 0;
            tableData[selectedTable].occupied = false;

            $(`.table-item[data-table="${selectedTable}"]`)
                .removeClass('table-occupied')
                .addClass('table-free')
                .find('.table-status')
                .text('LIBERO');

            updateStats();
            updateReceiptDisplay();
        }
    });

    // Free table
    $('#btnFreeTable').click(function() {
        if (!selectedTable) return;

        if (confirm(`Liberare il tavolo ${selectedTable}?`)) {
            tableData[selectedTable] = { occupied: false, items: [], total: 0 };
            $(`.table-item[data-table="${selectedTable}"]`)
                .removeClass('table-occupied table-selected')
                .addClass('table-free')
                .find('.table-status')
                .text('LIBERO');

            selectedTable = null;
            updateStats();
            updateReceiptDisplay();
            $('#receiptOverlay').fadeOut(300);
        }
    });

    // Reset all tables
    $('#resetAll').click(function() {
        if (confirm('Liberare tutti i tavoli? Tutti gli ordini verranno persi!')) {
            Object.keys(tableData).forEach(tableId => {
                tableData[tableId] = { occupied: false, items: [], total: 0 };
            });

            $('.table-item')
                .removeClass('table-occupied table-selected')
                .addClass('table-free')
                .find('.table-status')
                .text('LIBERO');

            selectedTable = null;
            updateStats();
            updateReceiptDisplay();
            showNotification('Tutti i tavoli sono stati liberati');
        }
    });

    // Save data
    $('#saveAll').click(function() {
        showNotification('Dati salvati con successo!');
    });

    // Page navigation
    $('#btnMainView').click(function() {
        $('.page-content').removeClass('active');
        $('#mainView').addClass('active');
        $('.btn-red').removeClass('active');
        $(this).addClass('active');
    });

    $('#btnAddTable').click(function() {
        $('.page-content').removeClass('active');
        $('#addTableView').addClass('active');
        $('.btn-red').removeClass('active');
        $(this).addClass('active');
        updateNewTableCount();
    });

    // Add table functionality
    $('#addTableArea').click(function(e) {
        const rect = this.getBoundingClientRect();
        const x = e.clientX - rect.left - 42.5;
        const y = e.clientY - rect.top - 42.5;

        const newTable = $(`
                    <div class="table-item table-free new-table" data-table="${nextTableId}"
                         style="left: ${x}px; top: ${y}px;">
                        <div class="table-number">${nextTableId}</div>
                        <div class="table-status">LIBERO</div>
                    </div>
                `);

        newTable.draggable({
            containment: '#addTableArea'
        });

        $('#addTableArea').append(newTable);
        newTables.push({
            id: nextTableId,
            x: x,
            y: y
        });

        tableData[nextTableId] = { occupied: false, items: [], total: 0 };
        nextTableId++;
        updateNewTableCount();
    });

    function updateNewTableCount() {
        $('#newTableCount').text(newTables.length);
        $('#nextTableNumber').text(nextTableId);
    }

    // Save tables
    $('#btnSaveTables').click(function() {
        newTables.forEach(table => {
            const tableElement = $(`
                        <div class="table-item table-free" data-table="${table.id}">
                            <div class="table-number">${table.id}</div>
                            <div class="table-status">LIBERO</div>
                        </div>
                    `);
            $('#tablesContainer').append(tableElement);
        });

        newTables = [];
        $('#addTableArea .new-table').remove();
        updateNewTableCount();
        updateStats();

        showNotification('Tavoli salvati con successo!');
    });

    // Reset layout
    $('#btnResetLayout').click(function() {
        if (confirm('Resettare il layout? Tutti i nuovi tavoli non salvati verranno persi.')) {
            $('#addTableArea .new-table').remove();
            newTables = [];
            nextTableId = 21;
            updateNewTableCount();
        }
    });

    // Close modals on overlay click
    $('#receiptOverlay, #productModal').click(function(e) {
        if (e.target === this) {
            $(this).fadeOut(300);
            if (this.id === 'productModal') {
                currentProduct = null;
            }
        }
    });

    // Initialize
    initializeTables();
});
