<!-- Operator Authentication Modal -->
<div id="operatorAuthModal" class="operator-modal" style="display: none;">
    <div class="operator-modal-overlay"></div>
    <div class="operator-modal-content">
        <div class="operator-modal-header">
            <h4 class="operator-modal-title">
                <i class="fas fa-lock me-2"></i> CONFERMA OPERAZIONE
            </h4>
            <button class="operator-modal-close" id="closeOperatorAuthModal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="operator-modal-body">
            <p class="operator-modal-description">
                Inserisci la tua password per confermare l'operazione
            </p>

            <form id="operatorAuthForm">
                <div class="operator-form-group">
                    <label for="operatorPassword">
                        <i class="fas fa-key me-2"></i> Password Operatore
                    </label>
                    <input
                        type="password"
                        id="operatorPassword"
                        class="operator-form-control"
                        placeholder="Inserisci la tua password"
                        required
                        autocomplete="off"
                    >
                </div>

                <div class="operator-error-message" id="operatorAuthError" style="display: none;">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <span id="operatorAuthErrorText"></span>
                </div>
            </form>
        </div>

        <div class="operator-modal-footer">
            <button type="button" class="operator-btn operator-btn-secondary" id="cancelOperatorAuth">
                <i class="fas fa-times me-2"></i> ANNULLA
            </button>
            <button type="submit" form="operatorAuthForm" class="operator-btn operator-btn-primary" id="confirmOperatorAuth">
                <i class="fas fa-check me-2"></i> CONFERMA
            </button>
        </div>
    </div>
</div>

<style>
.operator-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.operator-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
}

.operator-modal-content {
    position: relative;
    background: #1a1a1a;
    border: 2px solid #dc3545;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 40px rgba(220, 53, 69, 0.3);
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.operator-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #333;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.operator-modal-title {
    margin: 0;
    color: #dc3545;
    font-size: 1.25rem;
    font-weight: 600;
}

.operator-modal-close {
    background: none;
    border: none;
    color: #999;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

.operator-modal-close:hover {
    color: #dc3545;
}

.operator-modal-body {
    padding: 24px;
}

.operator-modal-description {
    color: #ccc;
    margin-bottom: 20px;
    font-size: 0.95rem;
}

.operator-form-group {
    margin-bottom: 20px;
}

.operator-form-group label {
    display: block;
    color: #fff;
    font-weight: 500;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.operator-form-control {
    width: 100%;
    padding: 12px 16px;
    background: #2a2a2a;
    border: 1px solid #444;
    border-radius: 4px;
    color: #fff;
    font-size: 1rem;
    transition: border-color 0.2s;
}

.operator-form-control:focus {
    outline: none;
    border-color: #dc3545;
}

.operator-form-control option {
    background: #2a2a2a;
    color: #fff;
}

.operator-error-message {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid #dc3545;
    border-radius: 4px;
    padding: 12px 16px;
    color: #dc3545;
    font-size: 0.9rem;
    margin-top: 16px;
}

.operator-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #333;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.operator-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.operator-btn-primary {
    background: #dc3545;
    color: #fff;
}

.operator-btn-primary:hover {
    background: #c82333;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.operator-btn-secondary {
    background: #444;
    color: #fff;
}

.operator-btn-secondary:hover {
    background: #555;
}

.operator-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .operator-modal-content {
        width: 95%;
        margin: 20px;
    }

    .operator-modal-header,
    .operator-modal-body,
    .operator-modal-footer {
        padding: 16px;
    }

    .operator-modal-title {
        font-size: 1.1rem;
    }

    .operator-btn {
        padding: 10px 20px;
        font-size: 0.9rem;
    }
}
</style>
