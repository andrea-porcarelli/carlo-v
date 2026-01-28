/**
 * Table Orders Management
 * Unified logic for managing table orders across desktop and mobile
 */

class TableOrdersManager {
    constructor(isMobile = false) {
        this.isMobile = isMobile;
        this.currentTable = null;
        this.currentProduct = null;
        this.apiBase = '/api/tables';
        this.temporaryCart = []; // Temporary cart for multiple items

        this.init();
    }

    /**
     * Initialize event listeners
     */
    init() {
        this.attachModalEvents();
        this.loadTables();
        this.startTimerUpdates();
    }

    /**
     * Get element ID based on device type
     */
    getElementId(baseId) {
        return this.isMobile ? `${baseId}Mobile` : baseId;
    }

    /**
     * Get element by ID based on device type
     */
    getElement(baseId) {
        return document.getElementById(this.getElementId(baseId));
    }

    /**
     * Attach modal event listeners
     */
    attachModalEvents() {
        // Quantity controls
        this.getElement('decreaseQty')?.addEventListener('click', () => this.changeQuantity(-1));
        this.getElement('increaseQty')?.addEventListener('click', () => this.changeQuantity(1));
        this.getElement('productQuantity')?.addEventListener('input', () => this.updateModalTotal());

        // Custom price input
        this.getElement('productCustomPrice')?.addEventListener('input', () => this.updateModalTotal());

        // Extras and removals
        const extrasContainer = this.getElement('extrasContainer');
        const removalsContainer = this.getElement('removalsContainer');

        extrasContainer?.addEventListener('change', (e) => {
            if (e.target.matches('.extra-checkbox, .extra-checkbox-mobile')) {
                this.updateModalTotal();
            }
        });

        // Close modal
        this.getElement('closeProductModal')?.addEventListener('click', () => this.closeProductModal());
        this.getElement('cancelProductBtn')?.addEventListener('click', () => this.closeProductModal());

        // Add product
        this.getElement('addProductBtn')?.addEventListener('click', () => this.addProductToTable());

        // Add to cart button
        this.getElement('addToCartBtn')?.addEventListener('click', () => this.addToCart());

        // Cart buttons
        document.getElementById('confirmCart')?.addEventListener('click', () => this.confirmCart());
        document.getElementById('clearCart')?.addEventListener('click', () => this.clearTemporaryCart());

        // Cart buttons for modify overlay
        document.getElementById('confirmCartModify')?.addEventListener('click', () => this.confirmCart());
        document.getElementById('clearCartModify')?.addEventListener('click', () => this.clearTemporaryCart());
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'success') {
        const notification = this.getElement('notification');
        const notificationText = this.getElement('notificationText');

        if (notification && notificationText) {
            notificationText.textContent = message;
            notification.style.display = 'block';
            notification.className = `notification ${type}`;

            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }
    }

    /**
     * Load all tables
     */
    async loadTables() {
        try {
            const response = await fetch(this.apiBase);
            const result = await response.json();

            if (result.success) {
                this.renderTables(result.data);
            }
        } catch (error) {
            console.error('Error loading tables:', error);
            this.showNotification('Errore nel caricamento dei tavoli', 'error');
        }
    }

    /**
     * Render tables in the grid
     */
    renderTables(tables) {
        const container = document.getElementById(
            this.isMobile ? 'tablesContainerMobile' : 'tablesContainer'
        );

        if (!container) return;

        // Different rendering for mobile vs desktop
        if (this.isMobile) {
            container.innerHTML = tables.map(table => `
                <div class="mobile-table ${table.status === 'free' ? 'free' : 'occupied'}" data-table="${table.id}">
                    <div class="mobile-table-number">${table.table_number}</div>
                    <div class="mobile-table-status">${this.getStatusLabel(table.status)}</div>
                    ${table.has_active_order ? `
                        <div class="mobile-table-total">€${parseFloat(table.current_total).toFixed(2)}</div>
                        <div class="mobile-table-timer" data-opened-at="${table.active_order.opened_at}">
                            ${this.formatElapsedTime(table.active_order.opened_at)}
                        </div>
                    ` : ''}
                </div>
            `).join('');
        } else {
            container.innerHTML = tables.map(table => `
                <div class="table-item table-${table.status === 'free' ? 'free' : 'occupied'}" data-table="${table.id}">
                    <div class="table-number">${table.table_number}</div>
                    <div class="table-status">${this.getStatusLabel(table.status)}</div>
                    ${table.has_active_order ? `
                        <div class="table-total">€${parseFloat(table.current_total).toFixed(2)}</div>
                        <div class="table-timer" data-opened-at="${table.active_order.opened_at}">
                            <i class="fas fa-clock"></i> ${this.formatElapsedTime(table.active_order.opened_at)}
                        </div>
                    ` : ''}
                </div>
            `).join('');
        }

        // Attach click events to tables
        const tableElements = container.querySelectorAll(this.isMobile ? '.mobile-table' : '.table-item');
        tableElements.forEach(card => {
            card.addEventListener('click', () => {
                const tableId = card.dataset.table;

                // Remove previous selection
                tableElements.forEach(t => t.classList.remove('table-selected', 'selected'));

                // Add selection to current table
                card.classList.add(this.isMobile ? 'selected' : 'table-selected');

                this.selectTable(tableId);

                // Mobile: show action bar
                if (this.isMobile) {
                    const actionBar = document.getElementById('mobileActionBar');
                    if (actionBar) actionBar.style.display = 'block';

                    // Haptic feedback
                    if (navigator.vibrate) {
                        navigator.vibrate(50);
                    }
                }
            });
        });

        // Update stats
        this.updateStats(tables);
    }

    /**
     * Get status label
     */
    getStatusLabel(status) {
        const labels = {
            'free': 'LIBERO',
            'occupied': 'OCCUPATO',
            'reserved': 'RISERVATO'
        };
        return labels[status] || status;
    }

    /**
     * Update stats counters
     */
    updateStats(tables) {
        const occupied = tables.filter(t => t.status === 'occupied').length;
        const free = tables.filter(t => t.status === 'free').length;

        const occupiedElement = this.getElement('occupiedCount');
        const freeElement = this.getElement('freeCount');

        if (occupiedElement) occupiedElement.textContent = occupied;
        if (freeElement) freeElement.textContent = free;
    }

    /**
     * Select a table
     */
    async selectTable(tableId, skipCoversRequest = false) {
        // Clear temporary cart when changing tables
        if (this.currentTable && this.currentTable.table.id !== tableId) {
            this.temporaryCart = [];
            this.updateCartDisplay();
        }

        try {
            const response = await fetch(`${this.apiBase}/${tableId}`);
            const result = await response.json();

            if (result.success) {
                this.currentTable = result.data;

                // If table is free and has no active order, ask for covers (unless we just opened it)
                if (this.currentTable.table.status === 'free' && !this.currentTable.order && !skipCoversRequest) {
                    try {
                        // First request covers
                        const covers = await coversManager.requestCovers(this.currentTable.table.table_number);

                        // Then request operator authentication
                        const auth = await operatorAuthManager.requestAuth();
                        if (!auth) {
                            this.currentTable = null;
                            this.showNotification('Autenticazione annullata', 'error');
                            return;
                        }

                        // Open table with covers
                        await this.openTableWithCovers(tableId, covers, auth.token);

                        // Reload table data (skip covers request to avoid loop)
                        await this.selectTable(tableId, true);
                    } catch (error) {
                        // User cancelled covers selection or authentication
                        this.currentTable = null;
                        this.showNotification('Operazione annullata', 'error');
                    }
                } else {
                    this.showTableDetails();
                }
            }
        } catch (error) {
            console.error('Error loading table:', error);
            this.showNotification('Errore nel caricamento del tavolo', 'error');
        }
    }

    /**
     * Open table with covers (create order without items)
     */
    async openTableWithCovers(tableId, covers, operatorToken) {
        try {
            console.error(tableId)
            const response = await fetch(`${this.apiBase}/${tableId}/open`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({
                    covers: covers,
                    operator_token: operatorToken
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                // Refresh tables list to show updated status
                await this.loadTables();
                return true;
            } else {
                this.showNotification(result.message || 'Errore nell\'apertura del tavolo', 'error');
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Error opening table:', error);
            if (error.message) {
                this.showNotification(error.message, 'error');
            } else {
                this.showNotification('Errore nell\'apertura del tavolo', 'error');
            }
            throw error;
        }
    }

    /**
     * Show table details
     */
    showTableDetails() {
        // Update table number display
        const tableNumberElement = this.getElement('selectedTableNumber');
        if (tableNumberElement) {
            tableNumberElement.textContent = this.currentTable.table.table_number;
        }

        // Update mobile specific elements
        if (this.isMobile) {
            const mobileTableNumber = document.getElementById('selectedTableNumberMobile');
            if (mobileTableNumber) {
                mobileTableNumber.textContent = this.currentTable.table.table_number;
            }

            const receiptTableNumber = document.getElementById('receiptTableNumberMobile');
            if (receiptTableNumber) {
                receiptTableNumber.textContent = this.currentTable.table.table_number;
            }

            // Update total in action bar
            const mobileTotal = document.getElementById('selectedTableTotalMobile');
            if (mobileTotal && this.currentTable.order) {
                mobileTotal.textContent = '€' + parseFloat(this.currentTable.order.total_amount || 0).toFixed(2);
            } else if (mobileTotal) {
                mobileTotal.textContent = '';
            }
        }

        // Update MODIFICA button state (only for desktop)
        if (!this.isMobile) {
            this.updateModifyButtonState();
        }

        // Update receipt items
        this.updateReceiptItems();
    }

    /**
     * Update GESTISCI button state based on table status
     */
    updateModifyButtonState() {
        const modifyBtn = document.getElementById('btnModifyTable');
        if (!modifyBtn) return;

        if (this.currentTable && this.currentTable.table.status === 'occupied') {
            modifyBtn.removeAttribute('disabled');
        } else {
            modifyBtn.setAttribute('disabled', 'disabled');
        }
    }

    /**
     * Open modify overlay with menu and order details
     */
    async openModifyOverlay() {
        if (!this.currentTable) return;

        try {
            // Reload table data to ensure we have the latest order items
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}`);
            const result = await response.json();

            if (result.success) {
                this.currentTable = result.data;
            }
        } catch (error) {
            console.error('Error loading table data:', error);
            this.showNotification('Errore nel caricamento dei dati', 'error');
            return;
        }

        // Update table number
        const modifyTableNumber = document.getElementById('modifyTableNumber');
        const modifySelectedTableNumber = document.getElementById('modifySelectedTableNumber');
        if (modifyTableNumber) modifyTableNumber.textContent = this.currentTable.table.table_number;
        if (modifySelectedTableNumber) modifySelectedTableNumber.textContent = this.currentTable.table.table_number;

        // Update covers info
        const order = this.currentTable.order;
        const modifyCoversInfo = document.getElementById('modifyCoversInfo');
        const modifyCoversCount = document.getElementById('modifyCoversCount');
        const modifyCoversIcon = document.getElementById('modifyCoversIcon');
        const modifyCoversLabel = document.getElementById('modifyCoversLabel');
        if (order && modifyCoversInfo && modifyCoversCount) {
            if (order.covers === 0) {
                // Drinks mode
                if (modifyCoversIcon) modifyCoversIcon.className = 'fas fa-glass-cheers';
                modifyCoversCount.textContent = 'Consumo Bevande';
                if (modifyCoversLabel) modifyCoversLabel.textContent = '';
                modifyCoversInfo.style.display = 'block';
            } else if (order.covers > 0) {
                if (modifyCoversIcon) modifyCoversIcon.className = 'fas fa-users';
                modifyCoversCount.textContent = order.covers;
                if (modifyCoversLabel) modifyCoversLabel.textContent = ' coperti';
                modifyCoversInfo.style.display = 'block';
            } else {
                modifyCoversInfo.style.display = 'none';
            }
        } else if (modifyCoversInfo) {
            modifyCoversInfo.style.display = 'none';
        }

        // Update receipt items in modify overlay
        this.updateModifyReceiptItems();

        // Show overlay
        const overlay = document.getElementById('modifyOrderOverlay');
        if (overlay) {
            overlay.style.display = 'block';
            // Fade in effect
            setTimeout(() => {
                overlay.style.opacity = '1';
            }, 10);
        }
    }

    /**
     * Update receipt items in modify overlay
     */
    updateModifyReceiptItems() {
        const itemsContainer = document.getElementById('modifyReceiptItems');
        const totalElement = document.getElementById('modifyTotalAmount');

        if (!itemsContainer || !totalElement) {
            console.log('Container o total element non trovato');
            return;
        }

        const order = this.currentTable?.order;

        if (!order || !order.items || order.items.length === 0) {
            itemsContainer.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Nessun ordine</p>
                </div>
            `;
            totalElement.textContent = '€0.00';
            return;
        }


        const unitPriceLabel = (item) => {
            const up = parseFloat(item.unit_price);
            return `€${up.toFixed(2)}`;
        };

        let itemsHtml = order.items.map(item => `
            <div class="receipt-item" data-item-id="${item.id}">
                <div class="receipt-item-header">
                    <strong>${item.dish_name}</strong>
                    ${item.notes ? `<br /><div class="receipt-item-notes"><i class="fas fa-sticky-note me-1"></i>${item.notes}</div>` : ''}
                </div>
                <div class="receipt-item-details">
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="tableOrdersManager.decreaseQuantity(${item.id}, event)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <span class="quantity-display">${item.quantity}</span>
                        <button class="quantity-btn" onclick="tableOrdersManager.increaseQuantity(${item.id}, event)">
                            <i class="fas fa-plus"></i>
                        </button>
                        <span class="receipt-item-price" id="priceDisplay_${item.id}">
                            <span onclick="tableOrdersManager.editPrice(${item.id}, ${parseFloat(item.unit_price)})" style="cursor:pointer;" title="Modifica prezzo">€${parseFloat(item.subtotal).toFixed(2)} <i class="fas fa-pencil-alt" style="font-size:0.7rem;color:#6c757d;"></i></span>
                        </span>
                        <span id="priceEdit_${item.id}" style="display:none;">
                            <input type="number" step="0.01" min="0" value="${parseFloat(item.unit_price).toFixed(2)}" id="priceInput_${item.id}" style="width:70px;padding:2px 4px;font-size:0.85rem;border:1px solid #dc3545;border-radius:3px;">
                            <button onclick="tableOrdersManager.savePrice(${item.id})" style="background:#28a745;border:none;color:white;padding:2px 6px;border-radius:3px;cursor:pointer;font-size:0.8rem;"><i class="fas fa-check"></i></button>
                            <button onclick="tableOrdersManager.cancelEditPrice(${item.id})" style="background:#6c757d;border:none;color:white;padding:2px 6px;border-radius:3px;cursor:pointer;font-size:0.8rem;"><i class="fas fa-times"></i></button>
                        </span>
                        <br />
                    </div>
                    ${item.extras && Object.keys(item.extras).length > 0 ? `
                        <div class="receipt-item-extras">
                            ${Object.entries(item.extras).map(([name, price]) =>
                                `<span><i class="fas fa-plus-circle me-1"></i>${name} (+€${parseFloat(price).toFixed(2)})</span>`
                            ).join(', ')}
                        </div>
                    ` : ''}
                    ${item.removals && item.removals.length > 0 ? `
                        <div class="receipt-item-removals">
                            ${item.removals.map(removal => `<span><i class="fas fa-minus-circle me-1"></i>${removal}</span>`).join(', ')}
                        </div>
                    ` : ''}
                </div>
                <button class="btn-remove-item" onclick="tableOrdersManager.removeItem(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `).join('');

        // Add cover charge row (editable)
        if (order.has_cover_charge && order.cover_charge_total > 0) {
            itemsHtml += `
                <div class="receipt-item receipt-item-cover" style="background: #f8f9fa; border-left: 3px solid #17a2b8;">
                    <div class="receipt-item-header" style="display:flex;justify-content:space-between;align-items:center;">
                        <strong><i class="fas fa-utensils me-2"></i>Coperto</strong>
                        <span>
                            <span id="coversDisplay" style="cursor:pointer;" onclick="tableOrdersManager.editCovers()" title="Modifica coperti">
                                ${order.covers} x €${parseFloat(order.cover_charge_per_person).toFixed(2)} = <strong>€${parseFloat(order.cover_charge_total).toFixed(2)}</strong>
                                <i class="fas fa-pencil-alt" style="font-size:0.7rem;color:#6c757d;margin-left:4px;"></i>
                            </span>
                            <span id="coversEdit" style="display:none;">
                                <button onclick="tableOrdersManager.changeCovers(-1)" style="background:#dc3545;border:none;color:white;padding:2px 8px;border-radius:3px;cursor:pointer;font-weight:700;">−</button>
                                <span id="coversEditValue" style="display:inline-block;min-width:30px;text-align:center;font-weight:700;">${order.covers}</span>
                                <button onclick="tableOrdersManager.changeCovers(1)" style="background:#28a745;border:none;color:white;padding:2px 8px;border-radius:3px;cursor:pointer;font-weight:700;">+</button>
                                <button onclick="tableOrdersManager.saveCovers()" style="background:#17a2b8;border:none;color:white;padding:2px 8px;border-radius:3px;cursor:pointer;margin-left:4px;font-size:0.8rem;"><i class="fas fa-check"></i> Salva</button>
                                <button onclick="tableOrdersManager.cancelEditCovers()" style="background:#6c757d;border:none;color:white;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:0.8rem;"><i class="fas fa-times"></i></button>
                            </span>
                        </span>
                    </div>
                </div>
            `;
        } else if (order.covers === 0) {
            itemsHtml += `
                <div class="receipt-item receipt-item-cover" style="background: #f8f9fa; border-left: 3px solid #ffc107;">
                    <div class="receipt-item-header" style="display:flex;justify-content:space-between;align-items:center;">
                        <strong><i class="fas fa-glass-cheers me-2"></i>Consumo Bevande (no coperto)</strong>
                        <span>
                            <span id="coversDisplay" style="cursor:pointer;" onclick="tableOrdersManager.editCovers()" title="Modifica coperti">
                                <i class="fas fa-pencil-alt" style="font-size:0.7rem;color:#6c757d;"></i>
                            </span>
                            <span id="coversEdit" style="display:none;">
                                <button onclick="tableOrdersManager.changeCovers(-1)" style="background:#dc3545;border:none;color:white;padding:2px 8px;border-radius:3px;cursor:pointer;font-weight:700;">−</button>
                                <span id="coversEditValue" style="display:inline-block;min-width:30px;text-align:center;font-weight:700;">0</span>
                                <button onclick="tableOrdersManager.changeCovers(1)" style="background:#28a745;border:none;color:white;padding:2px 8px;border-radius:3px;cursor:pointer;font-weight:700;">+</button>
                                <button onclick="tableOrdersManager.saveCovers()" style="background:#17a2b8;border:none;color:white;padding:2px 8px;border-radius:3px;cursor:pointer;margin-left:4px;font-size:0.8rem;"><i class="fas fa-check"></i> Salva</button>
                                <button onclick="tableOrdersManager.cancelEditCovers()" style="background:#6c757d;border:none;color:white;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:0.8rem;"><i class="fas fa-times"></i></button>
                            </span>
                        </span>
                    </div>
                </div>
            `;
        }

        itemsContainer.innerHTML = itemsHtml;

        totalElement.textContent = `€${parseFloat(order.total_amount).toFixed(2)}`;
    }

    /**
     * Update receipt items display
     */
    updateReceiptItems() {
        const itemsContainer = this.getElement('receiptItems');
        const totalElement = this.getElement('totalAmount');

        if (!itemsContainer || !totalElement) return;

        const order = this.currentTable.order;

        // Update covers info if present
        const coversInfo = document.getElementById('coversInfo');
        const coversCount = document.getElementById('coversCount');
        const coversIcon = document.getElementById('coversIcon');
        const coversLabel = document.getElementById('coversLabel');
        if (order && coversInfo && coversCount) {
            if (order.covers === 0) {
                // Drinks mode
                if (coversIcon) coversIcon.className = 'fas fa-glass-cheers';
                coversCount.textContent = 'Consumo Bevande';
                if (coversLabel) coversLabel.textContent = '';
                coversInfo.style.display = 'inline';
            } else if (order.covers > 0) {
                if (coversIcon) coversIcon.className = 'fas fa-users';
                coversCount.textContent = order.covers;
                if (coversLabel) coversLabel.textContent = ' coperti';
                coversInfo.style.display = 'inline';
            } else {
                coversInfo.style.display = 'none';
            }
        } else if (coversInfo) {
            coversInfo.style.display = 'none';
        }

        if (!order || !order.items || order.items.length === 0) {
            itemsContainer.innerHTML = `
                <div class="${this.isMobile ? 'mobile-empty-state-small' : 'empty-state'}">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Nessun ordine</p>
                </div>
            `;
            totalElement.textContent = '€0.00';
            return;
        }

        let itemsHtml = order.items.map(item => `
            <div class="receipt-item" data-item-id="${item.id}">
                <div class="receipt-item-header">
                    <strong>${item.dish_name}</strong>
                    <span class="receipt-item-price">€${parseFloat(item.subtotal).toFixed(2)}</span>
                </div>
                <div class="receipt-item-details">
                    <span>Quantità: ${item.quantity}</span>
                    ${item.notes ? `<div class="receipt-item-notes">${item.notes}</div>` : ''}
                    ${item.extras && Object.keys(item.extras).length > 0 ? `
                        <div class="receipt-item-extras">
                            ${Object.entries(item.extras).map(([name, price]) =>
                                `<span>+ ${name} (€${parseFloat(price).toFixed(2)})</span>`
                            ).join(', ')}
                        </div>
                    ` : ''}
                    ${item.removals && item.removals.length > 0 ? `
                        <div class="receipt-item-removals">
                            ${item.removals.join(', ')}
                        </div>
                    ` : ''}
                </div>
                <button class="btn-remove-item" onclick="tableOrdersManager.removeItem(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `).join('');

        // Add cover charge if applicable
        if (order.has_cover_charge && order.cover_charge_total > 0) {
            itemsHtml += `
                <div class="receipt-item receipt-item-cover" style="background: #f8f9fa; border-left: 3px solid #17a2b8;">
                    <div class="receipt-item-header">
                        <strong><i class="fas fa-utensils me-2"></i>Coperto (${order.covers} x €${parseFloat(order.cover_charge_per_person).toFixed(2)})</strong>
                        <span class="receipt-item-price">€${parseFloat(order.cover_charge_total).toFixed(2)}</span>
                    </div>
                </div>
            `;
        }

        itemsContainer.innerHTML = itemsHtml;

        totalElement.textContent = `€${parseFloat(order.total_amount).toFixed(2)}`;
    }

    /**
     * Open product modal for selected dish
     */
    openProductModal(dish) {
        if (!this.currentTable) {
            this.showNotification('Seleziona prima un tavolo', 'error');
            return;
        }

        this.currentProduct = dish;

        // Set product info
        const nameElement = this.getElement('modalProductName');
        const priceDisplayElement = this.getElement('modalProductPriceDisplay');
        const customPriceElement = this.getElement('productCustomPrice');

        if (nameElement) nameElement.textContent = dish.name;
        if (priceDisplayElement) priceDisplayElement.textContent = `€${parseFloat(dish.price).toFixed(2)}`;
        if (customPriceElement) customPriceElement.value = parseFloat(dish.price).toFixed(2);

        // Reset form
        const quantityElement = this.getElement('productQuantity');
        const notesElement = this.getElement('productNotes');
        const segueElement = this.getElement('productSegue');

        if (quantityElement) quantityElement.value = 1;
        if (notesElement) notesElement.value = '';
        if (segueElement) segueElement.checked = false;

        // Reset checkboxes
        const extrasContainer = this.getElement('extrasContainer');
        const removalsContainer = this.getElement('removalsContainer');

        extrasContainer?.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        removalsContainer?.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);

        // Update total
        this.updateModalTotal();

        // Show modal
        const modal = this.getElement('productModal');
        if (modal) modal.style.display = this.isMobile ? 'flex' : 'block';
    }

    /**
     * Close product modal
     */
    closeProductModal() {
        const modal = this.getElement('productModal');
        if (modal) modal.style.display = 'none';
        this.currentProduct = null;
    }

    /**
     * Change quantity
     */
    changeQuantity(delta) {
        const quantityElement = this.getElement('productQuantity');
        if (!quantityElement) return;

        let newValue = parseInt(quantityElement.value) + delta;
        if (newValue < 1) newValue = 1;

        quantityElement.value = newValue;
        this.updateModalTotal();
    }

    /**
     * Update modal total
     */
    updateModalTotal() {
        if (!this.currentProduct) return;

        const quantityElement = this.getElement('productQuantity');
        const customPriceElement = this.getElement('productCustomPrice');
        const totalElement = this.getElement('modalTotal');

        if (!quantityElement || !totalElement) return;

        const quantity = parseInt(quantityElement.value) || 1;

        // Use custom price if available, otherwise use product price
        let basePrice = customPriceElement ? parseFloat(customPriceElement.value) : parseFloat(this.currentProduct.price);
        if (isNaN(basePrice)) basePrice = parseFloat(this.currentProduct.price);

        let total = basePrice;

        // Add extras
        const extrasContainer = this.getElement('extrasContainer');
        const checkedExtras = extrasContainer?.querySelectorAll('input[type="checkbox"]:checked') || [];

        checkedExtras.forEach(checkbox => {
            total += parseFloat(checkbox.value);
        });

        total *= quantity;

        totalElement.textContent = `€${total.toFixed(2)}`;
    }

    /**
     * Add product to table
     */
    async addProductToTable() {
        if (!this.currentTable || !this.currentProduct) return;

        // Request operator authentication
        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) {
            console.log('Authentication cancelled');
            return;
        }

        const quantityElement = this.getElement('productQuantity');
        const notesElement = this.getElement('productNotes');
        const segueElement = this.getElement('productSegue');
        const customPriceElement = this.getElement('productCustomPrice');
        const extrasContainer = this.getElement('extrasContainer');
        const removalsContainer = this.getElement('removalsContainer');

        // Get custom price
        let customPrice = customPriceElement ? parseFloat(customPriceElement.value) : null;
        if (isNaN(customPrice)) customPrice = null;

        // Gather data
        const data = {
            dish_id: this.currentProduct.id,
            quantity: parseInt(quantityElement?.value || 1),
            notes: notesElement?.value || null,
            segue: segueElement?.checked || false,
            custom_price: customPrice,
            extras: {},
            removals: [],
            operator_token: auth.token
        };

        // Get extras
        const checkedExtras = extrasContainer?.querySelectorAll('input[type="checkbox"]:checked') || [];
        checkedExtras.forEach(checkbox => {
            const name = checkbox.dataset.name;
            const price = parseFloat(checkbox.value);
            data.extras[name] = price;
        });

        // Get removals
        const checkedRemovals = removalsContainer?.querySelectorAll('input[type="checkbox"]:checked') || [];
        checkedRemovals.forEach(checkbox => {
            data.removals.push(checkbox.dataset.name);
        });

        try {
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/items`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Prodotto aggiunto con successo');
                this.closeProductModal();
                // Reload table details
                await this.selectTable(this.currentTable.table.id);
                // Reload tables to update status
                await this.loadTables();
                // Update modify overlay if open
                const modifyOverlay = document.getElementById('modifyOrderOverlay');
                if (modifyOverlay && modifyOverlay.style.display === 'block') {
                    this.updateModifyReceiptItems();
                }
            } else {
                this.showNotification(result.message || 'Errore nell\'aggiunta del prodotto', 'error');
            }
        } catch (error) {
            console.error('Error adding product:', error);
            this.showNotification('Errore nell\'aggiunta del prodotto', 'error');
        }
    }

    /**
     * Remove item from order
     */
    async removeItem(itemId) {
        if (!confirm('Vuoi rimuovere questo prodotto?')) return;

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
            const response = await fetch(`${this.apiBase}/items/${itemId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Prodotto rimosso');
                await this.selectTable(this.currentTable.table.id);
                await this.loadTables();
                // Update modify overlay if open
                const modifyOverlay = document.getElementById('modifyOrderOverlay');
                if (modifyOverlay && modifyOverlay.style.display === 'block') {
                    this.updateModifyReceiptItems();
                }
            } else {
                this.showNotification(result.message || 'Errore nella rimozione', 'error');
            }
        } catch (error) {
            console.error('Error removing item:', error);
            this.showNotification('Errore nella rimozione', 'error');
        }
    }

    /**
     * Increase item quantity
     */
    async increaseQuantity(itemId, event) {
        if (event) event.stopPropagation();

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
            // Find the current item
            const item = this.currentTable.order.items.find(i => i.id === itemId);
            if (!item) {
                this.showNotification('Prodotto non trovato', 'error');
                return;
            }

            const newQuantity = item.quantity + 1;
            await this.updateItemQuantity(itemId, newQuantity, auth.token);
        } catch (error) {
            console.error('Error increasing quantity:', error);
            this.showNotification('Errore nell\'aggiornamento della quantità', 'error');
        }
    }

    /**
     * Decrease item quantity
     */
    async decreaseQuantity(itemId, event) {
        if (event) event.stopPropagation();

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
            // Find the current item
            const item = this.currentTable.order.items.find(i => i.id === itemId);
            if (!item) {
                this.showNotification('Prodotto non trovato', 'error');
                return;
            }

            const newQuantity = item.quantity - 1;

            // If quantity becomes 0, remove the item
            if (newQuantity <= 0) {
                if (confirm('Rimuovere questo prodotto dall\'ordine?')) {
                    await this.removeItem(itemId);
                }
                return;
            }

            await this.updateItemQuantity(itemId, newQuantity, auth.token);
        } catch (error) {
            console.error('Error decreasing quantity:', error);
            this.showNotification('Errore nell\'aggiornamento della quantità', 'error');
        }
    }

    /**
     * Update item quantity via API
     */
    async updateItemQuantity(itemId, newQuantity, token) {
        try {
            const response = await fetch(`${this.apiBase}/items/${itemId}/quantity`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': token
                },
                body: JSON.stringify({ quantity: newQuantity })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Quantità aggiornata');
                await this.selectTable(this.currentTable.table.id);
                await this.loadTables();

                // Update modify overlay if open
                const modifyOverlay = document.getElementById('modifyOrderOverlay');
                if (modifyOverlay && modifyOverlay.style.display === 'block') {
                    this.updateModifyReceiptItems();
                }
            } else {
                this.showNotification(result.message || 'Errore nell\'aggiornamento', 'error');
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            this.showNotification('Errore nell\'aggiornamento della quantità', 'error');
        }
    }

    /**
     * Show price edit controls for an item
     */
    editPrice(itemId, currentPrice) {
        const display = document.getElementById(`priceDisplay_${itemId}`);
        const edit = document.getElementById(`priceEdit_${itemId}`);
        const input = document.getElementById(`priceInput_${itemId}`);
        if (display) display.style.display = 'none';
        if (edit) edit.style.display = 'inline';
        if (input) { input.focus(); input.select(); }
    }

    /**
     * Cancel price edit
     */
    cancelEditPrice(itemId) {
        const display = document.getElementById(`priceDisplay_${itemId}`);
        const edit = document.getElementById(`priceEdit_${itemId}`);
        if (display) display.style.display = 'inline';
        if (edit) edit.style.display = 'none';
    }

    /**
     * Save new price for an item
     */
    async savePrice(itemId) {
        const input = document.getElementById(`priceInput_${itemId}`);
        if (!input) return;

        const newPrice = parseFloat(input.value);
        if (isNaN(newPrice) || newPrice < 0) {
            this.showNotification('Prezzo non valido', 'error');
            return;
        }

        // Request operator authentication
        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) {
            return;
        }

        try {
            const response = await fetch(`${this.apiBase}/items/${itemId}/price`, {
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
                this.showNotification('Prezzo aggiornato');
                await this.selectTable(this.currentTable.table.id);
                await this.loadTables();
                const modifyOverlay = document.getElementById('modifyOrderOverlay');
                if (modifyOverlay && modifyOverlay.style.display === 'block') {
                    this.updateModifyReceiptItems();
                }
            } else {
                this.showNotification(result.message || 'Errore nell\'aggiornamento del prezzo', 'error');
            }
        } catch (error) {
            console.error('Error updating price:', error);
            this.showNotification('Errore nell\'aggiornamento del prezzo', 'error');
        }
    }

    /**
     * Show covers edit controls
     */
    editCovers() {
        const display = document.getElementById('coversDisplay');
        const edit = document.getElementById('coversEdit');
        if (display) display.style.display = 'none';
        if (edit) edit.style.display = 'inline';
    }

    /**
     * Cancel covers edit
     */
    cancelEditCovers() {
        const display = document.getElementById('coversDisplay');
        const edit = document.getElementById('coversEdit');
        if (display) display.style.display = 'inline';
        if (edit) edit.style.display = 'none';
    }

    /**
     * Change covers value in edit mode
     */
    changeCovers(delta) {
        const valueEl = document.getElementById('coversEditValue');
        if (!valueEl) return;
        let current = parseInt(valueEl.textContent) || 0;
        current += delta;
        if (current < 0) current = 0;
        valueEl.textContent = current;
    }

    /**
     * Save new covers value
     */
    async saveCovers() {
        const valueEl = document.getElementById('coversEditValue');
        if (!valueEl) return;

        const newCovers = parseInt(valueEl.textContent) || 0;

        // Request operator authentication
        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) {
            return;
        }

        try {
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/covers`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token
                },
                body: JSON.stringify({ covers: newCovers })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Coperti aggiornati');
                await this.selectTable(this.currentTable.table.id);
                await this.loadTables();
                const modifyOverlay = document.getElementById('modifyOrderOverlay');
                if (modifyOverlay && modifyOverlay.style.display === 'block') {
                    this.updateModifyReceiptItems();
                }
            } else {
                this.showNotification(result.message || 'Errore nell\'aggiornamento dei coperti', 'error');
            }
        } catch (error) {
            console.error('Error updating covers:', error);
            this.showNotification('Errore nell\'aggiornamento dei coperti', 'error');
        }
    }

    /**
     * Clear table
     */
    async clearTable() {
        if (!this.currentTable) return;
        if (!confirm('Vuoi svuotare il tavolo?')) return;

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
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/clear`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Tavolo svuotato');
                this.currentTable = null;
                await this.loadTables();

                // Hide receipt overlay
                const receiptOverlay = this.getElement('receiptOverlay');
                if (receiptOverlay) receiptOverlay.style.display = 'none';

                // Hide modify overlay
                const modifyOverlay = document.getElementById('modifyOrderOverlay');
                if (modifyOverlay) modifyOverlay.style.display = 'none';

                // Disable MODIFICA button (desktop)
                if (!this.isMobile) {
                    const modifyBtn = document.getElementById('btnModifyTable');
                    if (modifyBtn) modifyBtn.setAttribute('disabled', 'disabled');
                }

                // Hide mobile elements
                if (this.isMobile) {
                    const manageModal = document.getElementById('manageModalMobile');
                    if (manageModal) {
                        manageModal.classList.remove('active');
                        manageModal.style.display = 'none';
                    }
                    const actionBar = document.getElementById('mobileActionBar');
                    if (actionBar) actionBar.style.display = 'none';
                }
            } else {
                this.showNotification(result.message || 'Errore nello svuotamento', 'error');
            }
        } catch (error) {
            console.error('Error clearing table:', error);
            this.showNotification('Errore nello svuotamento', 'error');
        }
    }

    /**
     * Pay table
     */
    async payTable() {
        if (!this.currentTable) return;
        if (!confirm('Confermi l\'incasso del conto?')) return;

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
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/pay`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(`Conto incassato: €${parseFloat(result.data.total_paid).toFixed(2)}`);
                this.currentTable = null;
                await this.loadTables();

                // Hide receipt overlay
                const receiptOverlay = this.getElement('receiptOverlay');
                if (receiptOverlay) receiptOverlay.style.display = 'none';

                // Hide modify overlay
                const modifyOverlay = document.getElementById('modifyOrderOverlay');
                if (modifyOverlay) modifyOverlay.style.display = 'none';

                // Disable MODIFICA button (desktop)
                if (!this.isMobile) {
                    const modifyBtn = document.getElementById('btnModifyTable');
                    if (modifyBtn) modifyBtn.setAttribute('disabled', 'disabled');
                }

                // Hide mobile elements
                if (this.isMobile) {
                    const manageModal = document.getElementById('manageModalMobile');
                    if (manageModal) {
                        manageModal.classList.remove('active');
                        manageModal.style.display = 'none';
                    }
                    const actionBar = document.getElementById('mobileActionBar');
                    if (actionBar) actionBar.style.display = 'none';
                }
            } else {
                this.showNotification(result.message || 'Errore nell\'incasso', 'error');
            }
        } catch (error) {
            console.error('Error paying table:', error);
            this.showNotification('Errore nell\'incasso', 'error');
        }
    }

    /**
     * Send "Marcia Tavolo" command to all printers
     */
    async marciaTavolo() {
        if (!this.currentTable) {
            this.showNotification('Seleziona prima un tavolo', 'error');
            return;
        }

        if (!confirm('Inviare MARCIA TAVOLO alle stampanti?')) return;

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
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/marcia`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Marcia tavolo inviata con successo', 'success');
            } else {
                this.showNotification(result.message || 'Errore nell\'invio della marcia', 'error');
            }
        } catch (error) {
            console.error('Error sending marcia tavolo:', error);
            this.showNotification('Errore nell\'invio della marcia', 'error');
        }
    }

    /**
     * Open PreConto modal
     */
    openPrecontoModal() {
        if (!this.currentTable || !this.currentTable.order) {
            this.showNotification('Seleziona prima un tavolo con un ordine attivo', 'error');
            return;
        }

        const modal = document.getElementById('precontoModal');
        const tableNumberEl = document.getElementById('precontoTableNumber');
        const totalAmountEl = document.getElementById('precontoTotalAmount');

        if (tableNumberEl) {
            tableNumberEl.textContent = this.currentTable.table.table_number;
        }
        if (totalAmountEl) {
            totalAmountEl.textContent = `€${parseFloat(this.currentTable.order.total_amount).toFixed(2)}`;
        }

        // Reset form
        const fullRadio = document.querySelector('input[name="precontoType"][value="full"]');
        if (fullRadio) fullRadio.checked = true;

        const splitContainer = document.getElementById('splitCountContainer');
        if (splitContainer) splitContainer.style.display = 'none';

        const splitCountInput = document.getElementById('splitCount');
        if (splitCountInput) splitCountInput.value = 2;

        this.updateSplitPreview();

        // Show modal
        if (modal) modal.style.display = 'flex';

        // Setup event listeners
        this.setupPrecontoModalListeners();
    }

    /**
     * Setup PreConto modal event listeners
     */
    setupPrecontoModalListeners() {
        const self = this;

        // Radio buttons for preconto type
        document.querySelectorAll('input[name="precontoType"]').forEach(radio => {
            radio.onchange = function() {
                const splitContainer = document.getElementById('splitCountContainer');
                if (this.value === 'split') {
                    splitContainer.style.display = 'block';
                    self.updateSplitPreview();
                } else {
                    splitContainer.style.display = 'none';
                }
            };
        });

        // Split count controls
        const decreaseBtn = document.getElementById('decreaseSplit');
        const increaseBtn = document.getElementById('increaseSplit');
        const splitInput = document.getElementById('splitCount');

        if (decreaseBtn) {
            decreaseBtn.onclick = () => {
                const current = parseInt(splitInput.value) || 2;
                if (current > 2) {
                    splitInput.value = current - 1;
                    this.updateSplitPreview();
                }
            };
        }

        if (increaseBtn) {
            increaseBtn.onclick = () => {
                const current = parseInt(splitInput.value) || 2;
                if (current < 20) {
                    splitInput.value = current + 1;
                    this.updateSplitPreview();
                }
            };
        }

        if (splitInput) {
            splitInput.oninput = () => this.updateSplitPreview();
        }

        // Close buttons
        const closeBtn = document.getElementById('closePrecontoModal');
        const cancelBtn = document.getElementById('cancelPreconto');

        if (closeBtn) closeBtn.onclick = () => this.closePrecontoModal();
        if (cancelBtn) cancelBtn.onclick = () => this.closePrecontoModal();

        // Confirm button
        const confirmBtn = document.getElementById('confirmPreconto');
        if (confirmBtn) {
            confirmBtn.onclick = () => this.printPreconto();
        }
    }

    /**
     * Update split preview amount
     */
    updateSplitPreview() {
        if (!this.currentTable || !this.currentTable.order) return;

        const splitInput = document.getElementById('splitCount');
        const perPersonEl = document.getElementById('perPersonAmount');

        if (!splitInput || !perPersonEl) return;

        const splitCount = parseInt(splitInput.value) || 2;
        const total = parseFloat(this.currentTable.order.total_amount) || 0;
        const perPerson = total / splitCount;

        perPersonEl.textContent = `€${perPerson.toFixed(2)}`;
    }

    /**
     * Close PreConto modal
     */
    closePrecontoModal() {
        const modal = document.getElementById('precontoModal');
        if (modal) modal.style.display = 'none';
    }

    /**
     * Print PreConto
     */
    async printPreconto() {
        if (!this.currentTable) return;

        // Request operator authentication
        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) {
            console.log('Authentication cancelled');
            return;
        }

        // Get split count if applicable
        const precontoType = document.querySelector('input[name="precontoType"]:checked')?.value;
        let splitCount = null;

        if (precontoType === 'split') {
            splitCount = parseInt(document.getElementById('splitCount').value) || null;
        }

        try {
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/preconto`, {
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
                this.showNotification(result.message || 'PreConto stampato con successo', 'success');
                this.closePrecontoModal();
            } else {
                this.showNotification(result.message || 'Errore nella stampa del PreConto', 'error');
            }
        } catch (error) {
            console.error('Error printing preconto:', error);
            this.showNotification('Errore nella stampa del PreConto', 'error');
        }
    }

    /**
     * Format elapsed time from opened_at timestamp
     */
    formatElapsedTime(openedAt) {
        const now = new Date();
        const opened = new Date(openedAt);
        const diffMs = now - opened;

        const diffMinutes = Math.floor(diffMs / 60000);
        const hours = Math.floor(diffMinutes / 60);
        const minutes = diffMinutes % 60;

        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        } else {
            return `${minutes}m`;
        }
    }

    /**
     * Update all timers
     */
    updateTimers() {
        const timers = document.querySelectorAll('.table-timer, .mobile-table-timer');
        timers.forEach(timer => {
            const openedAt = timer.dataset.openedAt;
            if (openedAt) {
                const timeText = this.formatElapsedTime(openedAt);
                // Update only the text, keep the icon if present
                if (timer.querySelector('i')) {
                    timer.innerHTML = `<i class="fas fa-clock"></i> ${timeText}`;
                } else {
                    timer.textContent = timeText;
                }
            }
        });
    }

    /**
     * Start interval to update timers every minute
     */
    startTimerUpdates() {
        // Update immediately
        this.updateTimers();

        // Then update every minute
        setInterval(() => {
            this.updateTimers();
        }, 60000); // 60 seconds
    }

    /**
     * Add product to temporary cart
     */
    addToCart() {
        if (!this.currentTable || !this.currentProduct) return;

        const quantityElement = this.getElement('productQuantity');
        const notesElement = this.getElement('productNotes');
        const segueElement = this.getElement('productSegue');
        const customPriceElement = this.getElement('productCustomPrice');
        const extrasContainer = this.getElement('extrasContainer');
        const removalsContainer = this.getElement('removalsContainer');

        // Get custom price
        let customPrice = customPriceElement ? parseFloat(customPriceElement.value) : null;
        if (isNaN(customPrice)) customPrice = null;

        // Gather data
        const extras = {};
        const removals = [];

        // Get extras
        const checkedExtras = extrasContainer?.querySelectorAll('input[type="checkbox"]:checked') || [];
        checkedExtras.forEach(checkbox => {
            const name = checkbox.dataset.name;
            const price = parseFloat(checkbox.value);
            extras[name] = price;
        });

        // Get removals
        const checkedRemovals = removalsContainer?.querySelectorAll('input[type="checkbox"]:checked') || [];
        checkedRemovals.forEach(checkbox => {
            removals.push(checkbox.dataset.name);
        });

        // Use custom price if available, otherwise use product price
        const unitPrice = customPrice !== null ? customPrice : parseFloat(this.currentProduct.price);

        // Create cart item
        const cartItem = {
            dish_id: this.currentProduct.id,
            dish_name: this.currentProduct.name,
            dish_price: unitPrice,
            custom_price: customPrice,
            quantity: parseInt(quantityElement?.value || 1),
            notes: notesElement?.value || null,
            segue: segueElement?.checked || false,
            extras: Object.keys(extras).length > 0 ? extras : null,
            removals: removals.length > 0 ? removals : null,
        };

        // Calculate item total
        let itemTotal = unitPrice;
        Object.values(extras).forEach(price => {
            itemTotal += price;
        });
        itemTotal *= cartItem.quantity;
        cartItem.total = itemTotal;

        // Add to cart
        this.temporaryCart.push(cartItem);

        // Show notification
        this.showNotification(`${cartItem.dish_name} aggiunto al carrello`);

        // Update cart display
        this.updateCartDisplay();

        // Close modal
        this.closeProductModal();
    }

    /**
     * Update cart display
     */
    updateCartDisplay() {
        // Update both cart displays (main and modify overlay)
        this.updateSingleCartDisplay('temporaryCart', 'cartItems');
        this.updateSingleCartDisplay('temporaryCartModify', 'cartItemsModify');
    }

    /**
     * Update a single cart display
     */
    updateSingleCartDisplay(cartContainerId, cartItemsContainerId) {
        const cartContainer = document.getElementById(cartContainerId);
        const cartItemsContainer = document.getElementById(cartItemsContainerId);

        if (!cartContainer || !cartItemsContainer) return;

        if (this.temporaryCart.length === 0) {
            cartContainer.style.display = 'none';
            return;
        }

        cartContainer.style.display = 'block';

        // Render cart items
        cartItemsContainer.innerHTML = this.temporaryCart.map((item, index) => {
            let extrasHtml = '';
            if (item.extras && Object.keys(item.extras).length > 0) {
                extrasHtml = '<div style="font-size: 0.8rem; color: #28a745;">' +
                    Object.entries(item.extras).map(([name, price]) =>
                        `<span>+ ${name} (€${parseFloat(price).toFixed(2)})</span>`
                    ).join(', ') +
                    '</div>';
            }

            let removalsHtml = '';
            if (item.removals && item.removals.length > 0) {
                removalsHtml = '<div style="font-size: 0.8rem; color: #dc3545;">' +
                    item.removals.map(removal => `<span>- ${removal}</span>`).join(', ') +
                    '</div>';
            }

            let notesHtml = '';
            if (item.notes) {
                notesHtml = `<div style="font-size: 0.8rem; color: #6c757d; font-style: italic;">${item.notes}</div>`;
            }

            let segueHtml = '';
            if (item.segue) {
                segueHtml = `<span style="background: #dc3545; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 3px; margin-left: 8px;">SEGUE</span>`;
            }

            return `
                <div style="background: white; padding: 8px; margin-bottom: 8px; border-radius: 4px; border: 1px solid #dee2e6;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <strong style="font-size: 0.9rem;">${item.quantity}x ${item.dish_name}</strong>${segueHtml}
                            ${notesHtml}
                            ${extrasHtml}
                            ${removalsHtml}
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-weight: 600; color: #dc3545;">€${item.total.toFixed(2)}</span>
                            <button onclick="tableOrdersManager.removeFromCart(${index})"
                                    style="background: #dc3545; border: none; color: white; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 0.8rem;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Remove item from cart
     */
    removeFromCart(index) {
        this.temporaryCart.splice(index, 1);
        this.updateCartDisplay();
        this.showNotification('Prodotto rimosso dal carrello');
    }

    /**
     * Clear temporary cart
     */
    clearTemporaryCart() {
        if (!confirm('Vuoi svuotare il carrello?')) return;
        this.temporaryCart = [];
        this.updateCartDisplay();
        this.showNotification('Carrello svuotato');
    }

    /**
     * Confirm cart and add all items to order
     */
    async confirmCart() {
        if (!this.currentTable) {
            this.showNotification('Seleziona prima un tavolo', 'error');
            return;
        }

        if (this.temporaryCart.length === 0) {
            this.showNotification('Il carrello è vuoto', 'error');
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
            // Prepare items data
            const items = this.temporaryCart.map(item => ({
                dish_id: item.dish_id,
                quantity: item.quantity,
                notes: item.notes,
                segue: item.segue || false,
                custom_price: item.custom_price || null,
                extras: item.extras,
                removals: item.removals,
            }));

            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/items-multiple`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({
                    items: items,
                    operator_token: auth.token
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message);
                // Clear cart
                this.temporaryCart = [];
                this.updateCartDisplay();
                // Reload table details
                await this.selectTable(this.currentTable.table.id);
                // Reload tables to update status
                await this.loadTables();
                // Update modify overlay if open
                const modifyOverlay = document.getElementById('modifyOrderOverlay');
                if (modifyOverlay && modifyOverlay.style.display === 'block') {
                    this.updateModifyReceiptItems();
                }
            } else {
                this.showNotification(result.message || 'Errore nell\'aggiunta dei prodotti', 'error');
            }
        } catch (error) {
            console.error('Error confirming cart:', error);
            this.showNotification('Errore nell\'aggiunta dei prodotti', 'error');
        }
    }

    /**
     * Open Comunica modal
     */
    openComunicaModal() {
        const modal = document.getElementById('comunicaModal');
        const tableNumberInput = document.getElementById('comunicaTableNumber');
        const messageInput = document.getElementById('comunicaMessage');
        const printerSelect = document.getElementById('comunicaPrinterSelect');

        // Reset form
        if (messageInput) messageInput.value = '';
        if (printerSelect) printerSelect.value = '';

        // Set table number if available
        if (tableNumberInput) {
            if (this.currentTable && this.currentTable.table) {
                tableNumberInput.value = 'Tavolo ' + this.currentTable.table.table_number;
            } else {
                tableNumberInput.value = '';
            }
        }

        // Show modal
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    /**
     * Close Comunica modal
     */
    closeComunicaModal() {
        const modal = document.getElementById('comunicaModal');
        if (modal) modal.style.display = 'none';
    }

    /**
     * Send Comunica message to printer
     */
    async sendComunica() {
        const printerSelect = document.getElementById('comunicaPrinterSelect');
        const messageInput = document.getElementById('comunicaMessage');

        const printerId = printerSelect?.value;
        const message = messageInput?.value?.trim();

        // Validate
        if (!printerId) {
            this.showNotification('Seleziona una stampante', 'error');
            return;
        }

        if (!message) {
            this.showNotification('Inserisci un messaggio', 'error');
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

            // Add table_id if a table is selected
            if (this.currentTable && this.currentTable.table) {
                requestData.table_id = this.currentTable.table.id;
            }

            const response = await fetch(`${this.apiBase}/comunica`, {
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
                this.showNotification(result.message || 'Comunicazione inviata con successo', 'success');
                this.closeComunicaModal();
            } else {
                this.showNotification(result.message || 'Errore nell\'invio della comunicazione', 'error');
            }
        } catch (error) {
            console.error('Error sending comunica:', error);
            this.showNotification('Errore nell\'invio della comunicazione', 'error');
        }
    }
}

// Initialize manager when DOM is ready
let tableOrdersManager;
document.addEventListener('DOMContentLoaded', () => {
    const isMobile = window.innerWidth <= 768 || document.getElementById('mainViewMobile') !== null;
    tableOrdersManager = new TableOrdersManager(isMobile);
});
