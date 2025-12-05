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
        }

        // Update MODIFICA button state (only for desktop)
        if (!this.isMobile) {
            this.updateModifyButtonState();
        }

        // Update receipt items
        this.updateReceiptItems();
    }

    /**
     * Update MODIFICA button state based on table status
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
        if (order && order.covers && modifyCoversInfo && modifyCoversCount) {
            modifyCoversCount.textContent = order.covers;
            modifyCoversInfo.style.display = 'block';
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


        itemsContainer.innerHTML = order.items.map(item => `
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
                        <span class="receipt-item-price">€${parseFloat(item.subtotal).toFixed(2)}</span><br />
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
        if (order && order.covers && coversInfo && coversCount) {
            coversCount.textContent = order.covers;
            coversInfo.style.display = 'inline';
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

        itemsContainer.innerHTML = order.items.map(item => `
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
        const priceElement = this.getElement('modalProductPrice');

        if (nameElement) nameElement.textContent = dish.name;
        if (priceElement) priceElement.textContent = `€${parseFloat(dish.price).toFixed(2)}`;

        // Reset form
        const quantityElement = this.getElement('productQuantity');
        const notesElement = this.getElement('productNotes');

        if (quantityElement) quantityElement.value = 1;
        if (notesElement) notesElement.value = '';

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
        const totalElement = this.getElement('modalTotal');

        if (!quantityElement || !totalElement) return;

        const quantity = parseInt(quantityElement.value) || 1;
        let total = parseFloat(this.currentProduct.price);

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
        const extrasContainer = this.getElement('extrasContainer');
        const removalsContainer = this.getElement('removalsContainer');

        // Gather data
        const data = {
            dish_id: this.currentProduct.id,
            quantity: parseInt(quantityElement?.value || 1),
            notes: notesElement?.value || null,
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

                // Disable MODIFICA button
                if (!this.isMobile) {
                    const modifyBtn = document.getElementById('btnModifyTable');
                    if (modifyBtn) modifyBtn.setAttribute('disabled', 'disabled');
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

                // Disable MODIFICA button
                if (!this.isMobile) {
                    const modifyBtn = document.getElementById('btnModifyTable');
                    if (modifyBtn) modifyBtn.setAttribute('disabled', 'disabled');
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
}

// Initialize manager when DOM is ready
let tableOrdersManager;
document.addEventListener('DOMContentLoaded', () => {
    const isMobile = window.innerWidth <= 768 || document.getElementById('mainViewMobile') !== null;
    tableOrdersManager = new TableOrdersManager(isMobile);
});
