/* ===== MOBILE JAVASCRIPT FOR CARLO V ===== */

$(document).ready(function() {

    // ===== AGGIUNGI TAVOLI =====

    $('#btnAddTableMobile').click(function() {
        const count = prompt('Quanti tavoli vuoi aggiungere?', '1');
        if (count === null) return;

        const tableCount = parseInt(count);
        if (isNaN(tableCount) || tableCount < 1 || tableCount > 50) {
            if (typeof tableOrdersManager !== 'undefined') {
                tableOrdersManager.showNotification('Numero non valido. Inserisci un numero tra 1 e 50', 'error');
            }
            return;
        }

        $.ajax({
            url: '/api/tables/add-batch',
            method: 'POST',
            data: { count: tableCount },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                if (response.success) {
                    if (typeof tableOrdersManager !== 'undefined') {
                        tableOrdersManager.showNotification(response.message, 'success');
                        tableOrdersManager.loadTables();
                    }
                    hapticFeedback([100, 50, 100]);
                } else {
                    if (typeof tableOrdersManager !== 'undefined') {
                        tableOrdersManager.showNotification(response.message || 'Errore nell\'aggiunta dei tavoli', 'error');
                    }
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Errore nell\'aggiunta dei tavoli';
                if (typeof tableOrdersManager !== 'undefined') {
                    tableOrdersManager.showNotification(message, 'error');
                }
            }
        });
    });

    // ===== HAPTIC FEEDBACK HELPER =====

    function hapticFeedback(pattern = [50]) {
        if (navigator.vibrate) {
            navigator.vibrate(pattern);
        }
    }

    // ===== TOUCH OPTIMIZATIONS =====

    // Prevent pull-to-refresh on iOS only when scrolling UP at the top
    let touchStartY = 0;
    document.body.addEventListener('touchstart', function(e) {
        touchStartY = e.touches[0].clientY;
    }, { passive: true });

    document.body.addEventListener('touchmove', function(e) {
        if (e.target.closest('.mobile-modal-body') ||
            e.target.closest('#modifyReceiptItems') ||
            e.target.closest('.dsm-categories-strip') ||
            e.target.closest('.dsm-dishes-list') ||
            e.target.closest('#operationalLogModal')) {
            return;
        }
        const dy = e.touches[0].clientY - touchStartY;
        if (document.body.scrollTop === 0 && dy > 0) {
            e.preventDefault(); // blocca solo pull-to-refresh
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

    $(document).on('click', '.menu-item', function() {
        hapticFeedback([50]);
    });

    // Tasto "+" nella lista piatti mobile: aggiunta immediata senza modal.
    // Usiamo capture per intercettare il click prima che risalga a .menu-item
    // (che aprirebbe il product modal).
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.dsm-dish-add-quick');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        const row = btn.closest('.dsm-dish-row');
        if (!row) return;
        const dish = {
            id: row.dataset.dishId,
            name: row.dataset.item,
            price: parseFloat(row.dataset.price),
        };
        if (typeof tableOrdersManager !== 'undefined' && tableOrdersManager.quickAddDishToSession) {
            tableOrdersManager.quickAddDishToSession(dish);
            hapticFeedback([30]);
        }
    }, true);

    // ===== WELCOME MESSAGE =====

    setTimeout(() => {
        if (typeof tableOrdersManager !== 'undefined') {
            tableOrdersManager.showNotification('Benvenuto!', 'success');
        }
    }, 500);
});

// ===== PRODUCT MODAL: toggle "Note, supplementi e rimozioni" (mobile) =====

(function() {
    const toggleId = 'productExtrasToggle';
    const collapseId = 'productExtrasCollapse';
    const modalId   = 'productModalMobile';

    function setCollapsed(collapsed) {
        const wrap = document.getElementById(collapseId);
        const btn  = document.getElementById(toggleId);
        if (!wrap || !btn) return;
        wrap.classList.toggle('collapsed', collapsed);
        btn.classList.toggle('open', !collapsed);
        btn.setAttribute('aria-expanded', String(!collapsed));
    }

    function hasExistingCustomizations() {
        const notes = document.getElementById('productNotesMobile');
        if (notes && notes.value && notes.value.trim() !== '') return true;
        const wrap = document.getElementById(collapseId);
        if (!wrap) return false;
        return !!wrap.querySelector('input[type="checkbox"]:checked');
    }

    // Toggle handler
    document.addEventListener('click', function(ev) {
        const btn = ev.target.closest('#' + toggleId);
        if (!btn) return;
        const wrap = document.getElementById(collapseId);
        if (!wrap) return;
        const isCollapsed = wrap.classList.contains('collapsed');
        setCollapsed(!isCollapsed);
    });

    // Osserva l'apertura del modal mobile: se già ci sono note/extras/removals
    // valorizzati (caso "modifica voce"), apri l'accordion; altrimenti chiudi.
    function observeModal() {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        const obs = new MutationObserver(() => {
            const isOpen = modal.style.display && modal.style.display !== 'none';
            if (!isOpen) return;
            // Piccolo delay: il JS del manager popola gli input in modo sincrono
            // ma i checkbox extras/removals vengono generati con innerHTML — attendiamo un tick.
            setTimeout(() => setCollapsed(!hasExistingCustomizations()), 0);
        });
        obs.observe(modal, { attributes: true, attributeFilter: ['style'] });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observeModal);
    } else {
        observeModal();
    }
})();

// ===== TAB SWITCHING IN OVERLAY =====

function mobileShowOverlayTab(tab) {
    const ordineTab = document.getElementById('mobileOverlayOrderTab');
    const menuTab   = document.getElementById('mobileOverlayMenuTab');
    const btnOrdine = document.getElementById('mobileTabOrdine');
    const btnMenu   = document.getElementById('mobileTabMenu');

    if (tab === 'ordine') {
        ordineTab.style.display = 'flex';
        menuTab.style.display   = 'none';
        btnOrdine.classList.add('active');
        btnMenu.classList.remove('active');
    } else {
        ordineTab.style.display = 'none';
        menuTab.style.display   = 'flex';
        btnOrdine.classList.remove('active');
        btnMenu.classList.add('active');
    }
}
