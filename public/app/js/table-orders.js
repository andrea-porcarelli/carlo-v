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
        this.allTables = [];

        this.modifySession = {
            active: false,
            token: null,
            permissions: [],
            items: [],
            pendingAdd: [],
            pendingRemove: [],
            pendingUpdate: {},
            pendingDishChange: [],
            itemCounter: -1,
        };
        this._pendingNewDish = null;
        this._dishCache = null;
        this._dishChangeSelectedDish = null;
        this._dishChangeActiveCategoryId = null;
        this._cashDrawerOperationId = null;
        this._cashDrawerPollInterval = null;
        this._cashDrawerToken = null;
        this._cashDrawerOnComplete = null;
        this._beforeUnloadHandler = (e) => {
            e.preventDefault();
            e.returnValue = 'Pagamento in corso. Sei sicuro di voler uscire?';
        };

        this.menuOptions = { extras: [], removals: [] };

        this.init();
    }

    /**
     * Initialize event listeners
     */
    init() {
        this.attachModalEvents();
        this.loadTables();
        this.startTimerUpdates();
        this.loadMenuOptions();
    }

    async loadMenuOptions() {
        try {
            const resp = await fetch('/api/menu-options');
            const result = await resp.json();
            if (result.success) this.menuOptions = result.data;
        } catch (e) { /* silenzioso */ }
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
        this.getElement('addProductBtn')?.addEventListener('click', () => this.addProductToSession());

        // Close modify overlay button
        document.getElementById('closeModifyBtn')?.addEventListener('click', () => this.closeModifyOverlay());
        document.getElementById('closeModifyNoPrintBtn')?.addEventListener('click', () => this.closeModifyOverlay({ skipPrint: true }));
        document.getElementById('btnInviaOrdine')?.addEventListener('click', () => this.submitSessionKeepOpen());

        // Banco button
        document.getElementById('btnBanco')?.addEventListener('click', () => this.openBanco());

        // Reprint order button
        document.getElementById('btnRistampaOrdine')?.addEventListener('click', () => this.reprintOrder());

        // Move table button
        document.getElementById('btnSpostaTavolo')?.addEventListener('click', () => this.openMoveTableModal());

        // Move table modal cancel
        document.getElementById('cancelMoveTable')?.addEventListener('click', () => this.closeMoveTableModal());

        // Autoconsumo modal
        this._initAutoconsumoModal();
    }

    /**
     * Show notification with slide-in from bottom-left
     */
    showNotification(message, type = 'success', persistent = false) {
        const notification = this.getElement('notification');
        const notificationText = this.getElement('notificationText');
        const notificationClose = document.getElementById('notificationClose');

        if (notification && notificationText) {
            // Clear any pending hide timeouts
            if (notification._hideTimeout) {
                clearTimeout(notification._hideTimeout);
            }

            // Update content and style
            notificationText.textContent = message;
            notification.className = `notification ${type} show`;

            // Handle close button
            if (persistent) {
                if (notificationClose) notificationClose.style.display = 'block';
                notification.onclick = (e) => {
                    if (e.target !== notificationClose) return;
                    this._hideNotification();
                };
            } else {
                if (notificationClose) notificationClose.style.display = 'none';
                notification.onclick = null;
                // Auto-hide after 3.5 seconds
                notification._hideTimeout = setTimeout(() => {
                    this._hideNotification();
                }, 3500);
            }
        }
    }

    /**
     * Hide notification with fade-out animation
     */
    _hideNotification() {
        const notification = this.getElement('notification');
        if (!notification) return;

        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.classList.remove('fade-out', 'show');
            notification.className = 'notification';
        }, 300);
    }

    /**
     * Load all tables (and banco orders separately for the summary)
     */
    async loadTables() {
        try {
            const resp = await fetch(this.apiBase);
            const result = await resp.json();
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
        this.allTables = tables;
        const container = document.getElementById(
            this.isMobile ? 'tablesContainerMobile' : 'tablesContainer'
        );

        if (!container) return;

        container.innerHTML = tables
            .filter(table => !table.is_banco)
            .map(table => {
                const tableClass = table.status === 'free' ? 'free' : (table.has_preconto ? 'preconto' : 'occupied');
                return `
                <div class="table-item table-${tableClass}" data-table="${table.id}">
                    <div class="table-number">${table.table_number}</div>
                    ${table.has_active_order ? `
                        ${(() => {
                            const current = parseFloat(table.current_total);
                            const remaining = parseFloat(table.remaining_total ?? current);
                            const hasPaid = remaining < current - 0.005;
                            if (hasPaid) {
                                return `<div title="${table.active_order.autoconsumo ? ' Autoconsumo' : ''}" class="table-total ${table.active_order.autoconsumo ? 'autoconsumo' : ''}" style="line-height:1.2;">
                                    <span style="text-decoration:line-through;font-size:0.75em;opacity:0.6;">€${current.toFixed(2)}</span><br>
                                    <span>€${remaining.toFixed(2)}</span>
                                </div>`;
                            }
                            return `<div title="${table.active_order.autoconsumo ? ' Autoconsumo' : ''}" class="table-total ${table.active_order.autoconsumo ? 'autoconsumo' : ''}">€${current.toFixed(2)}</div>`;
                        })()}
                        <div class="table-timer" data-opened-at="${table.active_order.opened_at}">
                            <i class="fas fa-clock"></i> ${this.formatElapsedTime(table.active_order.opened_at)}
                        </div>
                    ` : ''}
                </div>`;
            }).join('');

        // Attach click events to tables
        const tableElements = container.querySelectorAll('.table-item');
        tableElements.forEach(card => {
            card.addEventListener('click', () => {
                const tableId = card.dataset.table;

                // Remove previous selection
                tableElements.forEach(t => t.classList.remove('table-selected'));

                // Add selection to current table
                card.classList.add('table-selected');

                this.selectTable(tableId);

                // Haptic feedback on mobile
                if (this.isMobile && navigator.vibrate) {
                    navigator.vibrate(50);
                }
            });
        });

        this.updateStats();
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
    updateStats() {
        // Summary panel removed — nothing to update
    }

    /**
     * Select a table
     */
    async selectTable(tableId) {
        try {
            const response = await fetch(`${this.apiBase}/${tableId}`);
            const result = await response.json();

            if (!result.success) return;
            this.currentTable = result.data;

            const isFreeWithoutOrder = this.currentTable.table.status === 'free' && !this.currentTable.order;

            if (isFreeWithoutOrder) {
                // Ask covers then auth, then open table, then directly open overlay
                try {
                    const covers = await coversManager.requestCovers(this.currentTable.table.table_number);
                    const auth = await operatorAuthManager.requestAuth();
                    if (!auth) {
                        this.currentTable = null;
                        this.showNotification('Autenticazione annullata', 'error');
                        return;
                    }
                    console.log(auth.permissions)
                    if (!(auth.permissions).includes('take_orders')) {
                        this.currentTable = null;
                        this.showNotification('Non hai il permesso di prendere comande', 'error');
                        return;
                    }

                    await this.openTableWithCovers(tableId, covers, auth.token);

                    // Re-fetch table data after opening
                    const resp2 = await fetch(`${this.apiBase}/${tableId}`);
                    const res2 = await resp2.json();
                    if (res2.success) {
                        this.currentTable = res2.data;
                        this.modifySession.token = auth.token;
                        this.modifySession.permissions = auth.permissions ?? [];
                        this.modifySession.active = true;
                        this._initSessionFromOrder(this.currentTable.order);
                        this.openModifyOverlay();
                    }
                } catch (error) {
                    this.currentTable = null;
                    this.showNotification('Operazione annullata', 'error');
                }
            } else if (this.currentTable.table.status === 'occupied' && this.currentTable.order) {
                // Occupied table: auth → session → overlay
                let auth;
                try {
                    // Prepare dishes data for preview in auth modal
                    const dishes = this.currentTable.order.items
                        ? this.currentTable.order.items.map(item => ({
                            quantity: item.quantity,
                            name: item.dish_name || 'Prodotto'
                          }))
                        : null;

                    auth = await operatorAuthManager.requestAuth(null, dishes);
                } catch (error) {
                    this.currentTable = null;
                    return;
                }
                if (!auth) { this.currentTable = null; return; }
                if (!(auth.permissions ?? []).includes('view_orders')) {
                    this.currentTable = null;
                    this.showNotification('Non hai il permesso di visualizzare le comande', 'error');
                    return;
                }

                this.modifySession.token = auth.token;
                this.modifySession.permissions = auth.permissions ?? [];
                this.modifySession.active = true;
                this._initSessionFromOrder(this.currentTable.order);
                this.openModifyOverlay();
            } else {
                this.showTableDetails();
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
     * Initialize modify session from current order data
     */
    _initSessionFromOrder(order) {
        this.modifySession.items = order && order.items
            ? order.items.map(item => ({ ...item, _isNew: false }))
            : [];
        this.modifySession.pendingAdd = [];
        this.modifySession.pendingRemove = [];
        this.modifySession.pendingUpdate = {};
        this.modifySession.pendingDishChange = [];
        this.modifySession.itemCounter = -1;
        this.modifySession.paidSplitsTotal = parseFloat(order?.paid_splits_total || 0);
        this.modifySession.paidCoversTotal = parseFloat(order?.paid_covers_total || 0);
        this.modifySession.pendingSplits = order?.pending_splits ?? [];

        // Remove/reduce items already paid via preconto splits
        const paidItems = order?.paid_items ?? [];
        if (paidItems.length > 0) {
            this._applyPaidItemsToSession(paidItems);
        }

        // Restore persisted discount from DB
        if (order && order.discount_type && order.discount_amount) {
            this._authorizedDiscount = { type: order.discount_type, value: parseFloat(order.discount_amount) };
            const discountInput = document.getElementById('modifyDiscountInput');
            const btnApply = document.getElementById('btnApplyDiscount');
            const btnReset = document.getElementById('btnResetDiscount');
            const discountTypePct = document.getElementById('discountTypePct');
            const discountTypeVal = document.getElementById('discountTypeVal');
            if (discountInput) { discountInput.value = order.discount_amount; discountInput.disabled = true; }
            if (btnApply) btnApply.style.display = 'none';
            if (btnReset) btnReset.style.display = 'inline-flex';
            if (discountTypePct) discountTypePct.classList.toggle('active', order.discount_type === 'percent');
            if (discountTypeVal) discountTypeVal.classList.toggle('active', order.discount_type === 'value');
        } else {
            this._authorizedDiscount = null;
        }
    }

    /**
     * Remove/reduce session items already paid via preconto splits.
     * paidItems: [{order_item_id, paid_qty}, ...]
     */
    _applyPaidItemsToSession(paidItems) {
        for (const paid of paidItems) {
            const idx = this.modifySession.items.findIndex(i => i.id === paid.order_item_id);
            if (idx === -1) continue;
            const item = this.modifySession.items[idx];
            const remaining = item.quantity - paid.paid_qty;
            if (remaining <= 0) {
                this.modifySession.items.splice(idx, 1);
            } else {
                item.quantity = remaining;
                item.subtotal = parseFloat(
                    ((item.unit_price + this._itemExtrasTotal(item)) * remaining).toFixed(2)
                );
            }
        }
    }

    /**
     * Insert a segue separator after prevItemId.
     * For saved items (id > 0): persists to DB immediately.
     * For new items (id < 0): adds locally and queues in pendingAdd.
     */
    async addSegueAfter(prevItemId) {
        const idx = this.modifySession.items.findIndex(i => i.id === prevItemId);
        if (idx === -1) return;

        if (prevItemId < 0) {
            // Unsaved item: create local segue only
            const localId = this.modifySession.itemCounter--;
            const segueItem = {
                id: localId,
                _localId: localId,
                dish_id: null,
                dish_name: null,
                quantity: 1,
                unit_price: 0,
                subtotal: 0,
                segue: true,
                _isNew: true,
                notes: null,
                extras: null,
                removals: null,
                status: 'pending',
            };
            this.modifySession.items.splice(idx + 1, 0, segueItem);
            // Insert in pendingAdd right after prevItem
            const paIdx = this.modifySession.pendingAdd.findIndex(i => i._localId === prevItemId);
            this.modifySession.pendingAdd.splice(paIdx + 1, 0, {
                _localId: localId,
                dish_id: null,
                quantity: 1,
                notes: null,
                segue: true,
                custom_price: null,
                extras: null,
                removals: null,
            });
            this.updateModifyReceiptItems();
            return;
        }

        // Saved item: persist to DB
        try {
            const resp = await fetch(`${this.apiBase}/${this.currentTable.table.id}/add-segue`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({ after_item_id: prevItemId }),
            });
            const result = await resp.json();
            if (!result.success) throw new Error(result.message);

            this.modifySession.items.splice(idx + 1, 0, { ...result.data, _isNew: false });
            this.updateModifyReceiptItems();
        } catch (e) {
            this.showNotification("Errore nell'inserimento del segue", 'error');
        }
    }

    /**
     * Remove a segue separator item (no operator auth required).
     * For saved items (id > 0): deletes from DB.
     * For new items (id < 0): removes locally from session and pendingAdd.
     */
    async removeSegueItem(segueItemId) {
        this.modifySession.items = this.modifySession.items.filter(i => i.id !== segueItemId);

        if (segueItemId < 0) {
            this.modifySession.pendingAdd = this.modifySession.pendingAdd.filter(i => i._localId !== segueItemId);
            this.updateModifyReceiptItems();
            return;
        }

        this.updateModifyReceiptItems();

        try {
            const resp = await fetch(`${this.apiBase}/segue-items/${segueItemId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            });
            const result = await resp.json();
            if (!result.success) throw new Error(result.message);
        } catch (e) {
            this.showNotification('Errore nella rimozione del segue', 'error');
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

        // Update receipt items
        this.updateReceiptItems();
    }

    /**
     * Open modify overlay with menu and order details
     */
    openModifyOverlay() {
        if (!this.currentTable) return;

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

        // Hide/show buttons based on banco mode
        const isBanco = !!this.currentTable.table?.is_banco;
        ['btnModifyFreeAmount', 'btnSpostaTavolo', 'btnModifyComunica'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.style.display = isBanco ? 'none' : '';
        });
        // For banco: toggle close buttons based on whether items exist
        this._updateBancoCloseButtons();

        // Show overlay
        const overlay = document.getElementById('modifyOrderOverlay');
        if (overlay) {
            overlay.style.display = 'block';
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

        // When session is active, render from session items
        const items = this.modifySession.active
            ? this.modifySession.items
            : (this.currentTable?.order?.items || []);

        if (!items || items.length === 0) {
            itemsContainer.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Nessun ordine</p>
                </div>
            `;
            totalElement.textContent = '€0.00';
            return;
        }

        const nonSegueItems = items.filter(i => !(i.segue && !i.dish_id));
        let itemsHtml = '';
        items.forEach((item, index) => {
            const isSegueItem = item.segue && !item.dish_id;

            if (isSegueItem) {
                // This item IS the segue separator
                itemsHtml += `
                    <div style="display:flex;align-items:center;gap:6px;margin:2px 0;user-select:none;">
                        <div style="flex:1;height:2px;background:#dc3545;"></div>
                        <span style="font-size:1rem;font-weight:800;color:#dc3545;letter-spacing:1px;white-space:nowrap;">★ SEGUE ★</span>
                        <div style="flex:1;height:2px;background:#dc3545;"></div>
                        <button onclick="tableOrdersManager.removeSegueItem(${item.id})"
                            style="background:none;border:none;color:#dc3545;cursor:pointer;font-size:0.9rem;padding:0 2px;line-height:1;" title="Rimuovi segue">×</button>
                    </div>`;
                return;
            }

            // Show "+ segue" between any two consecutive non-segue items (saved or new)
            const prevItem = index > 0 ? items[index - 1] : null;
            const prevIsRegularDish = prevItem && !(prevItem.segue && !prevItem.dish_id);
            if (prevIsRegularDish) {
                itemsHtml += `
                    <div onclick="tableOrdersManager.addSegueAfter(${prevItem.id})"
                        style="display:flex;align-items:center;gap:6px;margin:2px 0;cursor:pointer;opacity:0.35;user-select:none;"
                        title="Inserisci segue">
                        <div style="flex:1;height:1px;background:#6c757d;"></div>
                        <span style="font-size:0.8rem;color:#565e65;white-space:nowrap;">+ segue</span>
                        <div style="flex:1;height:1px;background:#6c757d;"></div>
                    </div>`;
            }

            itemsHtml += `
            <div class="receipt-item" data-item-id="${item.id}">
                <div style="font-size:13px;font-weight:600;line-height:1.3; color:#3d3d3d;">
                    ${item.quantity} × ${item.dish_name}
                    ${item.notes ? `<br /><div class="receipt-item-notes"> ${item.notes}</div>` : ''}
                    ${item.extras && Object.keys(item.extras).length > 0 ? `
                        <div class="receipt-item-extras">
                            ${Object.entries(item.extras).map(([name, price]) =>
                    `<span><i class="fas fa-plus-circle me-1"></i>${name} (+€${parseFloat(price).toFixed(2)})</span>`
                     ).join(' ')}
                        </div>
                    ` : ''}
                    ${item.removals && item.removals.length > 0 ? `
                        <div class="receipt-item-removals">
                            ${item.removals.map(removal => `<span><i class="fas fa-minus-circle me-1"></i>${removal}</span>`).join(' ')}
                        </div>
                        ` : ''}
                </div>
                <div class="receipt-item-actions">
                    <div style="display:flex;gap:4px;">
                        <button class="btn-quick-add" onclick="tableOrdersManager.openProductModal({id:${item.dish_id}, name:'${(item.dish_name || '').replace(/'/g, "\\'")}', price:${item.unit_price}})" title="Aggiungi ancora"><i class="fas fa-plus"></i></button>
                        <button class="btn-edit-item" onclick="tableOrdersManager.openEditItemModal(${item.id})" title="Modifica piatto"><i class="fas fa-pen"></i></button>
                        ${nonSegueItems.length > 1 ? `<button class="btn-remove-item" onclick="tableOrdersManager.removeItem(${item.id})" title="Rimuovi piatto"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                    <span class="receipt-item-price">€${parseFloat(item.subtotal).toFixed(2)}</span>
                </div>
            </div>`;
        });

        // Cover charge and total: read from session or order
        const order = this.modifySession.active ? this.currentTable?.order : this.currentTable?.order;

        // Add cover charge row (editable)
        const paidCoversTotal = this.modifySession.paidCoversTotal || 0;
        const coverChargeTotal = parseFloat(order?.cover_charge_total || 0);
        const remainingCoverTotal = Math.max(0, coverChargeTotal - paidCoversTotal);
        const coverPerPerson = parseFloat(order?.cover_charge_per_person || 0);
        const remainingCovers = coverPerPerson > 0 ? Math.round(remainingCoverTotal / coverPerPerson) : 0;

        if (order && order.has_cover_charge && coverChargeTotal > 0) {
            const coverLabel = paidCoversTotal > 0
                ? `${remainingCovers} x €${coverPerPerson.toFixed(2)} = <strong>€${remainingCoverTotal.toFixed(2)}</strong> <small style="color:#28a745;">(${order.covers - remainingCovers} già pagati)</small>`
                : `${order.covers} x €${coverPerPerson.toFixed(2)} = <strong>€${coverChargeTotal.toFixed(2)}</strong>`;
            itemsHtml += `
                <div class="receipt-item receipt-item-cover" style="background: #f8f9fa; border-left: 3px solid #17a2b8; padding-left: 15px">
                    <div class="receipt-item-header" style="display:flex;justify-content:space-between;align-items:center;">
                        <strong><i class="fas fa-utensils me-2"></i>Coperto</strong>
                        <span>
                            <span id="coversDisplay" style="cursor:pointer;" onclick="tableOrdersManager.editCovers()" title="Modifica coperti">
                                ${coverLabel}
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
        } else if (order && order.covers === 0) {
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

        // Pending preconto splits summary
        const pendingSplits = this.modifySession.pendingSplits ?? [];
        const paidSplitsTotal = this.modifySession.paidSplitsTotal || 0;
        if (pendingSplits.length > 0 || paidSplitsTotal > 0) {
            itemsHtml += `<div style="margin-top:8px;border-top:2px solid #fd7e14;padding-top:6px;">`;
            itemsHtml += `<div style="font-size:0.7rem;font-weight:700;color:#fd7e14;letter-spacing:1px;margin-bottom:4px;text-transform:uppercase;"><i class="fas fa-receipt me-1"></i>Preconti emessi</div>`;
            if (paidSplitsTotal > 0) {
                itemsHtml += `<div style="display:flex;justify-content:space-between;align-items:center;font-size:0.8rem;color:#28a745;margin-bottom:2px;">
                    <span><i class="fas fa-check-circle me-1"></i>Già pagati con preconto</span>
                    <span>€${paidSplitsTotal.toFixed(2)}</span>
                </div>`;
            }
            pendingSplits.forEach(s => {
                itemsHtml += `<div style="display:flex;justify-content:space-between;align-items:center;font-size:0.8rem;color:#6c757d;margin-bottom:2px;">
                    <span><i class="fas fa-clock me-1"></i>${s.label || 'Preconto'}</span>
                    <span style="background:#fd7e14;color:white;padding:1px 6px;border-radius:3px;font-size:0.75rem;">€${parseFloat(s.total).toFixed(2)} DA PAGARE</span>
                </div>`;
            });
            itemsHtml += `</div>`;
        }

        itemsContainer.innerHTML = itemsHtml;

        // Calculate raw total
        let rawTotal;
        if (this.modifySession.active) {
            const itemsTotal = items.reduce((sum, item) => sum + parseFloat(item.subtotal || 0), 0);
            // Use remainingCoverTotal (already computed above) — excludes covers paid via preconto splits
            const coverTotal = (order && order.has_cover_charge) ? remainingCoverTotal : 0;
            rawTotal = itemsTotal + coverTotal;
        } else {
            rawTotal = parseFloat(order ? order.total_amount : 0);
        }

        // Apply authorized discount (set only after auth)
        const authorizedDiscount = this._authorizedDiscount;
        let finalTotal = rawTotal;
        if (authorizedDiscount && authorizedDiscount.value > 0) {
            finalTotal = authorizedDiscount.type === 'percent'
                ? Math.max(0, rawTotal - Math.round(rawTotal * Math.min(authorizedDiscount.value, 100) / 100 * 100) / 100)
                : Math.max(0, rawTotal - Math.min(authorizedDiscount.value, rawTotal));
        }

        // Note: paidSplitsTotal is NOT subtracted here because _applyPaidItemsToSession
        // already removes/reduces paid items from the session, so rawTotal already reflects
        // the remaining amount. Subtracting again would double-count and show €0.00.

        // Show/hide original total (strikethrough)
        const originalAmountEl = document.getElementById('modifyOriginalAmount');
        if (originalAmountEl) {
            if (authorizedDiscount && authorizedDiscount.value > 0 && finalTotal !== rawTotal) {
                originalAmountEl.textContent = `€${rawTotal.toFixed(2)}`;
                originalAmountEl.style.display = 'block';
            } else {
                originalAmountEl.style.display = 'none';
            }
        }

        totalElement.textContent = `€${finalTotal.toFixed(2)}`;

        this._updateBancoCloseButtons();
    }

    /**
     * Show/hide close buttons for banco based on whether items exist.
     * Empty banco → show close buttons. Banco with items → hide them.
     */
    _updateBancoCloseButtons() {
        const isBanco = !!this.currentTable?.table?.is_banco;
        const closeBtn = document.getElementById('closeModifyBtn');
        const closeNoPrintBtn = document.getElementById('closeModifyNoPrintBtn');

        if (!isBanco) return; // non-banco tables are handled elsewhere

        const hasItems = (this.modifySession.items || []).some(i => !(i.segue && !i.dish_id));
        if (closeBtn) closeBtn.style.display = hasItems ? 'none' : '';
        if (closeNoPrintBtn) closeNoPrintBtn.style.display = hasItems ? 'none' : '';
    }

    /**
     * Request operator auth before applying discount
     */
    async requestDiscountAuth() {
        const discountInput = document.getElementById('modifyDiscountInput');
        const discountVal = parseFloat(discountInput?.value || 0);
        if (!discountVal || discountVal <= 0) {
            this.showNotification('Inserisci un valore di sconto', 'warning');
            return;
        }
        const isPercent = document.getElementById('discountTypePct')?.classList.contains('active');
        const discountType = isPercent ? 'percent' : 'value';

        const order = this.currentTable?.order;

        // Calcola il totale reale con la stessa logica usata per applicare lo sconto
        let rawTotal;
        if (this.modifySession.active) {
            const items = this.modifySession.items;
            const itemsTotal = items.reduce((sum, item) => sum + parseFloat(item.subtotal || 0), 0);
            const coverTotal = (order && order.has_cover_charge) ? parseFloat(order.cover_charge_total || 0) : 0;
            rawTotal = itemsTotal + coverTotal;
        } else {
            rawTotal = parseFloat(order ? order.total_amount : 0);
        }

        // Validazione: lo sconto non può superare il totale del tavolo
        if (discountType === 'percent' && discountVal > 100) {
            this.showNotification('Lo sconto percentuale non può superare il 100%', 'error');
            return;
        }
        if (discountType === 'value' && discountVal > rawTotal) {
            this.showNotification(`Lo sconto non può superare il totale del tavolo (€${rawTotal.toFixed(2)})`, 'error');
            return;
        }

        try {
            const auth = await operatorAuthManager.requestAuth();

            // rawTotal già calcolato sopra
            const finalTotal = discountType === 'percent'
                ? Math.max(0, rawTotal - Math.round(rawTotal * Math.min(discountVal, 100) / 100 * 100) / 100)
                : Math.max(0, rawTotal - Math.min(discountVal, rawTotal));

            // Save log via API
            const tableId = this.currentTable.table.id;
            await fetch(`${this.apiBase}/${tableId}/apply-discount`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token,
                },
                body: JSON.stringify({
                    discount_type:   discountType,
                    discount_amount: discountVal,
                    original_total:  rawTotal,
                    final_total:     finalTotal,
                }),
            });

            // Lock discount UI and apply
            this._authorizedDiscount = { type: discountType, value: discountVal };
            if (discountInput) discountInput.disabled = true;
            const btnApply = document.getElementById('btnApplyDiscount');
            const btnReset = document.getElementById('btnResetDiscount');
            if (btnApply) btnApply.style.display = 'none';
            if (btnReset) btnReset.style.display = 'inline-flex';

            // Persist discount fields in local order state
            if (this.currentTable?.order) {
                this.currentTable.order.discount_type   = discountType;
                this.currentTable.order.discount_amount = discountVal;
                this.currentTable.order.discount_value  = rawTotal - finalTotal;
                this.currentTable.order.discounted_total = finalTotal;
            }

            this.updateModifyReceiptItems();
            this.showNotification(`Sconto autorizzato da ${auth.user.name}`, 'success');
        } catch {
            // auth cancelled or error — do nothing
        }
    }

    /**
     * Reset the authorized discount
     */
    async resetDiscount() {
        // Call API to clear discount from DB
        const tableId = this.currentTable?.table?.id;
        const token = this.modifySession?.token;
        if (tableId && token) {
            try {
                await fetch(`${this.apiBase}/${tableId}/reset-discount`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'X-Operator-Token': token,
                    },
                });
            } catch (e) {
                console.warn('reset-discount API error:', e);
            }
        }

        this._authorizedDiscount = null;
        if (this.currentTable?.order) {
            this.currentTable.order.discount_type   = null;
            this.currentTable.order.discount_amount = null;
            this.currentTable.order.discount_value  = null;
            this.currentTable.order.discounted_total = parseFloat(this.currentTable.order.total_amount || 0);
        }
        const discountInput = document.getElementById('modifyDiscountInput');
        if (discountInput) { discountInput.value = ''; discountInput.disabled = false; }
        const btnApply = document.getElementById('btnApplyDiscount');
        const btnReset = document.getElementById('btnResetDiscount');
        if (btnApply) btnApply.style.display = 'inline-flex';
        if (btnReset) btnReset.style.display = 'none';
        this.updateModifyReceiptItems();
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
                <div class="receipt-item-actions">
                    <button class="btn-edit-item" onclick="tableOrdersManager.openEditItemModal(${item.id})" title="Modifica note/extra">
                        <i class="fas fa-pen"></i>
                    </button>
                    ${items.length > 1 ? `<button class="btn-remove-item" onclick="tableOrdersManager.removeItem(${item.id})">
                        <i class="fas fa-trash"></i>
                    </button>` : ''}
                </div>
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

        if (quantityElement) quantityElement.value = 1;
        if (notesElement) notesElement.value = '';

        // Populate extras and removals
        const extrasContainer = this.getElement('extrasContainer');
        const removalsContainer = this.getElement('removalsContainer');

        if (extrasContainer) {
            extrasContainer.innerHTML = this.menuOptions.extras.length
                ? this.menuOptions.extras.map(e => `
                    <label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;padding:5px 10px;border:1px solid #dee2e6;border-radius:20px;background:#f8f9fa;font-size:0.82rem;color:#000;white-space:nowrap;">
                        <input type="checkbox" class="extra-checkbox" data-name="${e.label}" value="${e.price}" style="accent-color:#dc3545;">
                        <span>${e.label}</span><span style="color:#dc3545;font-weight:700;">+€${parseFloat(e.price).toFixed(2)}</span>
                    </label>`).join('')
                : '<small style="color:#6c757d;">Nessun supplemento disponibile</small>';
        }
        if (removalsContainer) {
            removalsContainer.innerHTML = this.menuOptions.removals.length
                ? this.menuOptions.removals.map(r => `
                    <label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;padding:5px 10px;border:1px solid #dee2e6;border-radius:20px;background:#f8f9fa;font-size:0.82rem;color:#000;white-space:nowrap;">
                        <input type="checkbox" class="removal-checkbox" data-name="${r.label}" value="${r.label}" style="accent-color:#dc3545;">
                        <span>${r.label}</span>
                    </label>`).join('')
                : '<small style="color:#6c757d;">Nessuna rimozione disponibile</small>';
        }

        // Update total
        this.updateModalTotal();

        // Hide "Cambia piatto" button in add mode
        const changeDishBtn = document.getElementById('changeDishBtn');
        if (changeDishBtn) changeDishBtn.style.display = 'none';

        // Restore add button label
        const addBtn = document.getElementById('addProductBtn');
        if (addBtn) addBtn.innerHTML = '<i class="fas fa-plus me-2"></i> AGGIUNGI';

        // Show modal
        const modal = this.getElement('productModal');
        if (modal) modal.style.display = this.isMobile ? 'flex' : 'block';
    }

    /**
     * Open product modal in "edit existing item" mode (notes/extras/removals)
     */
    openEditItemModal(itemId) {
        const item = this.modifySession.items.find(i => i.id === itemId);
        if (!item) return;

        this._editingItemId = itemId;

        // Snapshot original values for change detection (only on first open)
        if (!item._origQuantity) item._origQuantity = item.quantity;
        if (!item._origPrice)    item._origPrice    = item.unit_price;
        if (item._origNotes    === undefined) item._origNotes    = item.notes || null;
        if (item._origExtras   === undefined) item._origExtras   = JSON.parse(JSON.stringify(item.extras || {}));
        if (item._origRemovals === undefined) item._origRemovals = [...(item.removals || [])];

        // Set currentProduct so updateModalTotal works
        this.currentProduct = { id: item.dish_id, name: item.dish_name, price: item.unit_price };

        // Populate header
        const nameElement = this.getElement('modalProductName');
        const priceDisplayElement = this.getElement('modalProductPriceDisplay');
        const customPriceElement = this.getElement('productCustomPrice');
        if (nameElement) nameElement.textContent = item.dish_name || '';
        if (priceDisplayElement) priceDisplayElement.textContent = `€${parseFloat(item.unit_price).toFixed(2)}`;
        if (customPriceElement) { customPriceElement.value = parseFloat(item.unit_price).toFixed(2); customPriceElement.readOnly = false; }

        // Quantity (editable in edit mode)
        const quantityElement = this.getElement('productQuantity');
        if (quantityElement) { quantityElement.value = item.quantity; quantityElement.readOnly = false; }

        // Notes
        const notesElement = this.getElement('productNotes');
        if (notesElement) notesElement.value = item.notes || '';

        // Extras — pre-check existing
        const extrasContainer = this.getElement('extrasContainer');
        if (extrasContainer) {
            extrasContainer.innerHTML = this.menuOptions.extras.length
                ? this.menuOptions.extras.map(e => {
                    const checked = item.extras && item.extras[e.label] !== undefined ? 'checked' : '';
                    return `<label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;padding:5px 10px;border:1px solid #dee2e6;border-radius:20px;background:#f8f9fa;font-size:0.82rem;color:#000;white-space:nowrap;">
                        <input type="checkbox" class="extra-checkbox" data-name="${e.label}" value="${e.price}" style="accent-color:#dc3545;" ${checked}>
                        <span>${e.label}</span><span style="color:#dc3545;font-weight:700;">+€${parseFloat(e.price).toFixed(2)}</span>
                    </label>`;
                }).join('')
                : '<small style="color:#6c757d;">Nessun supplemento disponibile</small>';
        }

        // Removals — pre-check existing
        const removalsContainer = this.getElement('removalsContainer');
        if (removalsContainer) {
            removalsContainer.innerHTML = this.menuOptions.removals.length
                ? this.menuOptions.removals.map(r => {
                    const checked = item.removals && item.removals.includes(r.label) ? 'checked' : '';
                    return `<label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;padding:5px 10px;border:1px solid #dee2e6;border-radius:20px;background:#f8f9fa;font-size:0.82rem;color:#000;white-space:nowrap;">
                        <input type="checkbox" class="removal-checkbox" data-name="${r.label}" value="${r.label}" style="accent-color:#dc3545;" ${checked}>
                        <span>${r.label}</span>
                    </label>`;
                }).join('')
                : '<small style="color:#6c757d;">Nessuna rimozione disponibile</small>';
        }

        // Show "Cambia piatto" button in edit mode (only on desktop overlay)
        const changeDishBtn = document.getElementById('changeDishBtn');
        if (changeDishBtn && !this.isMobile) changeDishBtn.style.display = '';

        // Change add button label to "CONFERMA" in edit mode
        const addBtn = document.getElementById('addProductBtn');
        if (addBtn) addBtn.innerHTML = '<i class="fas fa-check me-2"></i> CONFERMA';

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
        this._editingItemId = null;
        this._pendingNewDish = null;
        // Hide "Cambia piatto" button
        const changeDishBtn = document.getElementById('changeDishBtn');
        if (changeDishBtn) changeDishBtn.style.display = 'none';
        // Restore add button label
        const addBtn = document.getElementById('addProductBtn');
        if (addBtn) addBtn.innerHTML = '<i class="fas fa-plus me-2"></i> AGGIUNGI';
        // Reset readonly state
        const quantityElement = this.getElement('productQuantity');
        const customPriceElement = this.getElement('productCustomPrice');
        if (quantityElement) quantityElement.readOnly = false;
        if (customPriceElement) customPriceElement.readOnly = false;
        // On mobile, reopen the manage modal
        if (this.isMobile) {
            const manage = document.getElementById('manageModalMobile');
            if (manage) {
                manage.style.display = '';
                $('#manageModalMobile').addClass('active').show();
            }
        }
    }

    /**
     * Open the dish-change selection view inside the product modal
     */
    async openDishChangeView() {
        // Load all active dishes (cached after first call)
        if (!this._dishCache) {
            try {
                const resp = await fetch('/api/dishes');
                const data = await resp.json();
                this._dishCache = data.data || [];
            } catch (e) {
                this.showNotification('Errore nel caricamento dei piatti', 'error');
                return;
            }
        }

        // Determine current dish's category for default filter
        const currentItem = this.modifySession.items.find(i => i.id === this._editingItemId);
        const currentDish = this._dishCache.find(d => d.id === currentItem?.dish_id);
        this._dishChangeActiveCategoryId = currentDish?.category_id ?? null;
        this._dishChangeSelectedDish = null;

        // Toggle views
        const editView = document.getElementById('productModal-editView');
        const dishView = document.getElementById('productModal-dishView');
        const mainFooter = document.getElementById('productModal-mainFooter');
        const dishFooter = document.getElementById('dishChangeFooter');
        if (editView)   editView.style.display   = 'none';
        if (dishView)   dishView.style.display    = 'flex';
        if (mainFooter) mainFooter.style.display  = 'none';
        if (dishFooter) dishFooter.style.display  = 'flex';

        // Clear search and render
        const searchInput = document.getElementById('dishChangeSearch');
        if (searchInput) searchInput.value = '';
        this._renderDishCategories();
        this._renderDishList('');
    }

    /**
     * Render category pills in the dish selection view
     */
    _renderDishCategories() {
        const container = document.getElementById('dishChangeCategoryFilter');
        if (!container || !this._dishCache) return;

        const categories = [];
        const seen = new Set();
        for (const dish of this._dishCache) {
            if (!seen.has(dish.category_id)) {
                seen.add(dish.category_id);
                categories.push({ id: dish.category_id, name: dish.category_name });
            }
        }
        categories.sort((a, b) => {
            if (a.id === this._dishChangeActiveCategoryId) return -1;
            if (b.id === this._dishChangeActiveCategoryId) return 1;
            return a.name.localeCompare(b.name);
        });

        container.innerHTML = categories.map(cat => {
            const active = cat.id === this._dishChangeActiveCategoryId;
            return `<button onclick="tableOrdersManager._selectDishCategory(${cat.id})"
                style="padding:4px 10px; font-size:0.78rem; font-weight:600; border:2px solid ${active ? '#fd7e14' : '#dee2e6'};
                       background:${active ? '#fd7e14' : '#f8f9fa'}; color:${active ? 'white' : '#333'};
                       border-radius:20px; cursor:pointer; white-space:nowrap; transition:all 0.1s;">
                ${cat.name}
            </button>`;
        }).join('');
    }

    _selectDishCategory(categoryId) {
        this._dishChangeActiveCategoryId = categoryId;
        this._renderDishCategories();
        const searchInput = document.getElementById('dishChangeSearch');
        if (searchInput) searchInput.value = '';
        this._renderDishList('');
    }

    _filterDishList(searchTerm) {
        this._renderDishList(searchTerm);
    }

    _renderDishList(searchTerm) {
        const container = document.getElementById('dishChangeList');
        if (!container || !this._dishCache) return;

        let dishes = this._dishCache;
        if (searchTerm && searchTerm.trim()) {
            const term = searchTerm.toLowerCase();
            dishes = dishes.filter(d => d.name.toLowerCase().includes(term));
        } else if (this._dishChangeActiveCategoryId) {
            dishes = dishes.filter(d => d.category_id === this._dishChangeActiveCategoryId);
        }

        if (!dishes.length) {
            container.innerHTML = '<div style="color:#6c757d; font-size:0.85rem; padding:16px; text-align:center; grid-column:1/-1;">Nessun piatto trovato</div>';
            return;
        }

        container.innerHTML = dishes.map(dish => {
            const selected = this._dishChangeSelectedDish?.id === dish.id;
            return `<button onclick="tableOrdersManager._selectDish(${dish.id})"
                style="padding:8px 10px; text-align:left; border:2px solid ${selected ? '#fd7e14' : '#dee2e6'};
                       background:${selected ? '#fff3e6' : '#f8f9fa'}; cursor:pointer; border-radius:6px;
                       font-size:0.82rem; transition:all 0.1s; display:flex; flex-direction:column; gap:2px;">
                <span style="font-weight:${selected ? '700' : '500'}; color:#000;">${dish.name}</span>
                <span style="color:#dc3545; font-weight:700; font-size:0.8rem;">€${parseFloat(dish.price).toFixed(2)}</span>
                ${searchTerm ? `<span style="font-size:0.72rem; color:#6c757d;">${dish.category_name}</span>` : ''}
            </button>`;
        }).join('');
    }

    _selectDish(dishId) {
        this._dishChangeSelectedDish = this._dishCache.find(d => d.id === dishId) ?? null;
        const searchInput = document.getElementById('dishChangeSearch');
        this._renderDishList(searchInput?.value || '');
    }

    /**
     * Confirm the dish selected in the selection view → update modal header, go back to edit view
     */
    confirmDishChange() {
        if (!this._dishChangeSelectedDish) {
            this.showNotification('Seleziona prima un piatto', 'error');
            return;
        }
        this._pendingNewDish = this._dishChangeSelectedDish;

        // Update modal header to show new dish
        const nameEl = document.getElementById('modalProductName');
        const priceDisplayEl = document.getElementById('modalProductPriceDisplay');
        const priceInputEl = document.getElementById('productCustomPrice');
        if (nameEl) nameEl.textContent = this._pendingNewDish.name;
        if (priceDisplayEl) priceDisplayEl.textContent = `€${parseFloat(this._pendingNewDish.price).toFixed(2)}`;
        if (priceInputEl) priceInputEl.value = parseFloat(this._pendingNewDish.price).toFixed(2);
        this.currentProduct = { id: this._pendingNewDish.id, name: this._pendingNewDish.name, price: this._pendingNewDish.price };

        this.closeDishChangeView();
    }

    /**
     * Close dish selection view and return to edit view
     */
    closeDishChangeView() {
        const editView = document.getElementById('productModal-editView');
        const dishView = document.getElementById('productModal-dishView');
        const mainFooter = document.getElementById('productModal-mainFooter');
        const dishFooter = document.getElementById('dishChangeFooter');
        if (editView)   editView.style.display   = '';
        if (dishView)   dishView.style.display    = 'none';
        if (mainFooter) mainFooter.style.display  = '';
        if (dishFooter) dishFooter.style.display  = 'none';
        this._dishChangeSelectedDish = null;
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
     * Add product to session.
     * In edit mode: requests auth, submits immediately to backend, prints.
     * In add mode: adds locally to session (sent at "Chiudi e Invia").
     */
    async addProductToSession() {
        if (!this.currentTable) return;
        console.log(this.modifySession.permissions)
        // Permission: only operators with take_orders can add/edit items
        if (!(this.modifySession.permissions ?? []).includes('take_orders')) {
            this.showNotification('Non hai il permesso di prendere comande', 'error');
            return;
        }

        // Edit mode: collect changes, submit immediately with auth
        if (this._editingItemId) {
            const itemId = this._editingItemId;

            const quantityElement    = this.getElement('productQuantity');
            const customPriceElement = this.getElement('productCustomPrice');
            const notesElement       = this.getElement('productNotes');
            const extrasContainer    = this.getElement('extrasContainer');
            const removalsContainer  = this.getElement('removalsContainer');

            const newQty   = parseInt(quantityElement?.value || 1);
            const newPrice = parseFloat(customPriceElement?.value);
            const notes    = notesElement?.value || null;
            // Preserve existing segue value — segue is managed from the list, not from the modal
            const existingItem = this.modifySession.items.find(i => i.id === itemId);
            const segue = existingItem?.segue || false;
            const extras   = {};
            extrasContainer?.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
                extras[cb.dataset.name] = parseFloat(cb.value);
            });
            const removals = [];
            removalsContainer?.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
                removals.push(cb.dataset.name);
            });

            // Update local session item (optimistic)
            const item = this.modifySession.items.find(i => i.id === itemId);
            if (item) {
                item.notes    = notes;
                item.segue    = segue;
                item.extras   = Object.keys(extras).length > 0 ? extras : {};
                item.removals = removals;
                if (newQty > 0) item.quantity = newQty;
                if (!isNaN(newPrice) && newPrice >= 0) item.unit_price = newPrice;
                const extrasTotal = Object.values(item.extras).reduce((s, v) => s + v, 0);
                item.subtotal = parseFloat(((item.unit_price + extrasTotal) * item.quantity).toFixed(2));
            }

            if (itemId > 0) {
                // If a new dish was selected in the dish-change view, record it
                if (this._pendingNewDish) {
                    const existing = (this.modifySession.pendingDishChange || []).findIndex(c => c.itemId === itemId);
                    const change = { itemId, newDish: this._pendingNewDish };
                    if (existing >= 0) {
                        this.modifySession.pendingDishChange[existing] = change;
                    } else {
                        this.modifySession.pendingDishChange.push(change);
                    }
                    // Update local session item display
                    const sessItem = this.modifySession.items.find(i => i.id === itemId);
                    if (sessItem) {
                        sessItem.dish_name = this._pendingNewDish.name;
                        sessItem.dish_id   = this._pendingNewDish.id;
                    }
                    this._pendingNewDish = null;
                }

                // Detect what actually changed compared to the current session item
                const origItem = this.modifySession.items.find(i => i.id === itemId) || {};
                const origQty   = origItem._origQuantity ?? origItem.quantity;
                const origPrice = origItem._origPrice    ?? origItem.unit_price;
                const origNotes = origItem._origNotes    ?? (origItem.notes || null);
                const origExtras   = JSON.stringify(origItem._origExtras   ?? (origItem.extras   || {}));
                const origRemovals = JSON.stringify([...((origItem._origRemovals ?? origItem.removals) || [])].sort());

                const qtyChanged   = newQty !== origQty;
                const priceChanged = !isNaN(newPrice) && newPrice >= 0 && Math.abs(newPrice - origPrice) > 0.001;
                const detailsChanged =
                    notes !== origNotes ||
                    JSON.stringify(Object.keys(extras).length > 0 ? extras : {}) !== origExtras ||
                    JSON.stringify([...removals].sort()) !== origRemovals;

                const hasDishChange = (this.modifySession.pendingDishChange || []).some(c => c.itemId === itemId);

                // Nothing changed → just close
                if (!qtyChanged && !priceChanged && !detailsChanged && !hasDishChange) {
                    this.closeProductModal();
                    return;
                }

                // Build pendingUpdate for changed fields
                if (!this.modifySession.pendingUpdate[itemId]) this.modifySession.pendingUpdate[itemId] = {};
                if (detailsChanged) {
                    this.modifySession.pendingUpdate[itemId].notes    = notes;
                    this.modifySession.pendingUpdate[itemId].extras   = Object.keys(extras).length > 0 ? extras : null;
                    this.modifySession.pendingUpdate[itemId].removals = removals.length > 0 ? removals : null;
                    this.modifySession.pendingUpdate[itemId]._detailsChanged = true;
                }
                if (qtyChanged)   this.modifySession.pendingUpdate[itemId].quantity = newQty;
                if (priceChanged) {
                    this.modifySession.pendingUpdate[itemId].unit_price = newPrice;
                    this.modifySession.pendingUpdate[itemId].price_motivation = null;
                }

                // Request auth and submit
                let auth;
                try { auth = await operatorAuthManager.requestAuth(); } catch (e) { return; }
                if (!auth) return;

                this.closeProductModal();
                try {
                    await this._submitItemEdit(itemId, auth.token);
                    this.showNotification('Modifiche salvate', 'success');
                } catch (e) {
                    console.error('Error submitting item edit:', e);
                    this.showNotification('Errore nel salvataggio: ' + e.message, 'error');
                }
                this.updateModifyReceiptItems();
            } else {
                // Local new item → update pendingAdd entry (no submit needed now)
                const addEntry = this.modifySession.pendingAdd.find(a => a._localId === itemId);
                if (addEntry) {
                    addEntry.notes    = notes;
                    addEntry.segue    = segue;
                    addEntry.extras   = Object.keys(extras).length > 0 ? extras : {};
                    addEntry.removals = removals;
                    if (newQty > 0) addEntry.quantity = newQty;
                    if (!isNaN(newPrice) && newPrice >= 0) addEntry.custom_price = newPrice;
                }
                this.closeProductModal();
                this.updateModifyReceiptItems();
            }
            return;
        }

        if (!this.currentProduct) return;

        const quantityElement = this.getElement('productQuantity');
        const notesElement = this.getElement('productNotes');
        const customPriceElement = this.getElement('productCustomPrice');
        const extrasContainer = this.getElement('extrasContainer');
        const removalsContainer = this.getElement('removalsContainer');

        let customPrice = customPriceElement ? parseFloat(customPriceElement.value) : null;
        if (isNaN(customPrice)) customPrice = null;

        const unitPrice = customPrice !== null ? customPrice : parseFloat(this.currentProduct.price);
        const quantity = parseInt(quantityElement?.value || 1);
        const notes = notesElement?.value || null;
        const segue = false; // segue is set from the list separator, not from the modal

        const extras = {};
        const removals = [];
        const checkedExtras = extrasContainer?.querySelectorAll('input[type="checkbox"]:checked') || [];
        checkedExtras.forEach(cb => { extras[cb.dataset.name] = parseFloat(cb.value); });
        const checkedRemovals = removalsContainer?.querySelectorAll('input[type="checkbox"]:checked') || [];
        checkedRemovals.forEach(cb => { removals.push(cb.dataset.name); });

        const extrasTotal = Object.values(extras).reduce((s, v) => s + v, 0);
        const subtotal = parseFloat(((unitPrice + extrasTotal) * quantity).toFixed(2));

        const localId = this.modifySession.itemCounter--;

        const sessionItem = {
            id: localId,
            dish_id: this.currentProduct.id,
            dish_name: this.currentProduct.name,
            quantity,
            unit_price: unitPrice,
            subtotal,
            notes,
            extras: Object.keys(extras).length > 0 ? extras : {},
            removals: removals.length > 0 ? removals : [],
            segue,
            _isNew: true,
        };

        this.modifySession.items.push(sessionItem);
        this.modifySession.pendingAdd.push({
            _localId: localId,
            dish_id: this.currentProduct.id,
            quantity,
            notes,
            segue,
            custom_price: customPrice,
            extras: Object.keys(extras).length > 0 ? extras : null,
            removals: removals.length > 0 ? removals : null,
        });

        const productName = this.currentProduct.name;
        this.closeProductModal();
        this.updateModifyReceiptItems();
        this.showNotification(`${productName} aggiunto`);
    }

    /**
     * Add product to table (legacy — kept for non-session contexts)
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
            segue: false, // segue is set from the list separator, not from the modal
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
        if (this.modifySession.active) {
            const reason = await this._showRemoveReasonModal();
            if (!reason) return; // user cancelled

            let auth;
            try {
                auth = await operatorAuthManager.requestAuth();
                if (!auth) return;
            } catch {
                return;
            }

            const item = this.modifySession.items.find(i => i.id === itemId);
            if (!item) return;

            if (item._isNew) {
                this.modifySession.items = this.modifySession.items.filter(i => i.id !== itemId);
                this.modifySession.pendingAdd = this.modifySession.pendingAdd.filter(i => i._localId !== itemId);
            } else {
                this.modifySession.pendingRemove.push({ id: itemId, reason, authToken: auth.token });
                this.modifySession.items = this.modifySession.items.filter(i => i.id !== itemId);
            }

            this.updateModifyReceiptItems();
            return;
        }

        if (!confirm('Vuoi rimuovere questo prodotto?')) return;

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
     * Show remove reason modal and return chosen reason (or null if cancelled)
     */
    _showRemoveReasonModal() {
        return new Promise(resolve => {
            const modal = document.getElementById('removeReasonModal');
            if (!modal) { resolve('Rimosso'); return; }

            modal.style.display = 'flex';

            const cleanup = (reason) => {
                modal.style.display = 'none';
                // Remove all event listeners by replacing nodes
                document.querySelectorAll('.remove-reason-btn').forEach(btn => {
                    btn.replaceWith(btn.cloneNode(true));
                });
                const cancelBtn = document.getElementById('cancelRemoveReason');
                if (cancelBtn) cancelBtn.replaceWith(cancelBtn.cloneNode(true));
                resolve(reason);
            };

            document.querySelectorAll('.remove-reason-btn').forEach(btn => {
                btn.addEventListener('click', () => cleanup(btn.dataset.reason));
            });

            const cancelBtn = document.getElementById('cancelRemoveReason');
            if (cancelBtn) cancelBtn.addEventListener('click', () => cleanup(null));
        });
    }

    /**
     * Increase item quantity
     */
    async increaseQuantity(itemId, event) {
        if (event) event.stopPropagation();

        if (this.modifySession.active) {
            const item = this.modifySession.items.find(i => i.id === itemId);
            if (!item) return;
            item.quantity += 1;
            item.subtotal = parseFloat(((parseFloat(item.unit_price) + this._itemExtrasTotal(item)) * item.quantity).toFixed(2));
            this._updateSessionPendingQuantity(itemId, item);
            this.updateModifyReceiptItems();
            return;
        }

        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) { return; }

        try {
            const item = this.currentTable.order.items.find(i => i.id === itemId);
            if (!item) { this.showNotification('Prodotto non trovato', 'error'); return; }
            await this.updateItemQuantity(itemId, item.quantity + 1, auth.token);
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

        if (this.modifySession.active) {
            const item = this.modifySession.items.find(i => i.id === itemId);
            if (!item) return;
            if (item.quantity <= 1) {
                await this.removeItem(itemId);
                return;
            }
            item.quantity -= 1;
            item.subtotal = parseFloat(((parseFloat(item.unit_price) + this._itemExtrasTotal(item)) * item.quantity).toFixed(2));
            this._updateSessionPendingQuantity(itemId, item);
            this.updateModifyReceiptItems();
            return;
        }

        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) { return; }

        try {
            const item = this.currentTable.order.items.find(i => i.id === itemId);
            if (!item) { this.showNotification('Prodotto non trovato', 'error'); return; }
            if (item.quantity - 1 <= 0) {
                if (confirm('Rimuovere questo prodotto dall\'ordine?')) {
                    await this.removeItem(itemId);
                }
                return;
            }
            await this.updateItemQuantity(itemId, item.quantity - 1, auth.token);
        } catch (error) {
            console.error('Error decreasing quantity:', error);
            this.showNotification('Errore nell\'aggiornamento della quantità', 'error');
        }
    }

    /** Helper: calculate extras total for a session item */
    _itemExtrasTotal(item) {
        if (!item.extras || typeof item.extras !== 'object') return 0;
        return Object.values(item.extras).reduce((s, v) => s + parseFloat(v), 0);
    }

    /** Helper: update pendingAdd or pendingUpdate for quantity changes */
    _updateSessionPendingQuantity(itemId, item) {
        if (item._isNew) {
            const paIdx = this.modifySession.pendingAdd.findIndex(i => i._localId === itemId);
            if (paIdx >= 0) this.modifySession.pendingAdd[paIdx].quantity = item.quantity;
        } else {
            if (!this.modifySession.pendingUpdate[itemId]) this.modifySession.pendingUpdate[itemId] = {};
            this.modifySession.pendingUpdate[itemId].quantity = item.quantity;
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

        if (this.modifySession.active) {
            const motivation = prompt('Motivo della modifica prezzo:');
            if (!motivation) {
                this.cancelEditPrice(itemId);
                return;
            }

            const item = this.modifySession.items.find(i => i.id === itemId);
            if (!item) return;

            item.unit_price = newPrice;
            item.subtotal = parseFloat(((newPrice + this._itemExtrasTotal(item)) * item.quantity).toFixed(2));

            if (item._isNew) {
                const paIdx = this.modifySession.pendingAdd.findIndex(i => i._localId === itemId);
                if (paIdx >= 0) this.modifySession.pendingAdd[paIdx].custom_price = newPrice;
            } else {
                if (!this.modifySession.pendingUpdate[itemId]) this.modifySession.pendingUpdate[itemId] = {};
                this.modifySession.pendingUpdate[itemId].unit_price = newPrice;
                this.modifySession.pendingUpdate[itemId].price_motivation = motivation;
            }

            this.cancelEditPrice(itemId);
            this.updateModifyReceiptItems();
            return;
        }

        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) { return; }

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
                this.modifySession.active = false;
                await this.loadTables();

                // Hide receipt overlay
                const receiptOverlay = this.getElement('receiptOverlay');
                if (receiptOverlay) receiptOverlay.style.display = 'none';

                // Hide modify overlay
                this._hideModifyOverlay();

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
     * Open the autoconsumo modal (admin only)
     */
    async openAutoconsumoModal() {
        if (!this.currentTable) return;

        // Admin-only check
        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
            console.log(auth)
            if (auth.user.role !== 'admin') {
                alert('Questa operazione richiede utenza admin');
                return;
            }
        } catch (error) {
            console.log('Authentication cancelled');
            return;
        }

        // Store auth for later use
        this._autoconsumoAuth = auth;
        this._autoconsumoAssignments = {};
        this._autoconsumoOperatorColors = {};

        const tableNum = this.currentTable.table?.table_number ?? this.currentTable.table_number ?? '-';
        document.getElementById('autoconsumoTableNumber').textContent = tableNum;

        const alreadyAutoconsumo = this.currentTable.order?.autoconsumo === true;

        if (alreadyAutoconsumo) {
            // Pre-populate assignments from existing item data
            const sessionItems = this.modifySession.items.filter(i => !i._isNew || i.id > 0);
            sessionItems.forEach(item => {
                if (item.autoconsumo_user_id) {
                    this._autoconsumoAssignments[item.id] = {
                        userId: item.autoconsumo_user_id,
                        userName: item.autoconsumo_user_name ?? `Utente #${item.autoconsumo_user_id}`,
                        color: null, // will be filled when operators are loaded
                    };
                }
            });

            // Skip mode selection and go directly to the partial view
            document.getElementById('autoconsumoModeSelect').style.display = 'none';
            this._autoconsumoInProgress = true;
            document.getElementById('autoconsumoModal').style.display = 'flex';
            this._showAutoconsumoPartialView();
        } else {
            // Show mode selection
            document.getElementById('autoconsumoModeSelect').style.display = 'block';
            document.getElementById('autoconsumoPartialView').style.display = 'none';
            document.getElementById('autoconsumoFooter').style.display = 'none';
            this._autoconsumoInProgress = true;
            document.getElementById('autoconsumoModal').style.display = 'flex';
        }
    }

    /**
     * Show the partial assignment UI
     */
    async _showAutoconsumoPartialView() {
        document.getElementById('autoconsumoModeSelect').style.display = 'none';
        const partialView = document.getElementById('autoconsumoPartialView');
        partialView.style.display = 'flex';
        document.getElementById('autoconsumoFooter').style.display = 'flex';

        // Fetch operators
        let operators = [];
        try {
            const res = await fetch('/api/operators');
            const data = await res.json();
            operators = Array.isArray(data.data) ? data.data : [];
        } catch (e) {
            console.error('Error fetching operators:', e);
        }

        // Assign a color to each operator
        const palette = ['#dc3545','#28a745','#17a2b8','#fd7e14','#6f42c1','#e83e8c','#20c997','#ffc107'];
        operators.forEach((op, i) => {
            this._autoconsumoOperatorColors[op.id] = palette[i % palette.length];
        });

        // Fill in colors for pre-existing assignments (loaded before operators were fetched)
        Object.keys(this._autoconsumoAssignments).forEach(itemId => {
            const a = this._autoconsumoAssignments[itemId];
            if (!a.color && this._autoconsumoOperatorColors[a.userId]) {
                a.color = this._autoconsumoOperatorColors[a.userId];
            } else if (!a.color) {
                a.color = '#6c757d'; // fallback if user not in operators list
            }
        });

        // Use session items (have correct dish_name field) — skip pending unsaved adds
        const items = this.modifySession.items.filter(i => !i._isNew || i.id > 0);
        const itemsList = document.getElementById('autoconsumoItemsList');
        itemsList.innerHTML = items.length === 0
            ? '<div style="color:#6c757d; text-align:center; padding:20px;">Nessun piatto nell\'ordine</div>'
            : items.map(item => {
            const name = item.dish_name ?? 'Prodotto';
            const qty = item.quantity ?? 1;
            const unitPrice = parseFloat(item.unit_price ?? 0);
            const price = (unitPrice * qty).toFixed(2);
            return `<div class="autoconsumo-item-row" data-item-id="${item.id}"
                style="display:flex; align-items:center; gap:10px; padding:10px 14px; background:#f8f9fa; border-radius:6px; border:2px solid #dee2e6; cursor:pointer; transition:border-color 0.15s, background 0.15s; user-select:none;">
                <input type="checkbox" class="autoconsumo-item-check" data-item-id="${item.id}"
                    style="width:20px; height:20px; cursor:pointer; flex-shrink:0; accent-color:#6c757d;">
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:700; font-size:0.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#212529;">${name}</div>
                    <div style="font-size:0.8rem; color:#6c757d; margin-top:1px;"><strong style="color:#dc3545;">€${price}</strong></div>
                </div>
                <div class="autoconsumo-qty-ctrl" onclick="event.stopPropagation()">
                    <button type="button" class="aqi-dec" data-item-id="${item.id}">−</button>
                    <input type="number" class="autoconsumo-qty-input" id="aqi_${item.id}"
                           value="${qty}" min="1" max="${qty}" data-max="${qty}" data-item-id="${item.id}"
                           data-unit-price="${unitPrice}"
                           onclick="event.stopPropagation()">
                    <button type="button" class="aqi-inc" data-item-id="${item.id}">+</button>
                    <span style="font-size:0.72rem;color:#6c757d;white-space:nowrap;">/ ${qty}</span>
                </div>
                <div class="autoconsumo-item-badge" data-item-id="${item.id}"
                    style="font-size:0.75rem; font-weight:700; padding:4px 10px; border-radius:14px; white-space:nowrap; display:none; flex-shrink:0;"></div>
            </div>`;
        }).join('');

        // Spinbox +/- buttons
        itemsList.querySelectorAll('.aqi-dec').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const input = itemsList.querySelector(`#aqi_${btn.dataset.itemId}`);
                if (input && parseInt(input.value) > 1) input.value = parseInt(input.value) - 1;
            });
        });
        itemsList.querySelectorAll('.aqi-inc').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const input = itemsList.querySelector(`#aqi_${btn.dataset.itemId}`);
                const max = parseInt(input?.dataset.max) || 1;
                if (input && parseInt(input.value) < max) input.value = parseInt(input.value) + 1;
            });
        });

        // Apply pre-existing assignment badges (for re-edit case)
        Object.entries(this._autoconsumoAssignments).forEach(([itemId, assignment]) => {
            const badge = itemsList.querySelector(`.autoconsumo-item-badge[data-item-id="${itemId}"]`);
            const row = itemsList.querySelector(`.autoconsumo-item-row[data-item-id="${itemId}"]`);
            const input = itemsList.querySelector(`#aqi_${itemId}`);
            if (input && assignment.quantity) input.value = assignment.quantity;
            if (badge) {
                const maxQty = parseInt(input?.dataset.max) || 1;
                const assignedQty = assignment.quantity || maxQty;
                badge.textContent = assignedQty < maxQty ? `${assignment.userName} (${assignedQty}/${maxQty})` : assignment.userName;
                badge.style.background = assignment.color;
                badge.style.color = 'white';
                badge.style.display = 'inline-block';
            }
            if (row) {
                row.style.borderColor = assignment.color;
                row.style.background = assignment.color + '18';
            }
        });

        // Row click toggles checkbox (skip spinbox area)
        itemsList.querySelectorAll('.autoconsumo-item-row').forEach(row => {
            row.addEventListener('click', (e) => {
                if (e.target.type === 'checkbox' || e.target.classList.contains('aqi-dec') ||
                    e.target.classList.contains('aqi-inc') || e.target.classList.contains('autoconsumo-qty-input') ||
                    e.target.classList.contains('autoconsumo-qty-ctrl')) return;
                const cb = row.querySelector('.autoconsumo-item-check');
                cb.checked = !cb.checked;
            });
        });

        // Select all button
        document.getElementById('btnSelectAllItems').onclick = () => {
            const checks = itemsList.querySelectorAll('.autoconsumo-item-check');
            const allChecked = Array.from(checks).every(c => c.checked);
            checks.forEach(c => { c.checked = !allChecked; });
        };

        // Render operators
        const opList = document.getElementById('autoconsumoOperatorsList');
        opList.innerHTML = operators.map(op => {
            const color = this._autoconsumoOperatorColors[op.id];
            const roleLabel = op.role === 'admin' ? 'Amministratore' : 'Operatore';
            return `<button class="autoconsumo-op-btn" data-user-id="${op.id}" data-user-name="${op.name}"
                style="padding:10px 12px; background:${color}; color:white; border:none; border-radius:6px; font-weight:600; font-size:0.82rem; cursor:pointer; text-align:left; width:100%; text-transform:uppercase;">
                <div style="font-size:0.95rem;">${op.name}</div>
                <div style="font-size:0.72rem; opacity:0.85; font-weight:400; text-transform:none;">${roleLabel}</div>
            </button>`;
        }).join('');

        // Operator button click → assign checked items with their spinbox qty
        opList.querySelectorAll('.autoconsumo-op-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const userId = parseInt(btn.dataset.userId);
                const userName = btn.dataset.userName;
                const color = this._autoconsumoOperatorColors[userId];
                const checks = itemsList.querySelectorAll('.autoconsumo-item-check:checked');
                checks.forEach(cb => {
                    const itemId = parseInt(cb.dataset.itemId);
                    const input = itemsList.querySelector(`#aqi_${itemId}`);
                    const qty = parseInt(input?.value) || 1;
                    const maxQty = parseInt(input?.dataset.max) || 1;
                    this._autoconsumoAssignments[itemId] = { userId, userName, color, quantity: qty };
                    // Update badge
                    const badge = itemsList.querySelector(`.autoconsumo-item-badge[data-item-id="${itemId}"]`);
                    const row = itemsList.querySelector(`.autoconsumo-item-row[data-item-id="${itemId}"]`);
                    if (badge) {
                        badge.textContent = qty < maxQty ? `${userName} (${qty}/${maxQty})` : userName;
                        badge.style.background = color;
                        badge.style.color = 'white';
                        badge.style.display = 'inline-block';
                    }
                    if (row) {
                        row.style.borderColor = color;
                        row.style.background = color + '18';
                    }
                    cb.checked = false;
                });
                this._updateAutoconsumoLegend(items);
            });
        });

        this._updateAutoconsumoLegend(items);
    }

    _updateAutoconsumoLegend(items) {
        const assigned = Object.keys(this._autoconsumoAssignments).length;
        const total = items.length;
        const unassigned = total - assigned;
        const legendEl = document.getElementById('autoconsumoLegend');
        if (legendEl) {
            if (assigned === 0) {
                legendEl.innerHTML = `<span style="color:#dc3545;"><i class="fas fa-exclamation-circle me-1"></i>Nessun piatto assegnato</span>`;
            } else if (unassigned > 0) {
                legendEl.innerHTML = `<span style="color:#fd7e14;"><i class="fas fa-info-circle me-1"></i>${unassigned} piatto/i non assegnati (resteranno in conto)</span>`;
            } else {
                legendEl.innerHTML = `<span style="color:#28a745;"><i class="fas fa-check-circle me-1"></i>Tutti i piatti assegnati</span>`;
            }
        }
    }

    /**
     * Submit autoconsumo (full or partial)
     */
    async _submitAutoconsumo(type) {
        if (!this.currentTable || !this._autoconsumoAuth) return;

        const tableId = this.currentTable.table?.id ?? this.currentTable.id;
        const body = { type };

        if (type === 'partial') {
            if (Object.keys(this._autoconsumoAssignments).length === 0) {
                this.showNotification('Assegna almeno un piatto a un operatore', 'error');
                return;
            }

            const assignments = Object.entries(this._autoconsumoAssignments).map(([itemId, data]) => ({
                item_id: parseInt(itemId),
                user_id: data.userId,
                quantity: data.quantity ?? 1,
            }));
            body.assignments = assignments;
        }

        try {
            const response = await fetch(`${this.apiBase}/${tableId}/free-amount`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': this._autoconsumoAuth.token,
                },
                body: JSON.stringify(body),
            });

            const result = await response.json();

            if (result.success) {
                this._autoconsumoInProgress = false;
                document.getElementById('autoconsumoModal').style.display = 'none';
                document.getElementById('modifyOrderOverlay').style.display = 'none';
                this.showNotification(result.message || 'Autoconsumo registrato');
                this.currentTable = null;
                await this.loadTables();
            } else {
                this.showNotification(result.message || 'Errore nell\'autoconsumo', 'error');
            }
        } catch (error) {
            console.error('Error in autoconsumo:', error);
            this.showNotification('Errore nell\'autoconsumo', 'error');
        }
    }

    /**
     * Initialize autoconsumo modal events (called once from attachModalEvents)
     */
    _initAutoconsumoModal() {
        document.getElementById('btnAutoconsumoCancel')?.addEventListener('click', () => {
            this._cancelAutoconsumo();
        });

        document.getElementById('btnAutoconsumoFull')?.addEventListener('click', () => {
            if (confirm('Confermi di voler mettere tutto il tavolo in autoconsumo?')) {
                this._submitAutoconsumo('full');
            }
        });

        document.getElementById('btnAutoconsumoPartial')?.addEventListener('click', () => {
            this._showAutoconsumoPartialView();
        });

        document.getElementById('btnAutoconsumoBack')?.addEventListener('click', () => {
            this._autoconsumoAssignments = {};
            document.getElementById('autoconsumoModeSelect').style.display = 'block';
            document.getElementById('autoconsumoPartialView').style.display = 'none';
            document.getElementById('autoconsumoFooter').style.display = 'none';
        });

        document.getElementById('btnAutoconsumoConfirm')?.addEventListener('click', () => {
            this._submitAutoconsumo('partial');
        });
    }

    async _cancelAutoconsumo() {
        if (!this.currentTable || !this._autoconsumoAuth) return;

        const tableId = this.currentTable.table?.id ?? this.currentTable.id;

        try {
            await fetch(`${this.apiBase}/${tableId}/cancel-autoconsumo`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Operator-Token': this._autoconsumoAuth.token,
                },
            });
        } catch (e) {
            console.warn('Errore log annullamento autoconsumo', e);
        }

        this._autoconsumoInProgress = false;
        document.getElementById('autoconsumoModal').style.display = 'none';
    }

    /**
     * Pay table — shows payment method selection modal
     */
    payTable() {
        if (!this.currentTable) return;
        if (!this.currentTable.order) {
            this.showNotification('Nessun ordine attivo per questo tavolo', 'error');
            return;
        }
        this.showPaymentMethodModal();
    }

    /**
     * Show payment method selection modal
     * If there are preconto splits, show the split payment view instead.
     */
    async showPaymentMethodModal() {
        const modal = document.getElementById('paymentMethodModal');
        if (!modal) return;

        // Populate info
        const tableNum = document.getElementById('pmTableNumber');
        const total = document.getElementById('pmTotalAmount');
        if (tableNum) tableNum.textContent = this.currentTable.table.table_number;
        if (total) total.textContent = `€${parseFloat(this.currentTable.order.total_amount).toFixed(2)}`;

        modal.style.display = 'flex';

        // Check for preconto splits
        try {
            const resp = await fetch(`${this.apiBase}/${this.currentTable.table.id}/preconto-splits`, {
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
            });
            const data = await resp.json();
            if (data.success && data.data.splits && data.data.splits.length > 0) {
                this._showSplitPaymentView(data.data.splits, data.data.remaining, data.data.order_total);
            } else {
                this._showNormalPaymentView();
            }
        } catch (e) {
            this._showNormalPaymentView();
        }
    }

    _showNormalPaymentView() {
        document.getElementById('normalPaymentView').style.display = 'flex';
        document.getElementById('splitPaymentView').style.display = 'none';
        this._pmShowStep(null);
    }

    /**
     * Show a step inside the normal payment view (null = step 1 doc type choice)
     */
    _pmShowStep(type) {
        if (type === 'fattura') {
            const perms = this.modifySession?.permissions ?? [];
            if (!perms.includes('invoice_payment')) {
                this.showNotification('Non hai il permesso di emettere fatture', 'error');
                return;
            }
        }
        const step1 = document.getElementById('pmStep1');
        const stepS = document.getElementById('pmStepScontrino');
        const stepF = document.getElementById('pmStepFattura');
        if (step1) step1.style.display = type === null ? 'flex' : 'none';
        if (stepS) stepS.style.display = type === 'scontrino' ? 'flex' : 'none';
        if (stepF) stepF.style.display = type === 'fattura' ? 'flex' : 'none';
    }

    _showSplitPaymentView(splits, remaining, orderTotal) {
        this._splitsData = splits;
        document.getElementById('normalPaymentView').style.display = 'none';
        const view = document.getElementById('splitPaymentView');
        view.style.display = 'block';

        const list = document.getElementById('splitPaymentList');
        list.innerHTML = splits.map(s => {
            const isPaid = s.status === 'paid';
            const paidLabel = s.payment_method ? ` · ${s.payment_method.toUpperCase()}` : '';
            return `<div class="split-pay-row ${isPaid ? 'paid' : ''}" data-split-id="${s.id}">
                <div class="split-pay-row-header">
                    <span class="split-pay-label"><i class="fas fa-receipt me-1"></i>${s.label || 'Preconto'}</span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="split-pay-total">€${parseFloat(s.total).toFixed(2)}</span>
                        <span class="split-pay-status ${s.status}">${isPaid ? 'PAGATO' + paidLabel : 'DA PAGARE'}</span>
                        ${!isPaid ? `<button class="split-pay-btn" style="background:#dc3545;flex:0 0 auto;padding:6px 8px;font-size:0.75rem;" onclick="tableOrdersManager.deletePrecontoSplit(${s.id})" title="Elimina preconto"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                </div>
                ${!isPaid ? `
                <div class="split-doctype-row">
                    <button class="split-docbtn scontrino" onclick="tableOrdersManager._splitToggleType(this,'scontrino')">
                        <i class="fas fa-receipt"></i> SCONTRINO
                    </button>
                    <button class="split-docbtn fattura" onclick="tableOrdersManager._splitToggleType(this,'fattura')">
                        <i class="fas fa-file-invoice"></i> FATTURA
                    </button>
                    <button class="split-docbtn" style="background:#6c757d;color:white;" onclick="tableOrdersManager.payPrecontoSplit(${s.id},'chiusura_conto')">
                        <i class="fas fa-times-circle"></i> CHIUSURA CONTO
                    </button>
                </div>
                <div class="split-pay-btns" data-type="scontrino-btns" style="display:none;">
                    <button class="split-back-btn" onclick="tableOrdersManager._splitBack(this)"><i class="fas fa-arrow-left"></i></button>
                    <button class="split-pay-btn contanti" onclick="tableOrdersManager.payPrecontoSplit(${s.id},'contanti')"><i class="fas fa-coins"></i> CONTANTI</button>
                    <button class="split-pay-btn pos" onclick="tableOrdersManager.payPrecontoSplit(${s.id},'pos')"><i class="fas fa-credit-card"></i> POS</button>
                </div>
                <div class="split-pay-btns" data-type="fattura-btns" style="display:none;">
                    <button class="split-back-btn" onclick="tableOrdersManager._splitBack(this)"><i class="fas fa-arrow-left"></i></button>
                    <button class="split-pay-btn contanti" onclick="tableOrdersManager.payPrecontoSplit(${s.id},'fattura_contanti')"><i class="fas fa-coins"></i> CONTANTI</button>
                    <button class="split-pay-btn pos" onclick="tableOrdersManager.payPrecontoSplit(${s.id},'fattura_pos')"><i class="fas fa-credit-card"></i> POS</button>
                    <button class="split-pay-btn bonifico" onclick="tableOrdersManager.payPrecontoSplit(${s.id},'bonifico')"><i class="fas fa-university"></i> BONIFICO</button>
                    <button class="split-pay-btn assegno" onclick="tableOrdersManager.payPrecontoSplit(${s.id},'assegno')"><i class="fas fa-money-check"></i> ASSEGNO</button>
                </div>` : ''}
            </div>`;
        }).join('');

        // Add remainder row if needed
        if (remaining > 0.01) {
            list.innerHTML += `<div class="split-pay-row split-remainder-row">
                <div class="split-pay-row-header">
                    <span class="split-pay-label"><i class="fas fa-calculator me-1"></i>Resto</span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="split-pay-total">€${parseFloat(remaining).toFixed(2)}</span>
                        <span class="split-pay-status pending">DA PAGARE</span>
                    </div>
                </div>
                <div class="split-doctype-row">
                    <button class="split-docbtn scontrino" onclick="tableOrdersManager._splitToggleType(this,'scontrino')">
                        <i class="fas fa-receipt"></i> SCONTRINO
                    </button>
                    <button class="split-docbtn fattura" onclick="tableOrdersManager._splitToggleType(this,'fattura')">
                        <i class="fas fa-file-invoice"></i> FATTURA
                    </button>
                    <button class="split-docbtn" style="background:#6c757d;color:white;" onclick="tableOrdersManager.executePayment('chiusura_conto')">
                        <i class="fas fa-times-circle"></i> CHIUSURA CONTO
                    </button>
                </div>
                <div class="split-pay-btns" data-type="scontrino-btns" style="display:none;">
                    <button class="split-back-btn" onclick="tableOrdersManager._splitBack(this)"><i class="fas fa-arrow-left"></i></button>
                    <button class="split-pay-btn contanti" onclick="tableOrdersManager.executePayment('contanti')"><i class="fas fa-coins"></i> CONTANTI</button>
                    <button class="split-pay-btn pos" onclick="tableOrdersManager.executePayment('pos')"><i class="fas fa-credit-card"></i> POS</button>
                </div>
                <div class="split-pay-btns" data-type="fattura-btns" style="display:none;">
                    <button class="split-back-btn" onclick="tableOrdersManager._splitBack(this)"><i class="fas fa-arrow-left"></i></button>
                    <button class="split-pay-btn contanti" onclick="tableOrdersManager.openInvoiceModal('fattura_contanti')"><i class="fas fa-coins"></i> CONTANTI</button>
                    <button class="split-pay-btn pos" onclick="tableOrdersManager.openInvoiceModal('fattura_pos')"><i class="fas fa-credit-card"></i> POS</button>
                    <button class="split-pay-btn bonifico" onclick="tableOrdersManager.openInvoiceModal('bonifico')"><i class="fas fa-university"></i> BONIFICO</button>
                    <button class="split-pay-btn assegno" onclick="tableOrdersManager.openInvoiceModal('assegno')"><i class="fas fa-money-check"></i> ASSEGNO</button>
                </div>
            </div>`;
        }
    }

    /** Show sub-methods (scontrino/fattura) inside a split row */
    _splitToggleType(btn, type) {
        if (type === 'fattura') {
            const perms = this.modifySession?.permissions ?? [];
            if (!perms.includes('invoice_payment')) {
                this.showNotification('Non hai il permesso di emettere fatture', 'error');
                return;
            }
        }
        const row = btn.closest('.split-pay-row, .split-remainder-row');
        row.querySelector('.split-doctype-row').style.display = 'none';
        row.querySelector(`[data-type="${type}-btns"]`).style.display = 'flex';
    }

    /** Go back to doc-type choice inside a split row */
    _splitBack(btn) {
        const row = btn.closest('.split-pay-row, .split-remainder-row');
        row.querySelector('.split-doctype-row').style.display = 'flex';
        row.querySelectorAll('[data-type]').forEach(el => el.style.display = 'none');
    }

    /**
     * Delete a pending preconto split
     */
    async deletePrecontoSplit(splitId) {
        if (!this.currentTable) return;
        if (!confirm('Eliminare questo preconto parziale?')) return;

        try {
            const resp = await fetch(`${this.apiBase}/${this.currentTable.table.id}/preconto-splits/${splitId}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
            });
            const result = await resp.json();
            if (result.success) {
                this.showNotification('Preconto eliminato', 'success');
                // Remove from pending splits in session
                this.modifySession.pendingSplits = (this.modifySession.pendingSplits ?? []).filter(s => s.id !== splitId);
                // Reload split view
                const splitsResp = await fetch(`${this.apiBase}/${this.currentTable.table.id}/preconto-splits`, {
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
                });
                const splitsData = await splitsResp.json();
                if (splitsData.success && splitsData.data.splits.length > 0) {
                    this._showSplitPaymentView(splitsData.data.splits, splitsData.data.remaining, splitsData.data.order_total);
                } else {
                    this._showNormalPaymentView();
                }
                await this.loadTables();
            } else {
                this.showNotification(result.message || 'Errore eliminazione', 'error');
            }
        } catch (e) {
            console.error('Error deleting split:', e);
            this.showNotification('Errore eliminazione preconto', 'error');
        }
    }

    /**
     * Pay a single preconto split
     */
    async payPrecontoSplit(splitId, method) {
        if (!this.currentTable) return;

        // Disable all split buttons to prevent double-click
        document.querySelectorAll('.split-pay-btn').forEach(b => b.disabled = true);

        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) {
                document.querySelectorAll('.split-pay-btn').forEach(b => b.disabled = false);
                return;
            }
        } catch (e) {
            document.querySelectorAll('.split-pay-btn').forEach(b => b.disabled = false);
            return;
        }

        // ── Permission checks ─────────────────────────────────────────────────
        const splitPerms = auth.permissions ?? [];
        console.log('🔐 Permission Check (Split) - Method:', method, '| Permissions:', splitPerms);

        if (!splitPerms.includes('close_bills')) {
            console.log('❌ BLOCKED (Split): Missing close_bills permission');
            this.showNotification('Non hai il permesso di chiudere i conti', 'error');
            document.querySelectorAll('.split-pay-btn').forEach(b => b.disabled = false);
            return;
        }
        const isContantiMethod = method === 'contanti' || method === 'fattura_contanti';
        const isPosMethod = method === 'pos' || method === 'fattura_pos';
        if (isContantiMethod && !splitPerms.includes('cash_payment')) {
            console.log('❌ BLOCKED (Split): Missing cash_payment permission');
            this.showNotification('Non hai il permesso di ricevere pagamenti in contanti', 'error');
            document.querySelectorAll('.split-pay-btn').forEach(b => b.disabled = false);
            return;
        }
        if (isPosMethod && !splitPerms.includes('pos_payment')) {
            console.log('❌ BLOCKED (Split): Missing pos_payment permission');
            this.showNotification('Non hai il permesso di ricevere pagamenti POS', 'error');
            document.querySelectorAll('.split-pay-btn').forEach(b => b.disabled = false);
            return;
        }
        const isFatturaMethod = ['fattura_contanti', 'fattura_pos', 'bonifico', 'assegno'].includes(method);
        if (isFatturaMethod && !splitPerms.includes('invoice_payment')) {
            this.showNotification('Non hai il permesso di emettere fatture', 'error');
            document.querySelectorAll('.split-pay-btn').forEach(b => b.disabled = false);
            return;
        }
        console.log('✅ ALLOWED (Split): All permissions granted');

        const doPaySplit = async () => {
            try {
                const resp = await fetch(`${this.apiBase}/${this.currentTable.table.id}/pay-split/${splitId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'X-Operator-Token': auth.token
                    },
                    body: JSON.stringify({ payment_method: method })
                });
                const result = await resp.json();

                if (result.success) {
                    if (result.data?.order_closed) {
                        this.showNotification('Conto chiuso completamente', 'success');
                        this.closePaymentMethodModal();
                        this._afterPaymentSuccess();
                    } else {
                        this.showNotification(result.message || 'Quota pagata', 'success');

                        // Update modify session: remove/reduce paid items, update total
                        const paidItems = result.data?.paid_items ?? [];
                        const paidSplitTotal = result.data?.paid_split_total ?? 0;
                        this.modifySession.paidSplitsTotal = (this.modifySession.paidSplitsTotal || 0) + paidSplitTotal;
                        this.modifySession.paidCoversTotal = (this.modifySession.paidCoversTotal || 0) + (result.data?.paid_cover_amount ?? 0);
                        this.modifySession.pendingSplits = (this.modifySession.pendingSplits ?? []).filter(s => s.id !== splitId);
                        if (paidItems.length > 0) {
                            this._applyPaidItemsToSession(paidItems);
                        }
                        const modifyOverlay = document.getElementById('modifyOrderOverlay');
                        if (modifyOverlay && modifyOverlay.style.display === 'block') {
                            this.updateModifyReceiptItems();
                        }

                        const splitsResp = await fetch(`${this.apiBase}/${this.currentTable.table.id}/preconto-splits`, {
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
                        });
                        const splitsData = await splitsResp.json();
                        if (splitsData.success) {
                            this._showSplitPaymentView(splitsData.data.splits, splitsData.data.remaining, splitsData.data.order_total);
                        }
                    }
                } else {
                    this.showNotification(result.message || 'Errore nel pagamento', 'error');
                    document.querySelectorAll('.split-pay-btn').forEach(b => b.disabled = false);
                }
            } catch (e) {
                console.error('Error paying split:', e);
                this.showNotification('Errore nel pagamento', 'error');
                document.querySelectorAll('.split-pay-btn').forEach(b => b.disabled = false);
            }
        };

        if (method === 'contanti') {
            const splitData = this._splitsData?.find(s => s.id === splitId);
            const amount = parseFloat(splitData?.total ?? 0);
            await this.startCashDrawerFlow(amount, this.currentTable.order.id, auth.token, doPaySplit, null, this.currentTable.table.id);
        } else {
            await doPaySplit();
        }
    }

    /**
     * Close payment method modal
     */
    closePaymentMethodModal() {
        const modal = document.getElementById('paymentMethodModal');
        if (modal) modal.style.display = 'none';
    }

    /**
     * Chiudi conto con pagamento contanti e apri il cassetto.
     */
    async startCashDrawerFlow(amount, tableOrderId, authToken, onComplete = null, onReady = null, tableId = null) {
        const overlay = document.getElementById('cashDrawerOverlay');
        const statusEl = document.getElementById('cashDrawerStatus');
        const amountEl = document.getElementById('cashDrawerAmount');
        const cancelBtn = document.getElementById('cashDrawerCancelBtn');
        const fallbackSection = document.getElementById('cashDrawerFallbackSection');
        const fallbackBtn = document.getElementById('cashDrawerFallbackBtn');

        if (!overlay) return;

        amountEl.textContent = `€${parseFloat(amount).toFixed(2)}`;
        statusEl.textContent = 'Avvio pagamento sulla cassa automatica...';
        cancelBtn.disabled = false;
        if (fallbackSection) fallbackSection.style.display = 'none';
        if (fallbackBtn) fallbackBtn.disabled = false;
        overlay.style.display = 'flex';
        window.addEventListener('beforeunload', this._beforeUnloadHandler);

        this._cashDrawerToken = authToken;
        this._cashDrawerTableId = tableId;
        this._cashDrawerPollErrors = 0;
        // Assegna subito onComplete così _executeCashDrawerFallback può usarlo anche in caso di errore all'avvio
        this._cashDrawerOnComplete = onComplete ?? (() => this._afterPaymentSuccess());

        try {
            const resp = await fetch(`${this.apiBase}/open-cash-drawer`, {
                method: 'POST',
                body: JSON.stringify({ amount, table_order_id: tableOrderId }),
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': authToken,
                },
            });
            const data = await resp.json();

            if (!data.success) {
                console.error('Cash drawer open failed:', data);
                await this._executeCashDrawerFallback();
                return;
            }

            this._cashDrawerOperationId = data.operation_id;

            // Hook opzionale eseguito dopo l'apertura del cassetto, prima del polling.
            // Se restituisce false il flusso viene interrotto.
            if (onReady) {
                const proceed = await onReady();
                if (proceed === false) {
                    this._hideCashDrawerOverlay();
                    return;
                }
            }

            statusEl.textContent = 'In attesa del pagamento dalla cassa automatica...';
            this._cashDrawerPollInterval = setInterval(() => this._pollCashDrawer(), 250);

        } catch (e) {
            console.error('Cash drawer start error:', e);
            await this._executeCashDrawerFallback();
        }
    }

    async _pollCashDrawer() {
        try {
            const resp = await fetch(`${this.apiBase}/cash-drawer/poll`, {
                method: 'POST',
                body: JSON.stringify({ operation_id: this._cashDrawerOperationId }),
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': this._cashDrawerToken,
                },
            });
            const data = await resp.json();

            if (data.payment_status === 1) {
                clearInterval(this._cashDrawerPollInterval);
                this._cashDrawerPollInterval = null;
                this._hideCashDrawerOverlay();
                await this._cashDrawerOnComplete();
            }
            // payment_status === 2 → in corso, continua
        } catch (e) {
            this._cashDrawerPollErrors = (this._cashDrawerPollErrors ?? 0) + 1;
            const statusEl = document.getElementById('cashDrawerStatus');
            if (statusEl) statusEl.textContent = 'Errore di comunicazione, riprovo...';

            if (this._cashDrawerPollErrors >= 8) {
                const fallbackSection = document.getElementById('cashDrawerFallbackSection');
                if (fallbackSection) fallbackSection.style.display = 'block';
            }
        }
    }

    async cancelCashDrawerTransaction() {
        const cancelBtn = document.getElementById('cashDrawerCancelBtn');
        if (cancelBtn) cancelBtn.disabled = true;

        clearInterval(this._cashDrawerPollInterval);
        this._cashDrawerPollInterval = null;

        try {
            await fetch(`${this.apiBase}/cash-drawer/cancel`, {
                method: 'POST',
                body: JSON.stringify({ operation_id: this._cashDrawerOperationId }),
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': this._cashDrawerToken,
                },
            });
        } catch (e) {
            console.error('Cash drawer cancel error:', e);
        }

        this._hideCashDrawerOverlay();
        await this._cashDrawerOnComplete();
    }

    _hideCashDrawerOverlay() {
        const overlay = document.getElementById('cashDrawerOverlay');
        if (overlay) overlay.style.display = 'none';
        const fallbackSection = document.getElementById('cashDrawerFallbackSection');
        if (fallbackSection) fallbackSection.style.display = 'none';
        window.removeEventListener('beforeunload', this._beforeUnloadHandler);
        this._cashDrawerOperationId = null;
        this._cashDrawerToken = null;
        this._cashDrawerTableId = null;
        this._cashDrawerPollErrors = 0;
        this._cashDrawerOnComplete = null;
    }

    async _executeCashDrawerFallback() {
        const fallbackBtn = document.getElementById('cashDrawerFallbackBtn');
        if (fallbackBtn) fallbackBtn.disabled = true;

        clearInterval(this._cashDrawerPollInterval);
        this._cashDrawerPollInterval = null;

        // Invia log al backend prima di completare il pagamento
        const tableId = this._cashDrawerTableId;
        const authToken = this._cashDrawerToken;
        if (tableId && authToken) {
            try {
                await fetch(`${this.apiBase}/${tableId}/log-cash-drawer-failed`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'X-Operator-Token': authToken,
                    },
                });
            } catch (e) {
                console.error('log-cash-drawer-failed error:', e);
            }
        }

        const onComplete = this._cashDrawerOnComplete;
        this._hideCashDrawerOverlay();

        if (onComplete) await onComplete();

        const fallbackAlert = document.getElementById('cashDrawerFallbackAlert');
        if (fallbackAlert) fallbackAlert.style.display = 'flex';
    }

    async chiudiContoContanti() {
        if (!this.currentTable) return;

        // Operator auth
        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch {
            return;
        }

        // Check close_bills permission
        const perms = auth.permissions ?? [];
        console.log('🔐 Permission Check - Operation: chiudiContoContanti | Permissions:', perms);
        if (!perms.includes('close_bills')) {
            console.log('❌ BLOCKED: Missing close_bills permission');
            this.showNotification('Non hai il permesso di chiudere i conti', 'error');
            return;
        }
        console.log('✅ ALLOWED: close_bills permission granted');

        const amount = parseFloat(this.currentTable.order.discounted_total ?? this.currentTable.order.total_amount ?? 0);
        const tableId = this.currentTable.table.id;
        await this.startCashDrawerFlow(
            amount,
            this.currentTable.order.id,
            auth.token,
            // onComplete: chiamato quando il poll del cassetto conferma l'operazione
            () => this._afterPaymentSuccess(),
            // onReady: chiamato subito dopo l'apertura del cassetto, prima del polling
            async () => {
                try {
                    const response = await fetch(`${this.apiBase}/${tableId}/pay`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            'X-Operator-Token': auth.token,
                        },
                        body: JSON.stringify({ payment_method: 'contanti' }),
                    });
                    const result = await response.json();
                    if (!result.success) {
                        this.showNotification(result.message || 'Errore nell\'incasso', 'error');
                        return false;
                    }
                    this.showNotification(`Conto registrato: €${parseFloat(result.data.total_paid).toFixed(2)}`);
                    return true;
                } catch (error) {
                    console.error('Error chiudi conto contanti:', error);
                    this.showNotification('Errore nell\'incasso', 'error');
                    return false;
                }
            },
            tableId
        );
    }

    /**
     * Execute payment with specified method (pos/contanti).
     * When method is 'pos', first sends a charge request to the physical POS terminal
     * via the backend TCP/JSONPOS bridge; proceeds with closing the order only on approval.
     */
    async executePayment(method) {
        if (!this.currentTable) return;

        this.closePaymentMethodModal();

        // ── POS terminal charge (only for 'pos' payments) ─────────────────────
        if (method === 'pos') {
            this.showNotification('POS in attesa di pagamento...', 'info');

            try {
                const posResp = await fetch(`${this.apiBase}/${this.currentTable.table.id}/pos-charge`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                });

                const posResult = await posResp.json();

                if (!posResult.success) {
                    // Hard failure — POS declined or unreachable
                    this.showNotification(posResult.message || 'Pagamento POS rifiutato', 'error');
                    return;
                }

                if (!posResult.pos_skipped) {
                    // Real POS approval — notify operator
                    this.showNotification('Pagamento POS approvato', 'success');
                }
            } catch (error) {
                console.error('POS charge error:', error);
                this.showNotification('Errore comunicazione POS', 'error');
                return;
            }
        }

        // ── Operator authentication ────────────────────────────────────────────
        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) {
            console.log('Authentication cancelled');
            return;
        }

        // ── Permission checks ─────────────────────────────────────────────────
        const perms = auth.permissions ?? [];
        console.log('🔐 Permission Check - Method:', method, '| Permissions:', perms);

        if (!perms.includes('close_bills')) {
            console.log('❌ BLOCKED: Missing close_bills permission');
            this.showNotification('Non hai il permesso di chiudere i conti', 'error');
            return;
        }
        if (method === 'contanti' && !perms.includes('cash_payment')) {
            console.log('❌ BLOCKED: Missing cash_payment permission');
            this.showNotification('Non hai il permesso di ricevere pagamenti in contanti', 'error');
            return;
        }
        if (method === 'pos' && !perms.includes('pos_payment')) {
            console.log('❌ BLOCKED: Missing pos_payment permission');
            this.showNotification('Non hai il permesso di ricevere pagamenti POS', 'error');
            return;
        }
        console.log('✅ ALLOWED: All permissions granted');

        // ── Contanti: apri cassetto prima di chiudere il conto ────────────────
        if (method === 'contanti') {
            const amount = parseFloat(this.currentTable.order.discounted_total ?? this.currentTable.order.total_amount ?? 0);
            const tableId = this.currentTable.table.id;
            await this.startCashDrawerFlow(amount, this.currentTable.order.id, auth.token, async () => {
                try {
                    const response = await fetch(`${this.apiBase}/${tableId}/pay`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            'X-Operator-Token': auth.token,
                        },
                        body: JSON.stringify({ payment_method: 'contanti' }),
                    });
                    const result = await response.json();
                    if (result.success) {
                        this.showNotification(`Conto incassato: €${parseFloat(result.data.total_paid).toFixed(2)}`);
                        this._afterPaymentSuccess();
                    } else {
                        this.showNotification(result.message || 'Errore nell\'incasso', 'error');
                    }
                } catch (e) {
                    console.error('Error paying table after cash drawer:', e);
                    this.showNotification('Errore nell\'incasso', 'error');
                }
            }, null, tableId);
            return;
        }

        // ── Close the order (non-contanti) ────────────────────────────────────
        try {
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/pay`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token
                },
                body: JSON.stringify({ payment_method: method })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(`Conto incassato: €${parseFloat(result.data.total_paid).toFixed(2)}`);
                this._afterPaymentSuccess();
            } else {
                this.showNotification(result.message || 'Errore nell\'incasso', 'error');
            }
        } catch (error) {
            console.error('Error paying table:', error);
            this.showNotification('Errore nell\'incasso', 'error');
        }
    }

    /**
     * Post-payment cleanup (hide overlays, reset state)
     */
    /**
     * Initialize the manager for a specific table when running inside the backoffice
     */
    async initForBackoffice(tableId) {
        try {
            // Get operator token for the logged-in admin
            const tokenResp = await fetch('/api/admin/operator-token');
            const tokenData = await tokenResp.json();
            if (!tokenData.success) {
                console.error('initForBackoffice: failed to get admin token');
                return;
            }

            // Load current table data
            const tableResp = await fetch(`${this.apiBase}/${tableId}`);
            const tableResult = await tableResp.json();
            if (!tableResult.success) {
                console.error('initForBackoffice: failed to load table');
                return;
            }

            this.currentTable = tableResult.data;
            this.modifySession.token = tokenData.data.token;
            this.modifySession.permissions = tokenData.data.permissions ?? [];
            if (tableResult.data.order) {
                this.modifySession.active = true;
                this._initSessionFromOrder(tableResult.data.order);
            }

            // Enable all backoffice action buttons
            document.querySelectorAll('[data-bo-action]').forEach(btn => {
                btn.disabled = false;
            });

            const statusSpan = document.getElementById('boStatusSpan');
            if (statusSpan) statusSpan.style.display = 'none';
        } catch (e) {
            console.error('initForBackoffice error:', e);
        }
    }

    _afterPaymentSuccess() {
        this.currentTable = null;
        this.modifySession.active = false;

        if (window._backofficeMode) {
            window.location.reload();
            return;
        }

        this.loadTables();

        // Hide overlays
        const receiptOverlay = this.getElement('receiptOverlay');
        if (receiptOverlay) receiptOverlay.style.display = 'none';

        this._hideModifyOverlay();

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
    }

    /**
     * Open invoice modal — default 1 invoice for the full total
     * @param {string} method  Payment method (fattura_contanti, fattura_pos, bonifico, assegno)
     */
    openInvoiceModal(method = 'fattura_pos') {
        if (!this.currentTable || !this.currentTable.order) return;

        const perms = this.modifySession?.permissions ?? [];
        if (!perms.includes('invoice_payment')) {
            this.showNotification('Non hai il permesso di emettere fatture', 'error');
            return;
        }

        this._pendingInvoicePaymentMethod = method;
        this.closePaymentMethodModal();

        const modal = document.getElementById('invoiceModal');
        if (!modal) return;

        const order = this.currentTable.order;
        const total = parseFloat(order.total_amount);
        const covers = order.covers || 1;

        document.getElementById('invoiceTableNumber').textContent = this.currentTable.table.table_number;
        document.getElementById('invoiceTotalTable').textContent = `€${total.toFixed(2)}`;
        document.getElementById('invoiceCoversCount').textContent = covers > 0 ? `${covers} cop.` : 'Bevande';

        // Always start with split = 1 (single invoice for the full amount)
        this._invoiceSplit = 1;
        this._invoiceRowIndex = 0;
        document.getElementById('invoiceSplitCount').textContent = '1';
        this._rebuildInvoiceRows();

        modal.style.display = 'flex';
    }

    /**
     * Close invoice modal
     */
    closeInvoiceModal() {
        const modal = document.getElementById('invoiceModal');
        if (modal) modal.style.display = 'none';
    }

    /**
     * Change the split count by delta (+1 or -1) and rebuild rows
     */
    _changeSplit(delta) {
        if (!this.currentTable || !this.currentTable.order) return;
        const covers = this.currentTable.order.covers || 1;
        const maxSplit = Math.max(covers, 20);
        const next = Math.min(maxSplit, Math.max(1, (this._invoiceSplit || 1) + delta));
        if (next === this._invoiceSplit) return;
        this._invoiceSplit = next;
        document.getElementById('invoiceSplitCount').textContent = next;
        this._rebuildInvoiceRows();
    }

    /**
     * Rebuild invoice rows to match current split count.
     * Preserves all customer fields already typed.
     */
    _rebuildInvoiceRows() {
        if (!this.currentTable || !this.currentTable.order) return;

        const total  = parseFloat(this.currentTable.order.total_amount) || 0;
        const n      = this._invoiceSplit || 1;
        const perRow = parseFloat((total / n).toFixed(2));

        // Save existing per-row data before clearing
        const saved = [];
        document.querySelectorAll('.invoice-row').forEach(row => {
            saved.push({
                description:          row.querySelector('.invoice-description')?.value            || 'Pasto completo',
                customerId:           row.querySelector('.invoice-customer-id')?.value            || '',
                userType:             row.querySelector('.invoice-user-type')?.value              || 'private',
                customerName:         row.querySelector('.invoice-customer-name')?.value          || '',
                fiscalCode:           row.querySelector('.invoice-fiscal-code')?.value            || '',
                vatNumber:            row.querySelector('.invoice-vat-number')?.value             || '',
                address:              row.querySelector('.invoice-address')?.value                || '',
                zipCode:              row.querySelector('.invoice-zip-code')?.value               || '',
                city:                 row.querySelector('.invoice-city')?.value                   || '',
                province:             row.querySelector('.invoice-province')?.value               || '',
                codiceDestinatario:   row.querySelector('.invoice-codice-destinatario')?.value    || '',
                pecDestinatario:      row.querySelector('.invoice-pec-destinatario')?.value       || '',
                saveCustomer:         row.querySelector('.invoice-save-customer')?.checked        || false,
            });
        });

        document.getElementById('invoiceRowsContainer').innerHTML = '';
        this._invoiceRowIndex = 0;

        for (let i = 0; i < n; i++) {
            // Last row gets the remainder to avoid rounding gaps
            const amount = i < n - 1 ? perRow : parseFloat((total - perRow * (n - 1)).toFixed(2));
            const prev   = saved[i] || {};
            this._addInvoiceRow(amount, prev);
        }

        // Update per-person amount display
        document.getElementById('invoicePerPersonAmount').textContent = `€${perRow.toFixed(2)}`;

        this._updateInvoiceTotals();
    }

    /**
     * Add a single invoice row with full customer fields for XML generation.
     * @param {number} defaultAmount
     * @param {object} data  Prefill values (description, userType, customerName, fiscalCode, …)
     */
    _addInvoiceRow(defaultAmount = 0, data = {}) {
        const idx       = this._invoiceRowIndex++;
        const container = document.getElementById('invoiceRowsContainer');
        const rowNum    = idx + 1;

        const description        = data.description        || 'Pasto completo';
        const customerId         = data.customerId         || '';
        const userType           = data.userType           || 'private';
        const customerName       = data.customerName       || '';
        const fiscalCode         = data.fiscalCode         || '';
        const vatNumber          = data.vatNumber          || '';
        const address            = data.address            || '';
        const zipCode            = data.zipCode            || '';
        const city               = data.city               || '';
        const province           = data.province           || '';
        const codiceDestinatario = data.codiceDestinatario || '';
        const pecDestinatario    = data.pecDestinatario    || '';
        const saveCustomer       = data.saveCustomer       || false;

        const isCompany = userType === 'company' || userType === 'public_company';
        const companyDisplay = isCompany ? 'grid' : 'none';

        const row = document.createElement('div');
        row.className      = 'invoice-row';
        row.dataset.rowIdx = idx;
        row.innerHTML = `
            <input type="hidden" class="invoice-customer-id" value="${customerId}">

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <span class="invoice-row-badge">Ospite ${rowNum}</span>
                <button class="btn-remove-invoice-row" onclick="tableOrdersManager._removeInvoiceRow(${idx})" title="Rimuovi ospite">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Autocomplete search -->
            <div style="position:relative; margin-bottom:8px;">
                <input type="text" class="invoice-customer-search" placeholder="Cerca cliente per nome, CF o P.IVA…"
                    autocomplete="off"
                    oninput="tableOrdersManager._onCustomerSearch(this, ${idx})"
                    style="width:100%;">
                <div class="invoice-customer-suggestions" data-row="${idx}"
                    style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ccc; border-radius:4px; z-index:999; max-height:180px; overflow-y:auto;"></div>
            </div>

            <!-- Row 1: importo, descrizione, tipo cliente -->
            <div style="display:grid; grid-template-columns:110px 1fr 180px; gap:10px; align-items:end; margin-bottom:10px;">
                <div>
                    <label>Importo (€) <span class="req">*</span></label>
                    <input type="number" class="invoice-amount" step="0.01" min="0"
                           value="${parseFloat(defaultAmount).toFixed(2)}" placeholder="0.00"
                           oninput="tableOrdersManager._updateInvoiceTotals()"
                           onfocus="this.classList.remove('invoice-field-error')">
                </div>
                <div>
                    <label>Descrizione fattura</label>
                    <input type="text" class="invoice-description" value="${description}" placeholder="Pasto completo">
                </div>
                <div>
                    <label>Tipo cliente</label>
                    <select class="invoice-user-type" onchange="tableOrdersManager._onInvoiceUserTypeChange(this, ${idx})">
                        <option value="private"${userType === 'private' ? ' selected' : ''}>Privato</option>
                        <option value="company"${userType === 'company' ? ' selected' : ''}>Azienda</option>
                        <option value="public_company"${userType === 'public_company' ? ' selected' : ''}>Ente Pubblico</option>
                    </select>
                </div>
            </div>

            <!-- Row 2: nome, CF, P.IVA -->
            <div style="display:grid; grid-template-columns:1fr 170px 170px; gap:10px; align-items:end; margin-bottom:10px;">
                <div>
                    <label>Nome / Ragione sociale <span class="req">*</span></label>
                    <input type="text" class="invoice-customer-name" value="${customerName}" placeholder="Mario Rossi"
                           onfocus="this.classList.remove('invoice-field-error')">
                </div>
                <div>
                    <label>Codice Fiscale <span class="req invoice-cf-req"${isCompany ? ' style="display:none"' : ''}>*</span></label>
                    <input type="text" class="invoice-fiscal-code" value="${fiscalCode}" placeholder="RSSMRA80A01H501U" style="text-transform:uppercase;"
                           onfocus="this.classList.remove('invoice-field-error')">
                </div>
                <div>
                    <label>P.IVA <span class="req invoice-piva-req"${!isCompany ? ' style="display:none"' : ''}>*</span></label>
                    <input type="text" class="invoice-vat-number" value="${vatNumber}" placeholder="01234567890"
                           onfocus="this.classList.remove('invoice-field-error')">
                </div>
            </div>

            <!-- Row 3: indirizzo (aziende) -->
            <div class="invoice-company-fields" style="display:${companyDisplay}; grid-template-columns:1fr 80px 1fr 60px; gap:10px; align-items:end; margin-bottom:10px;">
                <div>
                    <label>Indirizzo <span class="req">*</span></label>
                    <input type="text" class="invoice-address" value="${address}" placeholder="Via Roma 1"
                           onfocus="this.classList.remove('invoice-field-error')">
                </div>
                <div>
                    <label>CAP <span class="req">*</span></label>
                    <input type="text" class="invoice-zip-code" value="${zipCode}" placeholder="00100" maxlength="10"
                           onfocus="this.classList.remove('invoice-field-error')">
                </div>
                <div>
                    <label>Comune <span class="req">*</span></label>
                    <input type="text" class="invoice-city" value="${city}" placeholder="Roma"
                           onfocus="this.classList.remove('invoice-field-error')">
                </div>
                <div>
                    <label>Prov. <span class="req">*</span></label>
                    <input type="text" class="invoice-province" value="${province}" placeholder="RM" maxlength="5" style="text-transform:uppercase;"
                           onfocus="this.classList.remove('invoice-field-error')">
                </div>
            </div>

            <!-- Row 4: SDI / PEC (aziende) -->
            <div class="invoice-company-fields" style="display:${companyDisplay}; grid-template-columns:150px 1fr; gap:10px; align-items:end; margin-bottom:10px;">
                <div>
                    <label>Codice SDI</label>
                    <input type="text" class="invoice-codice-destinatario" value="${codiceDestinatario}" placeholder="0000000" maxlength="7" style="text-transform:uppercase;">
                </div>
                <div>
                    <label>PEC Destinatario</label>
                    <input type="text" class="invoice-pec-destinatario" value="${pecDestinatario}" placeholder="pec@example.com">
                </div>
            </div>

            <!-- Salva cliente -->
            <div style="margin-top:4px;">
                <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-size:0.85em;">
                    <input type="checkbox" class="invoice-save-customer"${saveCustomer ? ' checked' : ''}>
                    Salva cliente per usi futuri
                </label>
            </div>
        `;
        container.appendChild(row);
    }

    /**
     * Show/hide company-only fields when user type changes
     */
    _onInvoiceUserTypeChange(select, idx) {
        const row = document.querySelector(`.invoice-row[data-row-idx="${idx}"]`);
        if (!row) return;
        const isCompany = select.value === 'company' || select.value === 'public_company';
        row.querySelectorAll('.invoice-company-fields').forEach(el => {
            el.style.display = isCompany ? 'grid' : 'none';
        });
        // Toggle required indicators: CF for private, P.IVA for company
        const cfReq = row.querySelector('.invoice-cf-req');
        const pivaReq = row.querySelector('.invoice-piva-req');
        if (cfReq) cfReq.style.display = isCompany ? 'none' : '';
        if (pivaReq) pivaReq.style.display = isCompany ? '' : 'none';
        // Clear error highlights on toggled fields
        row.querySelectorAll('.invoice-field-error').forEach(el => el.classList.remove('invoice-field-error'));
    }

    /**
     * Autocomplete: search customers as user types
     */
    async _onCustomerSearch(input, idx) {
        const q = input.value.trim();
        const suggestionsEl = document.querySelector(`.invoice-customer-suggestions[data-row="${idx}"]`);
        if (!suggestionsEl) return;

        if (q.length < 2) {
            suggestionsEl.style.display = 'none';
            suggestionsEl.innerHTML = '';
            return;
        }

        try {
            const resp = await fetch(`/api/customers?q=${encodeURIComponent(q)}`, {
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
            });
            const data = await resp.json();
            const customers = data.data || [];

            if (customers.length === 0) {
                suggestionsEl.style.display = 'none';
                return;
            }

            suggestionsEl.innerHTML = customers.map(c => `
                <div class="invoice-customer-suggestion-item" style="padding:8px 10px; cursor:pointer; border-bottom:1px solid #eee; font-size:0.9em;"
                    onmousedown="tableOrdersManager._fillInvoiceRowFromCustomer(${idx}, ${JSON.stringify(c).replace(/"/g, '&quot;')})">
                    <strong>${c.full_name}</strong>
                    <span style="color:#888; font-size:0.85em; margin-left:6px;">${c.fiscal_code || c.vat_number || ''}</span>
                </div>
            `).join('');
            suggestionsEl.style.display = 'block';
        } catch (e) {
            console.error('Customer search error:', e);
        }
    }

    /**
     * Fill invoice row fields from a selected customer object
     */
    _fillInvoiceRowFromCustomer(idx, customer) {
        const row = document.querySelector(`.invoice-row[data-row-idx="${idx}"]`);
        if (!row) return;

        row.querySelector('.invoice-customer-id').value          = customer.id || '';
        row.querySelector('.invoice-user-type').value            = customer.user_type || 'private';
        row.querySelector('.invoice-customer-name').value        = customer.full_name || '';
        row.querySelector('.invoice-fiscal-code').value          = customer.fiscal_code || '';
        row.querySelector('.invoice-vat-number').value           = customer.vat_number || '';
        row.querySelector('.invoice-address').value              = customer.address || '';
        row.querySelector('.invoice-zip-code').value             = customer.zip_code || '';
        row.querySelector('.invoice-city').value                 = customer.city || '';
        row.querySelector('.invoice-province').value             = customer.province || '';
        row.querySelector('.invoice-codice-destinatario').value  = customer.codice_destinatario || '';
        row.querySelector('.invoice-pec-destinatario').value     = customer.pec_destinatario || '';

        // Show/hide company fields
        const isCompany = customer.user_type === 'company' || customer.user_type === 'public_company';
        row.querySelectorAll('.invoice-company-fields').forEach(el => {
            el.style.display = isCompany ? 'grid' : 'none';
        });

        // Update search input and hide suggestions
        const searchInput = row.querySelector('.invoice-customer-search');
        if (searchInput) searchInput.value = customer.full_name || '';
        const suggestionsEl = row.querySelector('.invoice-customer-suggestions');
        if (suggestionsEl) { suggestionsEl.style.display = 'none'; suggestionsEl.innerHTML = ''; }
    }

    /**
     * Remove a row manually (doesn't affect the split counter display)
     */
    _removeInvoiceRow(idx) {
        const row = document.querySelector(`.invoice-row[data-row-idx="${idx}"]`);
        if (row) row.remove();
        this._updateInvoiceTotals();
    }

    /**
     * Recalculate remaining amount
     */
    _updateInvoiceTotals() {
        if (!this.currentTable || !this.currentTable.order) return;

        const total    = parseFloat(this.currentTable.order.total_amount) || 0;
        let   invoiced = 0;
        document.querySelectorAll('.invoice-amount').forEach(input => {
            invoiced += parseFloat(input.value) || 0;
        });

        const remaining = Math.max(0, parseFloat((total - invoiced).toFixed(2)));

        const remainingEl      = document.getElementById('invoiceRemainingDisplay');
        const remainingLabel   = document.getElementById('invoiceRemainingLabel');
        const remainingSection = document.getElementById('invoiceRemainingSection');

        if (remainingEl) {
            remainingEl.textContent  = `€${remaining.toFixed(2)}`;
            remainingEl.style.color  = remaining > 0.01 ? '#dc3545' : '#28a745';
        }
        if (remainingLabel)   remainingLabel.textContent                        = `€${remaining.toFixed(2)}`;
        if (remainingSection) remainingSection.style.display = remaining > 0.01 ? 'block' : 'none';
    }

    /**
     * Submit invoice payment
     */
    async submitInvoicePayment() {
        if (!this.currentTable) return;

        // Collect invoice rows
        const rows = document.querySelectorAll('.invoice-row');
        if (rows.length === 0) {
            this.showNotification('Aggiungi almeno un ospite da fatturare', 'error');
            return;
        }

        // ── Validate all rows before proceeding ──────────────────────────────
        const invoices = [];
        let valid = true;
        let firstErrorField = null;
        rows.forEach(row => {
            // Clear previous error highlights
            row.querySelectorAll('.invoice-field-error').forEach(el => el.classList.remove('invoice-field-error'));

            const amount = parseFloat(row.querySelector('.invoice-amount').value);
            if (isNaN(amount) || amount <= 0) { valid = false; row.querySelector('.invoice-amount').classList.add('invoice-field-error'); }

            const userType     = row.querySelector('.invoice-user-type')?.value || 'private';
            const customerName = row.querySelector('.invoice-customer-name')?.value?.trim() || '';
            const fiscalCode   = row.querySelector('.invoice-fiscal-code')?.value?.trim() || '';
            const vatNumber    = row.querySelector('.invoice-vat-number')?.value?.trim() || '';

            // Nome / Ragione sociale is always required
            if (!customerName) {
                valid = false;
                const f = row.querySelector('.invoice-customer-name');
                f.classList.add('invoice-field-error');
                if (!firstErrorField) firstErrorField = f;
            }

            // Private: CF required. Company/PA: P.IVA required
            if (userType === 'private') {
                if (!fiscalCode) {
                    valid = false;
                    const f = row.querySelector('.invoice-fiscal-code');
                    f.classList.add('invoice-field-error');
                    if (!firstErrorField) firstErrorField = f;
                }
            } else {
                if (!vatNumber) {
                    valid = false;
                    const f = row.querySelector('.invoice-vat-number');
                    f.classList.add('invoice-field-error');
                    if (!firstErrorField) firstErrorField = f;
                }
                // Address fields required for company/PA
                const address  = row.querySelector('.invoice-address')?.value?.trim() || '';
                const zipCode  = row.querySelector('.invoice-zip-code')?.value?.trim() || '';
                const city     = row.querySelector('.invoice-city')?.value?.trim() || '';
                const province = row.querySelector('.invoice-province')?.value?.trim() || '';
                [{v: address, s: '.invoice-address'}, {v: zipCode, s: '.invoice-zip-code'}, {v: city, s: '.invoice-city'}, {v: province, s: '.invoice-province'}].forEach(({v, s}) => {
                    if (!v) {
                        valid = false;
                        const f = row.querySelector(s);
                        f.classList.add('invoice-field-error');
                        if (!firstErrorField) firstErrorField = f;
                    }
                });
            }

            invoices.push({
                amount:                       amount,
                description:                  row.querySelector('.invoice-description')?.value                || 'Pasto completo',
                user_type:                    userType,
                customer_name:                customerName || null,
                customer_fiscal_code:         fiscalCode || null,
                customer_vat_number:          vatNumber || null,
                customer_address:             row.querySelector('.invoice-address')?.value                    || null,
                customer_zip_code:            row.querySelector('.invoice-zip-code')?.value                   || null,
                customer_city:                row.querySelector('.invoice-city')?.value                       || null,
                customer_province:            row.querySelector('.invoice-province')?.value                   || null,
                customer_codice_destinatario: row.querySelector('.invoice-codice-destinatario')?.value        || null,
                customer_pec_destinatario:    row.querySelector('.invoice-pec-destinatario')?.value           || null,
                customer_id:                  row.querySelector('.invoice-customer-id')?.value                || null,
                save_customer:                row.querySelector('.invoice-save-customer')?.checked            || false,
            });
        });

        if (!valid) {
            this.showNotification('Compila tutti i campi obbligatori per ogni fattura', 'error');
            if (firstErrorField) firstErrorField.focus();
            return;
        }

        const total = parseFloat(this.currentTable.order.total_amount) || 0;
        const invoiced = invoices.reduce((s, r) => s + r.amount, 0);
        const remaining = Math.max(0, total - invoiced);
        const remainingMethod = document.querySelector('input[name="remainingMethod"]:checked')?.value || 'pos';

        // Request auth
        let auth;
        try {
            auth = await operatorAuthManager.requestAuth();
            if (!auth) return;
        } catch (error) {
            console.log('Authentication cancelled');
            return;
        }

        // ── Permission check: invoice_payment ────────────────────────────────
        const perms = auth.permissions ?? [];
        if (!perms.includes('invoice_payment')) {
            this.showNotification('Non hai il permesso di emettere fatture', 'error');
            return;
        }

        this.closeInvoiceModal();

        try {
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/pay-invoice`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token
                },
                body: JSON.stringify({
                    invoices: invoices,
                    remaining_amount: remaining,
                    remaining_method: remaining > 0.01 ? remainingMethod : null,
                    payment_method: this._pendingInvoicePaymentMethod || 'fattura_pos',
                })
            });

            const result = await response.json();

            if (result.success) {
                const ficInfo = result.data.fic_sent > 0 ? ` — ${result.data.fic_sent} fattura/e FIC inviata/e` : '';
                this.showNotification(`Incassato: €${parseFloat(result.data.total_paid).toFixed(2)}${ficInfo}`);
                this._afterPaymentSuccess();
            } else {
                this.showNotification(result.message || 'Errore nel pagamento con fattura', 'error');
            }
        } catch (error) {
            console.error('Error submitting invoice payment:', error);
            this.showNotification('Errore nel pagamento con fattura', 'error');
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
     * Hide the modify overlay (without side effects)
     */
    _hideModifyOverlay() {
        const overlay = document.getElementById('modifyOrderOverlay');
        if (overlay) overlay.style.display = 'none';
        this.temporaryCart = [];
        this.updateCartDisplay();
        this.resetDiscount();
    }

    /**
     * Close modify overlay: if changes exist, ask auth then submit; else close silently
     */
    async closeModifyOverlay({ skipPrint = false } = {}) {
        // Banco: empty order can be cancelled; order with items must be paid
        if (this.currentTable?.table?.is_banco) {
            const hasItems = (this.modifySession.items || []).some(i => !(i.segue && !i.dish_id));
            if (hasItems) {
                this.showNotification('Completare il pagamento per chiudere il banco', 'error');
                return;
            }
            // Cancel the empty banco order
            try {
                const token = this.modifySession.token;
                const orderId = this.currentTable.order?.id;
                if (orderId && token) {
                    await fetch(`/api/banco/${orderId}/cancel`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            'X-Operator-Token': token,
                        },
                    });
                }
            } catch (e) {
                console.error('Error cancelling empty banco order:', e);
            }
            this._hideModifyOverlay();
            this.modifySession.active = false;
            this.currentTable = null;
            this.loadTables();
            return;
        }

        if (this._autoconsumoInProgress) {
            this.showNotification('Completare o annullare prima la procedura di autoconsumo', 'error');
            return;
        }

        const hasChanges =
            this.modifySession.pendingAdd.length > 0 ||
            this.modifySession.pendingRemove.length > 0 ||
            Object.keys(this.modifySession.pendingUpdate).length > 0 ||
            (this.modifySession.pendingDishChange || []).length > 0;

        if (!hasChanges) {
            this._hideModifyOverlay();
            this.modifySession.active = false;
            return;
        }

        // ── Check permission: take_orders ──────────────────────────────────────
        const sessionPerms = this.modifySession.permissions ?? [];
        if (!sessionPerms.includes('take_orders')) {
            console.log('❌ BLOCKED (closeModifyOverlay): Missing take_orders permission');
            this.showNotification('Non hai il permesso di prendere comande', 'error');
            return;
        }
        console.log('✅ ALLOWED (closeModifyOverlay): take_orders permission granted');

        // Usa il token già acquisito all'apertura della sessione (o aggiornato da Comunica).
        // Non si chiede nuova password: l'autenticazione è già avvenuta.
        const token = this.modifySession.token;
        if (!token) {
            this.showNotification('Sessione non valida, riautenticarsi', 'error');
            return;
        }

        try {
            await this._submitSession(token, { skipPrint });
        } catch (error) {
            console.error('Error submitting session:', error);
            this.showNotification('Errore nel salvataggio delle modifiche', 'error');
            return;
        }

        this._hideModifyOverlay();
        this.modifySession.active = false;
        await this.loadTables();
    }

    /**
     * Submit all pending changes but keep the overlay open.
     * Used e.g. in banco mode to persist items before preconto/incasso.
     */
    async submitSessionKeepOpen() {
        if (!this.currentTable) return;

        if (this._autoconsumoInProgress) {
            this.showNotification('Completare o annullare prima la procedura di autoconsumo', 'error');
            return;
        }

        const hasChanges =
            this.modifySession.pendingAdd.length > 0 ||
            this.modifySession.pendingRemove.length > 0 ||
            Object.keys(this.modifySession.pendingUpdate).length > 0 ||
            (this.modifySession.pendingDishChange || []).length > 0;

        if (!hasChanges) {
            this.showNotification('Nessuna modifica da inviare', 'info');
            return;
        }

        const sessionPerms = this.modifySession.permissions ?? [];
        if (!sessionPerms.includes('take_orders')) {
            this.showNotification('Non hai il permesso di prendere comande', 'error');
            return;
        }

        const token = this.modifySession.token;
        if (!token) {
            this.showNotification('Sessione non valida, riautenticarsi', 'error');
            return;
        }

        try {
            await this._submitSession(token);
        } catch (error) {
            console.error('Error submitting session:', error);
            this.showNotification('Errore nel salvataggio delle modifiche', 'error');
            return;
        }

        // Refresh table/order data and rebuild session from persisted state — keep overlay open
        const orderId = this.currentTable?.order?.id;
        const tableId = this.currentTable?.table?.id;
        try {
            const resp = this.currentTable?.table?.is_banco && orderId
                ? await fetch(`/api/order/${orderId}`)
                : await fetch(`${this.apiBase}/${tableId}`);
            const res = await resp.json();
            if (res.success) {
                this.currentTable = res.data;
                this._initSessionFromOrder(this.currentTable.order);
                this.updateModifyReceiptItems?.();
            }
        } catch (e) {
            console.error('Error refreshing order after submit:', e);
        }

        await this.loadTables();
        this.showNotification('Ordine inviato', 'success');
    }

    /**
     * Submit all pending changes for a single existing item immediately.
     * Clears the item from pendingUpdate and pendingDishChange after success.
     */
    async _submitItemEdit(itemId, token) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const tableId = this.currentTable.table.id;
        const updates = this.modifySession.pendingUpdate[itemId] || {};
        const updatedItemIds = [];

        // Dish change for this item (if any)
        const dishChange = (this.modifySession.pendingDishChange || []).find(c => c.itemId === itemId);
        if (dishChange) {
            const resp = await fetch(`${this.apiBase}/items/${itemId}/dish`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Operator-Token': token },
                body: JSON.stringify({ dish_id: dishChange.newDish.id }),
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.message || 'Errore cambio piatto');
            }
            // printDishChange is handled server-side by updateItemDish
            this.modifySession.pendingDishChange = this.modifySession.pendingDishChange.filter(c => c.itemId !== itemId);
        }

        if (updates.quantity !== undefined) {
            const resp = await fetch(`${this.apiBase}/items/${itemId}/quantity`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Operator-Token': token },
                body: JSON.stringify({ quantity: updates.quantity, skip_print: true }),
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.message || 'Errore aggiornamento quantità');
            }
            updatedItemIds.push(itemId);
        }

        if (updates.unit_price !== undefined) {
            const resp = await fetch(`${this.apiBase}/items/${itemId}/price`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Operator-Token': token },
                body: JSON.stringify({ unit_price: updates.unit_price, motivation: updates.price_motivation, skip_print: true }),
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.message || 'Errore aggiornamento prezzo');
            }
            // Price-only change: no print
        }

        if (updates._detailsChanged) {
            const resp = await fetch(`${this.apiBase}/items/${itemId}/details`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Operator-Token': token },
                body: JSON.stringify({ notes: updates.notes, extras: updates.extras, removals: updates.removals }),
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.message || 'Errore aggiornamento dettagli');
            }
            if (!updatedItemIds.includes(itemId)) updatedItemIds.push(itemId);
        }

        // Print to kitchen if there are printable changes
        if (updatedItemIds.length > 0) {
            await fetch(`${this.apiBase}/${tableId}/print-session`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ new_item_ids: [], updated_item_ids: updatedItemIds, operator_token: token }),
            });
        }

        // Clear submitted changes
        delete this.modifySession.pendingUpdate[itemId];
    }

    /**
     * Submit all pending session changes to the backend, then trigger a single print
     */
    async _submitSession(token, { skipPrint = false } = {}) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const tableId = this.currentTable.table.id;
        const updatedItemIds = [];

        // 0. Dish changes
        for (const change of (this.modifySession.pendingDishChange || [])) {
            const resp = await fetch(`${this.apiBase}/items/${change.itemId}/dish`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Operator-Token': token,
                },
                body: JSON.stringify({ dish_id: change.newDish.id }),
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.message || `Errore cambio piatto (${resp.status})`);
            }
        }

        // 1. Remove items
        for (const removal of this.modifySession.pendingRemove) {
            const resp = await fetch(`${this.apiBase}/items/${removal.id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Operator-Token': removal.authToken ?? token,
                },
                body: JSON.stringify({ reason: removal.reason }),
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.message || `Errore nella rimozione (${resp.status})`);
            }
        }

        // 2. Update quantities and prices
        for (const [itemIdStr, updates] of Object.entries(this.modifySession.pendingUpdate)) {
            const itemId = parseInt(itemIdStr);
            if (updates.quantity !== undefined) {
                const resp = await fetch(`${this.apiBase}/items/${itemId}/quantity`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Operator-Token': token,
                    },
                    body: JSON.stringify({ quantity: updates.quantity, skip_print: true }),
                });
                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.message || `Errore aggiornamento quantità (${resp.status})`);
                }
                // Quantity changes need to be printed
                if (!updatedItemIds.includes(itemId)) updatedItemIds.push(itemId);
            }
            if (updates.unit_price !== undefined) {
                const resp = await fetch(`${this.apiBase}/items/${itemId}/price`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Operator-Token': token,
                    },
                    body: JSON.stringify({
                        unit_price: updates.unit_price,
                        motivation: updates.price_motivation,
                        skip_print: true,
                    }),
                });
                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.message || `Errore aggiornamento prezzo (${resp.status})`);
                }
                // Price-only changes do NOT trigger a print — omit from updatedItemIds
            }
            if (updates._detailsChanged) {
                const resp = await fetch(`${this.apiBase}/items/${itemId}/details`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Operator-Token': token,
                    },
                    body: JSON.stringify({
                        notes:    updates.notes    ?? null,
                        extras:   updates.extras   ?? null,
                        removals: updates.removals ?? null,
                    }),
                });
                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.message || `Errore aggiornamento dettagli (${resp.status})`);
                }
                // Details changes DO trigger a print
                if (!updatedItemIds.includes(itemId)) updatedItemIds.push(itemId);
            }
        }

        // 3. Add new items
        let newItemIds = [];
        if (this.modifySession.pendingAdd.length > 0) {
            const items = this.modifySession.pendingAdd.map(item => ({
                dish_id: item.dish_id,
                quantity: item.quantity,
                notes: item.notes,
                segue: item.segue || false,
                custom_price: item.custom_price || null,
                extras: item.extras,
                removals: item.removals,
            }));

            const resp = await fetch(`${this.apiBase}/${tableId}/items-multiple`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ items, operator_token: token, skip_print: true }),
            });
            const result = await resp.json();
            if (!result.success) {
                throw new Error(result.message || 'Errore nell\'aggiunta dei prodotti');
            }
            if (result.data && result.data.item_ids) {
                newItemIds = result.data.item_ids;
            }
        }

        // 4. Print all changes at once
        if (!skipPrint && (newItemIds.length > 0 || updatedItemIds.length > 0)) {
            await fetch(`${this.apiBase}/${tableId}/print-session`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    new_item_ids: newItemIds,
                    updated_item_ids: updatedItemIds,
                    operator_token: token,
                }),
            });
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

        const itemsContainer = document.getElementById('itemsSelectContainer');
        if (itemsContainer) itemsContainer.style.display = 'none';

        const splitCountInput = document.getElementById('splitCount');
        if (splitCountInput) splitCountInput.value = 2;

        this.updateSplitPreview();

        // Pre-populate discount from table-level authorized discount (if any)
        const discountRow = document.querySelector('.preconto-discount-row');
        const precontoDiscountWrap = document.getElementById('precontoDiscountInputWrap');
        const precontoDiscountSym  = document.getElementById('precontoDiscountSymbol');
        const precontoDiscountAmt  = document.getElementById('preconto_discount_amount');
        if (this._authorizedDiscount) {
            const typeRadio = document.querySelector(`input[name="precontoDiscountType"][value="${this._authorizedDiscount.type}"]`);
            if (typeRadio) typeRadio.checked = true;
            if (precontoDiscountAmt) precontoDiscountAmt.value = this._authorizedDiscount.value;
            if (precontoDiscountWrap) precontoDiscountWrap.style.display = 'flex';
            if (precontoDiscountSym) precontoDiscountSym.textContent = this._authorizedDiscount.type === 'percent' ? '%' : '€';
        } else {
            const noneRadio = document.querySelector('input[name="precontoDiscountType"][value="none"]');
            if (noneRadio) noneRadio.checked = true;
            if (precontoDiscountAmt) precontoDiscountAmt.value = 0;
            if (precontoDiscountWrap) precontoDiscountWrap.style.display = 'none';
        }
        // Discount row is only shown for full bill (default selection)
        if (discountRow) discountRow.style.display = '';

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
                const itemsContainer = document.getElementById('itemsSelectContainer');
                const partialTotalRow = document.getElementById('precontoPartialTotalRow');
                splitContainer.style.display = this.value === 'split' ? 'block' : 'none';
                if (this.value === 'split') self.updateSplitPreview();
                itemsContainer.style.display = this.value === 'items' ? 'block' : 'none';
                if (this.value === 'items') self._renderPrecontoItemsList();
                // Show partial total row for full/split (items has its own via _updatePrecontoPartialTotal)
                if (partialTotalRow) partialTotalRow.style.display = this.value !== 'items' ? 'block' : 'none';
                if (this.value !== 'items') self._updateGlobalTotal();
                // Discount only allowed on full bill
                const discountRow = document.querySelector('.preconto-discount-row');
                if (discountRow) discountRow.style.display = this.value === 'full' ? '' : 'none';
            };
        });

        // Split count controls
        const decreaseBtn = document.getElementById('decreaseSplit');
        const increaseBtn = document.getElementById('increaseSplit');
        const splitInput = document.getElementById('splitCount');

        if (decreaseBtn) {
            decreaseBtn.onclick = () => {
                const current = parseInt(splitInput.value) || 2;
                if (current > 2) { splitInput.value = current - 1; this.updateSplitPreview(); }
            };
        }
        if (increaseBtn) {
            increaseBtn.onclick = () => {
                const current = parseInt(splitInput.value) || 2;
                if (current < 20) { splitInput.value = current + 1; this.updateSplitPreview(); }
            };
        }
        if (splitInput) splitInput.oninput = () => this.updateSplitPreview();

        // Select all / deselect all
        const selectAllBtn = document.getElementById('precontoSelectAll');
        const deselectAllBtn = document.getElementById('precontoDeselectAll');
        if (selectAllBtn) selectAllBtn.onclick = () => {
            document.querySelectorAll('#precontoItemsList .preconto-item-check').forEach(cb => {
                cb.checked = true;
                cb.closest('.preconto-item-row').classList.add('selected');
            });
            this._updatePrecontoPartialTotal();
        };
        if (deselectAllBtn) deselectAllBtn.onclick = () => {
            document.querySelectorAll('#precontoItemsList .preconto-item-check').forEach(cb => {
                cb.checked = false;
                cb.closest('.preconto-item-row').classList.remove('selected');
            });
            this._updatePrecontoPartialTotal();
        };

        // Covers input
        const coversInput = document.getElementById('preconto_covers');
        if (coversInput) coversInput.oninput = () => this._updatePrecontoPartialTotal();

        // Discount type radios
        document.querySelectorAll('input[name="precontoDiscountType"]').forEach(radio => {
            radio.onchange = () => {
                const wrap = document.getElementById('precontoDiscountInputWrap');
                const sym  = document.getElementById('precontoDiscountSymbol');
                const val  = document.querySelector('input[name="precontoDiscountType"]:checked')?.value;
                if (val === 'none') {
                    wrap.style.display = 'none';
                    document.getElementById('preconto_discount_amount').value = 0;
                } else {
                    wrap.style.display = 'flex';
                    if (sym) sym.textContent = val === 'percent' ? '%' : '€';
                }
                this._updatePrecontoPartialTotal();
                this._updateGlobalTotal();
                this.updateSplitPreview();
            };
        });

        // Discount amount input
        const discountInput = document.getElementById('preconto_discount_amount');
        if (discountInput) discountInput.oninput = () => {
            this._updatePrecontoPartialTotal();
            this._updateGlobalTotal();
            this.updateSplitPreview();
        };

        // Close buttons
        const closeBtn = document.getElementById('closePrecontoModal');
        const cancelBtn = document.getElementById('cancelPreconto');
        if (closeBtn) closeBtn.onclick = () => this.closePrecontoModal();
        if (cancelBtn) cancelBtn.onclick = () => this.closePrecontoModal();

        // Confirm button
        const confirmBtn = document.getElementById('confirmPreconto');
        if (confirmBtn) confirmBtn.onclick = () => this.printPreconto();
    }

    async _renderPrecontoItemsList() {
        const listEl = document.getElementById('precontoItemsList');
        if (!listEl) return;

        const totalCovers = parseInt(this.currentTable?.order?.covers ?? 0);

        // Fetch existing pending splits — track qty already assigned per item
        let assignedQtyMap = {}; // order_item_id => total qty in pending splits
        let assignedCovers = 0;
        try {
            const resp = await fetch(`${this.apiBase}/${this.currentTable.table.id}/preconto-splits`, {
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
            });
            const data = await resp.json();
            if (data.success) {
                data.data.splits
                    .filter(s => s.status === 'pending')
                    .forEach(s => {
                        (s.items || []).forEach(i => {
                            const id = i.order_item_id;
                            assignedQtyMap[id] = (assignedQtyMap[id] || 0) + (parseInt(i.quantity) || 0);
                        });
                        assignedCovers += parseInt(s.covers || 0);
                    });
            }
        } catch (e) { /* ignore */ }

        const remainingCovers = Math.max(0, totalCovers - assignedCovers);

        // Update covers input
        const coversInput = document.getElementById('preconto_covers');
        const coversRow = document.querySelector('.preconto-covers-row');
        if (coversInput) {
            coversInput.max = remainingCovers;
            coversInput.value = remainingCovers;
            coversInput.disabled = remainingCovers === 0;
        }
        if (coversRow) coversRow.style.display = totalCovers > 0 ? 'flex' : 'none';

        const coversInfoEl = document.getElementById('precontoCoversInfo');
        if (coversInfoEl && totalCovers > 0) {
            coversInfoEl.textContent = assignedCovers > 0
                ? `(${assignedCovers} già assegnati, ${remainingCovers} disponibili)`
                : `(${totalCovers} coperti totali)`;
        }

        const items = this.modifySession?.items?.filter(i => i.id && i.id > 0) ?? [];

        // Compute available qty per item (full qty minus already in pending splits)
        const availableItems = items.map(i => ({
            ...i,
            _available: Math.max(0, (i.quantity || 1) - (assignedQtyMap[i.id] || 0)),
            _assigned: assignedQtyMap[i.id] || 0,
        })).filter(i => i._available > 0);

        if (availableItems.length === 0) {
            const msg = items.length > 0
                ? 'Tutti i piatti sono già stati assegnati a un preconto parziale'
                : 'Nessun piatto nell\'ordine';
            listEl.innerHTML = `<div style="padding:20px;text-align:center;color:#6c757d;">${msg}</div>`;
            this._updatePrecontoPartialTotal();
            return;
        }

        listEl.innerHTML = availableItems.filter(item => !item.segue).map(item => {
            const name = item.dish_name || item.name || 'N/D';
            const avail = item._available;
            const unitPrice = parseFloat(item.unit_price || 0);
            const subtotal = (0).toFixed(2);
            const alreadyBadge = item._assigned > 0
                ? `<span style="font-size:0.72rem;color:#fd7e14;margin-left:4px;">(${item._assigned} già in preconto)</span>`
                : '';
            return `<div class="preconto-item-row" data-item-id="${item.id}">
                <span class="preconto-item-name">${name}${alreadyBadge}</span>
                <div style="display: flex; flex-direction: column">
                    <div class="preconto-qty-ctrl">
                        <button type="button" class="pqi-dec" data-item-id="${item.id}">−</button>
                        <input type="number" class="preconto-item-qty-input"
                               value="0" min="0" max="${avail}"
                               data-item-id="${item.id}" data-unit-price="${unitPrice}" data-max="${avail}">
                        <button type="button" class="pqi-inc" data-item-id="${item.id}">+</button>
                        <span class="pqi-max">/ ${avail}</span>
                    </div>
                    <span class="preconto-item-price" id="pcp_${item.id}">€${subtotal}</span>
                </div>
            </div>`;
        }).join('');

        const self = this;
        const onQtyChange = (input) => {
            const itemId = input.dataset.itemId;
            const max = parseInt(input.dataset.max) || 0;
            let qty = Math.max(0, Math.min(parseInt(input.value) || 0, max));
            input.value = qty;
            const unitPrice = parseFloat(input.dataset.unitPrice) || 0;
            const priceEl = document.getElementById(`pcp_${itemId}`);
            if (priceEl) priceEl.textContent = `€${(unitPrice * qty).toFixed(2)}`;
            const row = input.closest('.preconto-item-row');
            if (row) row.classList.toggle('selected', qty > 0);
            self._updatePrecontoPartialTotal();
        };

        listEl.querySelectorAll('.pqi-dec').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = listEl.querySelector(`.preconto-item-qty-input[data-item-id="${btn.dataset.itemId}"]`);
                if (input && parseInt(input.value) > 0) { input.value = parseInt(input.value) - 1; onQtyChange(input); }
            });
        });
        listEl.querySelectorAll('.pqi-inc').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = listEl.querySelector(`.preconto-item-qty-input[data-item-id="${btn.dataset.itemId}"]`);
                const max = parseInt(input?.dataset.max) || 0;
                if (input && parseInt(input.value) < max) { input.value = parseInt(input.value) + 1; onQtyChange(input); }
            });
        });
        listEl.querySelectorAll('.preconto-item-qty-input').forEach(input => {
            input.addEventListener('input', () => onQtyChange(input));
        });

        // Select all / Deselect all
        const selectAllBtn = document.getElementById('precontoSelectAll');
        const deselectAllBtn = document.getElementById('precontoDeselectAll');
        if (selectAllBtn) selectAllBtn.onclick = () => {
            listEl.querySelectorAll('.preconto-item-qty-input').forEach(input => {
                input.value = input.dataset.max; onQtyChange(input);
            });
        };
        if (deselectAllBtn) deselectAllBtn.onclick = () => {
            listEl.querySelectorAll('.preconto-item-qty-input').forEach(input => {
                input.value = 0; onQtyChange(input);
            });
        };

        this._updatePrecontoPartialTotal();
    }

    _updatePrecontoPartialTotal() {
        let total = 0;
        document.querySelectorAll('#precontoItemsList .preconto-item-qty-input').forEach(input => {
            const qty = parseInt(input.value) || 0;
            const unitPrice = parseFloat(input.dataset.unitPrice) || 0;
            total += qty * unitPrice;
        });

        // Add cover charge
        const covers = parseInt(document.getElementById('preconto_covers')?.value || 0);
        if (covers > 0 && this.currentTable?.order) {
            const coverPerPerson = parseFloat(this.currentTable.order.cover_charge_per_person || 0);
            total += coverPerPerson * covers;
        }

        total = this._applyPrecontoDiscount(total);

        const el = document.getElementById('precontoPartialTotal');
        if (el) el.textContent = `€${total.toFixed(2)}`;
        const row = document.getElementById('precontoPartialTotalRow');
        if (row) row.style.display = 'block';
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
        const total = this._applyPrecontoDiscount(parseFloat(this.currentTable.order.total_amount) || 0);
        const perPerson = total / splitCount;

        perPersonEl.textContent = `€${perPerson.toFixed(2)}`;
    }

    _applyPrecontoDiscount(total) {
        const discountType   = document.querySelector('input[name="precontoDiscountType"]:checked')?.value || 'none';
        const discountAmount = parseFloat(document.getElementById('preconto_discount_amount')?.value || 0);
        if (discountType === 'value' && discountAmount > 0) {
            return Math.max(0, total - Math.min(discountAmount, total));
        } else if (discountType === 'percent' && discountAmount > 0) {
            return Math.max(0, total - Math.round(total * Math.min(discountAmount, 100) / 100 * 100) / 100);
        }
        return total;
    }

    _updateGlobalTotal() {
        if (!this.currentTable?.order) return;
        const total = this._applyPrecontoDiscount(parseFloat(this.currentTable.order.total_amount) || 0);
        const el = document.getElementById('precontoPartialTotal');
        if (el) el.textContent = `€${total.toFixed(2)}`;
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

        const precontoType = document.querySelector('input[name="precontoType"]:checked')?.value || 'full';

        // Validate items selection
        if (precontoType === 'items') {
            const inputs = document.querySelectorAll('#precontoItemsList .preconto-item-qty-input');
            const hasSelection = Array.from(inputs).some(i => parseInt(i.value) > 0);
            if (!hasSelection) {
                this.showNotification('Seleziona almeno un piatto per il preconto parziale', 'error');
                return;
            }
            const coversInput = document.getElementById('preconto_covers');
            if (coversInput && parseInt(coversInput.value) > parseInt(coversInput.max || 0)) {
                this.showNotification(`Coperti non validi: massimo ${coversInput.max} disponibili`, 'error');
                return;
            }
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

        let body = { type: precontoType };
        if (precontoType === 'split') {
            body.split_count = parseInt(document.getElementById('splitCount').value) || null;
        } else if (precontoType === 'items') {
            const selectedItems = [];
            document.querySelectorAll('#precontoItemsList .preconto-item-qty-input').forEach(input => {
                const qty = parseInt(input.value) || 0;
                if (qty > 0) {
                    selectedItems.push({
                        order_item_id: parseInt(input.dataset.itemId),
                        quantity: qty,
                    });
                }
            });
            body.items = selectedItems;
            body.covers = parseInt(document.getElementById('preconto_covers')?.value || 0);
        }

        // Always include discount
        body.discount_type   = document.querySelector('input[name="precontoDiscountType"]:checked')?.value || 'none';
        body.discount_amount = parseFloat(document.getElementById('preconto_discount_amount')?.value || 0);

        // For banco (multi-session) the table has multiple open orders: pin the exact one.
        if (this.currentTable?.order?.id) {
            body.order_id = this.currentTable.order.id;
        }

        try {
            console.log(`${this.apiBase}/${this.currentTable.table.id}/preconto`)
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/preconto`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token
                },
                body: JSON.stringify(body)
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message || 'PreConto stampato con successo', 'success');
                await this.loadTables();

                if (precontoType === 'items') {
                    // Stay in modal: reload item list with remaining (non-assigned) items
                    // Reset discount
                    const noneRadio = document.querySelector('input[name="precontoDiscountType"][value="none"]');
                    if (noneRadio) { noneRadio.checked = true; noneRadio.dispatchEvent(new Event('change')); }
                    document.getElementById('preconto_discount_amount').value = 0;
                    await this._renderPrecontoItemsList();
                } else {
                    this.closePrecontoModal();
                }
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
        const timers = document.querySelectorAll('.table-timer');
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
     * Start interval to update timers every minute and auto-refresh tables every 30 seconds.
     * The table refresh is skipped while the modify overlay is open to avoid conflicts.
     */
    startTimerUpdates() {
        // Update immediately
        this.updateTimers();

        // Update timers every 60 seconds
        setInterval(() => {
            this.updateTimers();
        }, 60000);

        // Auto-refresh table list every 30 seconds (skip if overlay is open)
        setInterval(() => {
            const overlay = document.getElementById('modifyOrderOverlay');
            if (!overlay || overlay.style.display === 'none' || overlay.style.display === '') {
                this.loadTables();
            }
        }, 30000);
    }

    /**
     * @deprecated — cart flow removed; use addProductToSession() instead
     */
    addToCart() {
        if (!this.currentTable || !this.currentProduct) return;

        const quantityElement = this.getElement('productQuantity');
        const notesElement = this.getElement('productNotes');
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
            segue: false, // segue is set from the list separator, not from the modal
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
                // Riutilizza il token per "Chiudi e invia" così non serve reinserire la password
                if (this.modifySession.active) {
                    this.modifySession.token = auth.token;
                    this.modifySession.permissions = auth.permissions ?? this.modifySession.permissions;
                }
                this.closeComunicaModal();
            } else {
                this.showNotification(result.message || 'Errore nell\'invio della comunicazione', 'error');
            }
        } catch (error) {
            console.error('Error sending comunica:', error);
            this.showNotification('Errore nell\'invio della comunicazione', 'error');
        }
    }

    /**
     * Reprint all items of the current order grouped by printer
     */
    async reprintOrder() {
        if (!this.currentTable?.order) {
            this.showNotification('Nessun ordine attivo', 'error');
            return;
        }

        const auth = await operatorAuthManager.requestAuth();
        if (!auth) return;

        try {
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/reprint`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token,
                },
            });

            const result = await response.json();
            if (result.success) {
                this.showNotification(result.message || 'Ordine ristampato', 'success');
            } else {
                this.showNotification(result.message || 'Errore nella ristampa', 'error');
            }
        } catch (error) {
            console.error('Error reprinting order:', error);
            this.showNotification('Errore nella ristampa', 'error');
        }
    }

    /**
     * Open a NEW banco order (always creates a fresh one)
     */
    async openBanco() {
        try {
            const auth = await operatorAuthManager.requestAuth();
            if (!auth) return;

            const openResp = await fetch('/api/banco/open', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token,
                },
                body: JSON.stringify({ operator_token: auth.token }),
            });
            const openResult = await openResp.json();
            if (!openResult.success) {
                this.showNotification(openResult.message || 'Errore apertura banco', 'error');
                return;
            }

            // Load the new order and open overlay (propagate auth into session)
            await this.selectByOrderId(openResult.data.order_id, auth);
            await this.loadTables();

        } catch (error) {
            console.error('Error opening banco:', error);
            this.showNotification('Errore nel banco', 'error');
        }
    }

    /**
     * Open overlay for a specific order (banco multi-session)
     */
    async selectByOrderId(orderId, auth = null) {
        try {
            const response = await fetch(`/api/order/${orderId}`);
            const result = await response.json();
            if (!result.success) return;
            this.currentTable = result.data;
            if (auth) {
                this.modifySession.token = auth.token;
                this.modifySession.permissions = auth.permissions ?? [];
                this.modifySession.active = true;
                if (this.currentTable.order) {
                    this._initSessionFromOrder(this.currentTable.order);
                }
            }
            this.openModifyOverlay();
        } catch (error) {
            console.error('Error selecting order:', error);
        }
    }

    /**
     * Open move table modal
     */
    openMoveTableModal() {
        if (!this.currentTable?.order) {
            this.showNotification('Seleziona prima un tavolo con un ordine attivo', 'error');
            return;
        }

        const sourceNumber = this.currentTable.table.table_number;
        const sourceId = this.currentTable.table.id;

        document.getElementById('moveSourceTableNumber').textContent = sourceNumber;

        const grid = document.getElementById('moveTableGrid');
        const tables = (this.allTables || []).filter(t => t.id !== sourceId && t.status === 'free');

        grid.innerHTML = tables.length
            ? tables.map(t => `<button class="move-table-btn free" data-table-id="${t.id}" style="
                padding:10px 4px; border:2px solid #28a745;
                background:#f0fff4;
                color:#000; font-weight:700; font-size:1rem; border-radius:4px; cursor:pointer;
            ">${t.table_number}</button>`).join('')
            : '<p style="color:#6c757d; text-align:center; grid-column:1/-1;">Nessun tavolo libero disponibile</p>';

        grid.querySelectorAll('.move-table-btn').forEach(btn => {
            btn.addEventListener('click', () => this.executeMove(parseInt(btn.dataset.tableId)));
        });

        const modal = document.getElementById('moveTableModal');
        modal.style.display = 'flex';
    }

    closeMoveTableModal() {
        document.getElementById('moveTableModal').style.display = 'none';
    }

    /**
     * Execute table move
     */
    async executeMove(destinationTableId) {
        const auth = await operatorAuthManager.requestAuth();
        if (!auth) return;

        this.closeMoveTableModal();

        try {
            const response = await fetch(`${this.apiBase}/${this.currentTable.table.id}/move`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Operator-Token': auth.token,
                },
                body: JSON.stringify({ destination_table_id: destinationTableId }),
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message || 'Tavolo spostato con successo', 'success');
                this.currentTable = null;
                this.modifySession.active = false;
                this._hideModifyOverlay();
                await this.loadTables();
            } else {
                this.showNotification(result.message || 'Errore nello spostamento', 'error');
            }
        } catch (error) {
            console.error('Error moving table:', error);
            this.showNotification('Errore nello spostamento del tavolo', 'error');
        }
    }
}

// Initialize manager when DOM is ready
let tableOrdersManager;
document.addEventListener('DOMContentLoaded', () => {
    const isMobile = window.innerWidth <= 768 || document.getElementById('mainViewMobile') !== null;
    tableOrdersManager = new TableOrdersManager(isMobile);
    if (window._backofficeMode && window._backofficeTableId) {
        tableOrdersManager.initForBackoffice(window._backofficeTableId);
    }
});
