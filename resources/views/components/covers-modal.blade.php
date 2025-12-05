<!-- Covers Selection Modal -->
<div id="coversModal" class="covers-modal" style="display: none;">
    <div class="covers-modal-overlay"></div>
    <div class="covers-modal-content">
        <div class="covers-modal-header">
            <h4 class="covers-modal-title">
                <i class="fas fa-users me-2"></i> NUMERO COPERTI
            </h4>
        </div>

        <div class="covers-modal-body">
            <p class="covers-modal-description">
                Seleziona il numero di coperti per il <span id="coversTableNumber">tavolo</span>
            </p>

            <div class="covers-grid">
                <button class="covers-btn" data-covers="1">1</button>
                <button class="covers-btn" data-covers="2">2</button>
                <button class="covers-btn" data-covers="3">3</button>
                <button class="covers-btn" data-covers="4">4</button>
                <button class="covers-btn" data-covers="5">5</button>
                <button class="covers-btn" data-covers="6">6</button>
                <button class="covers-btn" data-covers="7">7</button>
                <button class="covers-btn" data-covers="8">8</button>
                <button class="covers-btn" data-covers="9">9</button>
                <button class="covers-btn" data-covers="10">10</button>
                <button class="covers-btn" data-covers="12">12</button>
                <button class="covers-btn covers-btn-custom" id="customCoversBtn">
                    <i class="fas fa-ellipsis-h"></i> Altro
                </button>
            </div>
        </div>

        <div class="covers-modal-footer">
            <button type="button" class="covers-cancel-btn" id="cancelCoversBtn">
                <i class="fas fa-times me-2"></i> ANNULLA
            </button>
        </div>
    </div>
</div>

<style>
.covers-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9998;
    display: flex;
    align-items: center;
    justify-content: center;
}

.covers-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
}

.covers-modal-content {
    position: relative;
    background: #1a1a1a;
    border: 2px solid #dc3545;
    border-radius: 8px;
    width: 90%;
    max-width: 550px;
    box-shadow: 0 10px 40px rgba(220, 53, 69, 0.3);
    animation: slideIn 0.3s ease-out;
}

.covers-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #333;
}

.covers-modal-title {
    margin: 0;
    color: #dc3545;
    font-size: 1.25rem;
    font-weight: 600;
    text-align: center;
}

.covers-modal-body {
    padding: 24px;
}

.covers-modal-description {
    color: #ccc;
    margin-bottom: 24px;
    font-size: 1rem;
    text-align: center;
}

.covers-modal-description span {
    color: #dc3545;
    font-weight: 600;
}

.covers-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.covers-btn {
    background: #2a2a2a;
    border: 2px solid #444;
    border-radius: 8px;
    color: #fff;
    font-size: 1.5rem;
    font-weight: 600;
    padding: 20px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.covers-btn:hover {
    background: #dc3545;
    border-color: #dc3545;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
}

.covers-btn:active {
    transform: translateY(0);
}

.covers-btn-custom {
    font-size: 1rem;
    padding: 15px;
}

.covers-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #333;
    display: flex;
    justify-content: center;
}

.covers-cancel-btn {
    padding: 12px 32px;
    border: none;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;
    background: #444;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.covers-cancel-btn:hover {
    background: #555;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .covers-modal-content {
        width: 95%;
        margin: 20px;
    }

    .covers-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }

    .covers-btn {
        font-size: 1.3rem;
        padding: 16px;
    }

    .covers-modal-header,
    .covers-modal-body,
    .covers-modal-footer {
        padding: 16px;
    }

    .covers-modal-title {
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .covers-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>
