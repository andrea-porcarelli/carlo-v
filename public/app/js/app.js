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
    // NOTA: si usa this.dataset invece di $(this).data() per evitare
    // che la cache interna di jQuery restituisca valori stale dopo un
    // aggiornamento morphdom di Livewire.
    $(document).on('click', '.menu-item', function() {
        const dishId = this.dataset.dishId;
        const dishName = this.dataset.item;
        const dishPrice = parseFloat(this.dataset.price);

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

    // Pay bill from modify overlay — show payment method modal (overlay hidden by _afterPaymentSuccess)
    $('#btnModifyPayBill').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.payTable();
        }
    });

    // Clear bill from modify overlay — opens autoconsumo modal
    $('#btnModifyFreeAmount').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.openAutoconsumoModal();
        }
    });

    // Marcia Tavolo from modify overlay
    $('#btnMarciaTavolo').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.marciaTavolo();
        }
    });

    // PreConto from modify overlay
    $('#btnPreconto').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.openPrecontoModal();
        }
    });

    // Chiudi conto (contanti) + apri cassetto
    $('#btnModifyFreeTable').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.chiudiContoContanti();
        }
    });

    // Chiudi tavolo vuoto (nessun piatto) — solo visibile quando non ci sono piatti
    $('#btnChiudiTavolo').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.chiudiTavoloVuoto();
        }
    });

    // Comunica from modify overlay
    $('#btnModifyComunica').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.openComunicaModal();
        }
    });

    // Close Comunica modal
    $('#closeComunicaModal, #cancelComunica').click(function() {
        $('#comunicaModal').fadeOut(300);
    });

    // Confirm Comunica
    $('#confirmComunica').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.sendComunica();
        }
    });

    // Close Comunica modal on overlay click
    $('#comunicaModal').click(function(e) {
        if (e.target === this) {
            $(this).fadeOut(300);
        }
    });

    // Show receipt - delegate to tableOrdersManager
    $('#showReceipt').click(function() {
        if (typeof tableOrdersManager !== 'undefined' && tableOrdersManager.currentTable) {
            $('#receiptOverlay').show();
        } else {
            if (typeof tableOrdersManager !== 'undefined') {
                tableOrdersManager.showNotification('Seleziona prima un tavolo', 'error');
            }
        }
    });

    // Close receipt
    $('#closeReceiptBtn').click(function() {
        $('#receiptOverlay').hide();
    });

    // Pay bill - delegate to tableOrdersManager
    $('#btnPayBill').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.payTable();
        }
    });

    // Clear bill - delegate to tableOrdersManager
    $('#btnFreeAmount').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.freeAmount();
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

    // ── Payment Method Modal ──────────────────────────────────────────────────

    $('#closePaymentMethodModal').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.closePaymentMethodModal();
        }
    });

    // Close payment modal on backdrop click
    $('#paymentMethodModal').click(function(e) {
        if (e.target === this && typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.closePaymentMethodModal();
        }
    });

    // ── Invoice Modal ─────────────────────────────────────────────────────────

    $('#closeInvoiceModal, #cancelInvoiceModal').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.closeInvoiceModal();
        }
    });

    // Split selector
    $('#btnSplitMinus').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager._changeSplit(-1);
        }
    });

    $('#btnSplitPlus').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager._changeSplit(1);
        }
    });

    $('#confirmInvoicePayment').click(function() {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.submitInvoicePayment();
        }
    });

    // Close invoice modal on backdrop click
    $('#invoiceModal').click(function(e) {
        if (e.target === this && typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.closeInvoiceModal();
        }
    });

    // ── Admin Auth Modal (AMMINISTRA / LOG OPERATIVO) ─────────────────────────

    window._adminAuthMode = 'redirect'; // 'redirect' | 'logOperativo'

    function openAdminAuthModal(mode) {
        window._adminAuthMode = mode;
        $('#adminAuthPassword').val('');
        $('#adminAuthError').hide();
        $('#adminAuthModal').css('display', 'flex');
        setTimeout(function() { $('#adminAuthPassword').focus(); }, 100);
    }

    $('#btnAmministra').click(function() { openAdminAuthModal('redirect'); });
    $('#btnLogOperativo').click(function() { openAdminAuthModal('logOperativo'); });

    $('#cancelAdminAuth').click(function() {
        $('#adminAuthModal').hide();
    });

    $('#adminAuthModal').click(function(e) {
        if (e.target === this) $(this).hide();
    });

    $('#adminAuthPassword').on('keydown', function(e) {
        if (e.key === 'Enter') $('#confirmAdminAuth').click();
    });

    $('#confirmAdminAuth').click(async function() {
        const password = $('#adminAuthPassword').val();
        if (!password) {
            $('#adminAuthErrorText').text('Inserisci la password');
            $('#adminAuthError').show();
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true);

        try {
            if (window._adminAuthMode === 'logOperativo') {
                const response = await fetch('/api/admin/verify-pin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    },
                    body: JSON.stringify({ password }),
                });
                const data = await response.json();
                if (data.success) {
                    $('#adminAuthModal').hide();
                    openOperationalLog(password);
                } else {
                    $('#adminAuthErrorText').text(data.message || 'Accesso non autorizzato');
                    $('#adminAuthError').show();
                }
                return;
            }

            const orderId = (typeof tableOrdersManager !== 'undefined' && tableOrdersManager.currentTable?.order?.id)
                ? tableOrdersManager.currentTable.order.id
                : null;

            const response = await fetch('/api/admin/login-redirect', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
                body: JSON.stringify({ password, order_id: orderId }),
            });

            const data = await response.json();

            if (data.success) {
                window.location.href = data.url;
            } else {
                $('#adminAuthErrorText').text(data.message || 'Accesso non autorizzato');
                $('#adminAuthError').show();
            }
        } catch (error) {
            $('#adminAuthErrorText').text('Errore durante l\'autenticazione');
            $('#adminAuthError').show();
        } finally {
            $btn.prop('disabled', false);
        }
    });

    // ── LOG OPERATIVO ─────────────────────────────────────────────────────────

    let _logOpPin = null;

    function openOperationalLog(pin) {
        _logOpPin = pin;
        const today = new Date().toISOString().slice(0, 10);
        $('#logOpDate').val(today);
        $('#operationalLogModal').css('display', 'flex');
        loadOperationalLog(today);
    }

    function closeOperationalLog() {
        _logOpPin = null;
        $('#operationalLogModal').hide();
    }

    $('#closeOperationalLog').click(closeOperationalLog);
    $('#operationalLogModal').click(function(e) {
        if (e.target === this) closeOperationalLog();
    });
    $('#logOpDate').on('change', function() {
        if (_logOpPin) loadOperationalLog($(this).val());
    });

    function fmtMoney(v) {
        return '€' + Number(v || 0).toFixed(2).replace('.', ',');
    }

    async function loadOperationalLog(date) {
        const setLoading = (id) => $('#' + id).html('<div style="color:#888; text-align:center; padding:20px 0;">Caricamento…</div>');
        setLoading('logOpVenduto');
        setLoading('logOpDaIncassare');
        setLoading('logOpCancellati');
        setLoading('logOpModificati');
        $('#logOpVendutoDetail').hide().empty();

        try {
            const response = await fetch('/api/operational-log?date=' + encodeURIComponent(date), {
                headers: { 'X-Admin-Pin': _logOpPin || '', 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            renderVenduto(data.venduto);
            renderDaIncassare(data.daIncassare);
            renderCancellati(data.cancellati);
            renderModificati(data.modificati);
        } catch (e) {
            const err = '<div style="color:#dc3545; text-align:center; padding:20px 0;">Errore nel caricamento</div>';
            $('#logOpVenduto').html(err);
            $('#logOpDaIncassare').html(err);
            $('#logOpCancellati').html(err);
            $('#logOpModificati').html(err);
        }
    }

    function renderVenduto(v) {
        const det = v.dettagli || {};

        const rowMeta = [
            { key: 'contanti',           label: 'Contanti',            color: '#28a745', icon: 'fa-coins' },
            { key: 'pos',                label: 'POS',                 color: '#17a2b8', icon: 'fa-credit-card' },
            { key: null,                 label: 'Scontrino (tot.)',    amount: v.scontrino },
            { key: 'fatture',            label: 'Fatture',             color: '#6f42c1', icon: 'fa-file-invoice' },
            { key: 'omaggi_autoconsumo', label: 'Omaggi / Autoconsumo', color: '#ffc107', icon: 'fa-gift' },
            { key: 'vendite_banco',      label: 'Vendite al banco',   color: '#fd7e14', icon: 'fa-store' },
        ];

        function row(label, amount, detailKey, extraClass) {
            const items = detailKey ? det[detailKey] : null;
            const hasDetail = items && items.length > 0;
            const cls = hasDetail ? 'logop-row-clickable' : '';
            const dataAttr = hasDetail ? `data-detail-key="${detailKey}"` : '';
            return `<div class="logop-row ${extraClass || ''} ${cls}" ${dataAttr}>
                    <span class="label">${label}${hasDetail ? ' <span style="color:#555; font-size:0.75rem;">(' + items.length + ')</span>' : ''}</span>
                    <span class="value">${fmtMoney(amount)}</span>
                </div>`;
        }

        let html = '';
        rowMeta.forEach(m => {
            const amount = m.amount !== undefined ? m.amount : (v[m.key] || 0);
            html += row(m.label, amount, m.key);
        });
        html += row('Totale incassato', v.totale_incassato, null, 'total');

        $('#logOpVenduto').html(html);
        $('#logOpVendutoDetail').hide().empty();

        // Click on a venduto row → show detail card below
        $('#logOpVenduto').off('click', '.logop-row-clickable').on('click', '.logop-row-clickable', function () {
            const key = $(this).data('detail-key');
            const items = det[key];
            const meta = rowMeta.find(m => m.key === key);
            const $container = $('#logOpVendutoDetail');

            // Toggle off if same key is already open
            if ($container.is(':visible') && $container.data('active-key') === key) {
                $container.slideUp(150, function () { $(this).empty(); });
                $(this).removeClass('active');
                return;
            }

            // Remove active from all rows
            $('#logOpVenduto .logop-row-clickable').removeClass('active');
            $(this).addClass('active');

            if (!items || !items.length) {
                $container.slideUp(150, function () { $(this).empty(); });
                return;
            }

            const groupTotal = items.reduce((s, t) => s + (t.amount || 0), 0);
            const tableRows = items.map(t =>
                `<tr>
                    <td><strong style="color:#fff;">${escapeHtml(String(t.table_number))}</strong></td>
                    <td>${t.closed_at || '-'}</td>
                    <td style="text-align:right; font-weight:600; font-variant-numeric:tabular-nums;">${fmtMoney(t.amount)}</td>
                </tr>`
            ).join('');

            const cardHtml = `<div class="logop-card" style="background:#222; border:1px solid #333; border-radius:8px; padding:14px;">
                <h6 style="color:${meta.color || '#28a745'}; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin:0 0 12px 0;">
                    <i class="fas ${meta.icon || 'fa-list'} me-2"></i>Dettaglio ${escapeHtml(meta.label)}
                    <span style="color:#888; font-weight:400; font-size:0.8rem; text-transform:none;">(${items.length})</span>
                </h6>
                <div style="font-size:0.85rem; max-height:220px; overflow-y:auto;">
                    <table class="logop-table">
                        <thead><tr><th>Tavolo</th><th>Chiuso</th><th style="text-align:right;">Importo</th></tr></thead>
                        <tbody>${tableRows}</tbody>
                        <tfoot><tr style="border-top:2px solid ${meta.color || '#28a745'};">
                            <td colspan="2" style="font-weight:700; color:${meta.color || '#28a745'};">Totale</td>
                            <td style="text-align:right; font-weight:700; color:${meta.color || '#28a745'}; font-variant-numeric:tabular-nums;">${fmtMoney(groupTotal)}</td>
                        </tr></tfoot>
                    </table>
                </div>
            </div>`;

            $container.data('active-key', key).html(cardHtml).slideDown(150);
        });
    }

    function renderDaIncassare(d) {
        if (!d.tavoli || d.tavoli.length === 0) {
            $('#logOpDaIncassare').html('<div class="logop-empty">Nessun tavolo aperto</div>');
            return;
        }
        const rows = d.tavoli.map(t =>
            `<div class="logop-row">
                <span class="label">Tavolo <strong style="color:#fff;">${t.table_number}</strong>${t.opened_at ? ' <span style="color:#777; font-size:0.8rem;">apertura ' + t.opened_at + '</span>' : ''}</span>
                <span class="value">${fmtMoney(t.amount)}</span>
            </div>`
        ).join('');
        $('#logOpDaIncassare').html(rows +
            `<div class="logop-row total"><span class="label">Totale</span><span class="value">${fmtMoney(d.totale)}</span></div>`);
    }

    function renderCancellati(list) {
        $('#logOpCancellatiCount').text(list && list.length ? '(' + list.length + ')' : '');
        if (!list || !list.length) {
            $('#logOpCancellati').html('<div class="logop-empty">Nessun articolo cancellato</div>');
            return;
        }
        const rows = list.map(r => `
            <tr>
                <td>${r.time}</td>
                <td>${r.table}</td>
                <td>${escapeHtml(r.dish)}</td>
                <td>${r.qty}</td>
                <td>${r.price ? '€' + r.price : '-'}</td>
                <td>${escapeHtml(r.reason)}</td>
                <td>${escapeHtml(r.operator)}</td>
            </tr>`).join('');
        $('#logOpCancellati').html(`
            <table class="logop-table">
                <thead><tr><th>Ora</th><th>Tavolo</th><th>Piatto</th><th>Qtà</th><th>Prezzo</th><th>Motivo</th><th>Operatore</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>`);
    }

    function renderModificati(list) {
        $('#logOpModificatiCount').text(list && list.length ? '(' + list.length + ')' : '');
        if (!list || !list.length) {
            $('#logOpModificati').html('<div class="logop-empty">Nessuna modifica</div>');
            return;
        }
        const rows = list.map(r => `
            <tr>
                <td>${r.time}</td>
                <td>${r.table}</td>
                <td>${escapeHtml(r.dish)}</td>
                <td>${escapeHtml(r.field)}</td>
                <td style="color:#dc3545;">${escapeHtml(String(r.old))}</td>
                <td style="color:#28a745;">${escapeHtml(String(r.new))}</td>
                <td>${escapeHtml(r.operator)}</td>
            </tr>`).join('');
        $('#logOpModificati').html(`
            <table class="logop-table">
                <thead><tr><th>Ora</th><th>Tavolo</th><th>Piatto</th><th>Campo</th><th>Da</th><th>A</th><th>Operatore</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>`);
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }
});
