/**
 * Covers Selection Manager
 * Handles cover selection for tables
 */
const coversManager = {
    pendingCallback: null,
    tableNumber: null,

    /**
     * Initialize the covers manager
     */
    init() {
        this.setupEventListeners();
    },

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        const self = this;

        // Cover number buttons (excluding drinks mode and custom)
        $('.covers-btn[data-covers]').not('#drinksModeBtn').not('#customCoversBtn').on('click', function() {
            const covers = parseInt($(this).data('covers'));
            self.selectCovers(covers);
        });

        // Drinks mode button (covers = 0)
        $('#drinksModeBtn').on('click', function() {
            self.selectCovers(0);
        });

        // Custom covers button
        $('#customCoversBtn').on('click', function() {
            self.showCustomCoversInput();
        });

        // Cancel button
        $('#cancelCoversBtn').on('click', function() {
            self.closeModal();
        });

        // Close on overlay click
        $('.covers-modal-overlay').on('click', function() {
            self.closeModal();
        });
    },

    /**
     * Request covers selection
     * @param {string} tableNumber - Table number for display
     * @returns {Promise<number>} - Selected number of covers
     */
    requestCovers(tableNumber) {
        return new Promise((resolve, reject) => {
            this.tableNumber = tableNumber;
            this.pendingCallback = { resolve, reject };
            this.showModal();
        });
    },

    /**
     * Show covers modal
     */
    showModal() {
        $('#coversTableNumber').text(`Tavolo ${this.tableNumber}`);
        $('#coversModal').fadeIn(300);
    },

    /**
     * Close covers modal
     */
    closeModal() {
        $('#coversModal').fadeOut(300);

        if (this.pendingCallback && this.pendingCallback.reject) {
            this.pendingCallback.reject('Covers selection cancelled');
        }
        this.pendingCallback = null;
        this.tableNumber = null;
    },

    /**
     * Select covers and close modal
     * @param {number} covers - Number of covers selected (0 for drinks mode)
     */
    selectCovers(covers) {
        if (covers !== null && covers !== undefined && covers >= 0) {
            // Haptic feedback
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }

            if (this.pendingCallback && this.pendingCallback.resolve) {
                this.pendingCallback.resolve(covers);
            }

            this.closeModal();
        }
    },

    /**
     * Show custom covers input
     */
    showCustomCoversInput() {
        const covers = prompt('Inserisci il numero di coperti:', '1');

        if (covers === null) return; // User cancelled

        const coversNum = parseInt(covers);
        if (isNaN(coversNum) || coversNum < 1 || coversNum > 100) {
            if (typeof tableOrdersManager !== 'undefined') {
                tableOrdersManager.showNotification('Numero non valido. Inserisci un numero tra 1 e 100', 'error');
            } else {
                alert('Numero non valido. Inserisci un numero tra 1 e 100');
            }
            return;
        }

        this.selectCovers(coversNum);
    }
};

// Initialize on document ready
$(document).ready(function() {
    coversManager.init();
});
