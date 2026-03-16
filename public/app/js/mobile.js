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
            e.target.closest('.dsm-dishes-list')) {
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

    // ===== SEGUE TOGGLE =====

    $(document).on('click', '#segueToggleMobile', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const checkbox = $('#productSegueMobile');
        const isChecked = checkbox.prop('checked');
        checkbox.prop('checked', !isChecked);

        const toggle = $(this);
        const switchEl = toggle.find('.segue-switch');
        const handle = toggle.find('.segue-switch-handle');

        if (!isChecked) {
            switchEl.css('background', '#dc3545');
            handle.css('transform', 'translateX(26px)');
            toggle.css('border-color', '#dc3545');
        } else {
            switchEl.css('background', '#ccc');
            handle.css('transform', 'translateX(0)');
            toggle.css('border-color', '#dee2e6');
        }

        if (navigator.vibrate) navigator.vibrate(30);
    });

    // Reset segue toggle when product modal opens
    function resetSegueToggle() {
        $('#segueToggleMobile .segue-switch').css('background', '#ccc');
        $('#segueToggleMobile .segue-switch-handle').css('transform', 'translateX(0)');
        $('#segueToggleMobile').css('border-color', '#dee2e6');
        $('#productSegueMobile').prop('checked', false);
    }

    $(document).on('click', '.menu-item', function() {
        setTimeout(resetSegueToggle, 100);
        hapticFeedback([50]);
    });

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
