/**
 * Operator Authentication Manager
 * Handles operator authentication for table operations
 */
const operatorAuthManager = {
    currentToken: null,
    currentUser: null,
    operators: [],
    pendingCallback: null,

    /**
     * Initialize the operator authentication system
     */
    init() {
        this.setupEventListeners();
    },

    /**
     * Setup event listeners for the modal
     */
    setupEventListeners() {
        const self = this;

        // Form submission
        $('#operatorAuthForm').on('submit', function(e) {
            e.preventDefault();
            self.handleAuthentication();
        });

        // Close modal buttons
        $('#closeOperatorAuthModal, #cancelOperatorAuth').on('click', function() {
            self.closeModal();
        });

        // Close on overlay click
        $('.operator-modal-overlay').on('click', function() {
            self.closeModal();
        });

        // Hide error on input change
        $('#operatorPassword').on('input', function() {
            $('#operatorAuthError').hide();
        });
    },

    /**
     * Request operator authentication
     * @param {Function} callback - Function to call after successful authentication
     * @returns {Promise}
     */
    requestAuth(callback) {
        return new Promise((resolve, reject) => {
            // Always show authentication modal - no token persistence
            this.showModal(callback, resolve, reject);
        });
    },

    /**
     * Show authentication modal
     */
    showModal(callback, resolve, reject) {
        this.pendingCallback = { callback, resolve, reject };
        $('#operatorAuthModal').fadeIn(300);
        $('#operatorPassword').focus();
        $('#operatorAuthError').hide();
        $('#operatorPassword').val('');
    },

    /**
     * Close authentication modal
     */
    closeModal() {
        $('#operatorAuthModal').fadeOut(300);
        $('#operatorPassword').val('');
        $('#operatorAuthError').hide();

        if (this.pendingCallback && this.pendingCallback.reject) {
            this.pendingCallback.reject('Authentication cancelled');
        }
        this.pendingCallback = null;
    },

    /**
     * Handle authentication form submission
     */
    async handleAuthentication() {
        const password = $('#operatorPassword').val();

        if (!password) {
            this.showError('Inserisci la password');
            return;
        }

        // Disable submit button
        $('#confirmOperatorAuth').prop('disabled', true);

        try {
            const response = await fetch('/api/operators/verify-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
                body: JSON.stringify({ password }),
            });

            const data = await response.json();

            if (data.success) {
                // Store token and user info temporarily (only for current operation)
                const token = data.data.token;
                const user = {
                    id: data.data.user_id,
                    name: data.data.user_name,
                    email: data.data.user_email,
                };

                // Show success notification
                if (typeof tableOrdersManager !== 'undefined') {
                    tableOrdersManager.showNotification(`Operazione autorizzata: ${user.name}`, 'success');
                }

                // Save callback reference before closing modal (which sets it to null)
                const callback = this.pendingCallback;

                // Close modal (this will set pendingCallback to null)
                $('#operatorAuthModal').fadeOut(300);
                $('#operatorPassword').val('');
                $('#operatorAuthError').hide();
                this.pendingCallback = null;

                // Execute callback with token and user
                if (callback) {
                    if (callback.resolve) {
                        callback.resolve({
                            token: token,
                            user: user,
                        });
                    }
                    if (callback.callback) {
                        callback.callback(user);
                    }
                }

                // Clear auth (no longer storing anything)
                this.clearAuth();
            } else {
                this.showError(data.message || 'Credenziali non valide');
            }
        } catch (error) {
            console.error('Authentication error:', error);
            this.showError('Errore durante l\'autenticazione');
        } finally {
            $('#confirmOperatorAuth').prop('disabled', false);
        }
    },

    /**
     * Show error message in modal
     */
    showError(message) {
        $('#operatorAuthErrorText').text(message);
        $('#operatorAuthError').fadeIn(300);
    },

    /**
     * Clear authentication
     */
    clearAuth() {
        this.currentToken = null;
        this.currentUser = null;
    },

    /**
     * Get current operator info
     */
    getCurrentOperator() {
        return this.currentUser;
    },

    /**
     * Get current token
     */
    getCurrentToken() {
        return this.currentToken;
    },
};

// Initialize on document ready
$(document).ready(function() {
    operatorAuthManager.init();
});
