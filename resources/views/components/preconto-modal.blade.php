<!-- PreConto Modal -->
<div id="precontoModal" class="preconto-modal" style="display: none;">
    <div class="preconto-modal-content">
        <div class="preconto-modal-header">
            <h3><i class="fas fa-receipt me-2"></i>STAMPA PRE-CONTO</h3>
            <button type="button" class="preconto-close-btn" id="closePrecontoModal">&times;</button>
        </div>
        <div class="preconto-modal-body">
            <p class="preconto-modal-description">
                Tavolo <strong id="precontoTableNumber">-</strong> - Totale: <strong id="precontoTotalAmount">€0.00</strong>
            </p>

            <div class="preconto-options">
                <label class="preconto-option-label">
                    <input type="radio" name="precontoType" value="full" checked>
                    <span class="preconto-option-text">
                        <i class="fas fa-file-invoice"></i>
                        Stampa conto intero
                    </span>
                </label>

                <label class="preconto-option-label">
                    <input type="radio" name="precontoType" value="split">
                    <span class="preconto-option-text">
                        <i class="fas fa-users"></i>
                        Dividi per numero di persone
                    </span>
                </label>

                <label class="preconto-option-label">
                    <input type="radio" name="precontoType" value="items">
                    <span class="preconto-option-text">
                        <i class="fas fa-list-check"></i>
                        Seleziona piatti specifici
                    </span>
                </label>
            </div>

            <div id="splitCountContainer" class="split-count-container" style="display: none;">
                <label for="splitCount">Numero di persone:</label>
                <div class="split-count-controls">
                    <button type="button" class="split-btn" id="decreaseSplit">-</button>
                    <input type="number" id="splitCount" value="2" min="2" max="20">
                    <button type="button" class="split-btn" id="increaseSplit">+</button>
                </div>
                <div class="split-preview" id="splitPreview">
                    <span>Quota per persona: <strong id="perPersonAmount">€0.00</strong></span>
                </div>
            </div>

            <div id="itemsSelectContainer" class="items-select-container" style="display: none;">
                <div class="items-select-header">
                    <span>Seleziona i piatti da includere nel preconto parziale:</span>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="items-select-all-btn" id="precontoSelectAll">Tutti</button>
                        <button type="button" class="items-select-all-btn" id="precontoDeselectAll">Nessuno</button>
                    </div>
                </div>
                <div id="precontoItemsList" class="preconto-items-list"></div>
                <div class="preconto-covers-row">
                    <label for="preconto_covers">Coperti inclusi:</label>
                    <div class="preconto-qty-ctrl">
                        <button type="button" class="pqi-dec" id="preconto_covers_dec">−</button>
                        <input type="number" id="preconto_covers" value="0" min="0" max="50"
                               class="preconto-item-qty-input"
                               oninput="if(this.value>+this.max)this.value=this.max;if(this.value<0)this.value=0;">
                        <button type="button" class="pqi-inc" id="preconto_covers_inc">+</button>
                        <span class="pqi-max" id="preconto_covers_max">/ 0</span>
                    </div>
                    <span id="precontoCoversInfo" style="font-size:0.8rem;color:#6c757d;margin-left:6px;"></span>
                </div>

                <!-- Sconto dedicato a questo preconto (solo per sconti di tipo 'value') -->
                <div id="precontoSplitDiscountRow" class="preconto-covers-row" style="display:none; flex-wrap:wrap;">
                    <label for="preconto_split_discount" style="margin-bottom:0;">Sconto su questo preconto (€):</label>
                    <div class="preconto-qty-ctrl">
                        <button type="button" class="pqi-dec" id="preconto_split_discount_dec">−</button>
                        <input type="number" id="preconto_split_discount" value="0" min="0" step="0.01"
                               class="preconto-item-qty-input" style="width:70px;">
                        <button type="button" class="pqi-inc" id="preconto_split_discount_inc">+</button>
                        <span class="pqi-max" id="preconto_split_discount_max">/ 0.00</span>
                    </div>
                    <button type="button" id="preconto_split_discount_all"
                            style="font-size:0.75rem;padding:3px 10px;border:1px solid #dc3545;background:white;color:#dc3545;border-radius:4px;cursor:pointer;margin-left:6px;">
                        Tutto residuo
                    </button>
                    <span id="precontoSplitDiscountInfo" style="font-size:0.75rem;color:#6c757d;margin-left:6px; flex-basis:100%;"></span>
                </div>
            </div>

            <div id="precontoPartialTotalRow" class="preconto-partial-total" style="display:none;">
                Totale parziale: <strong id="precontoPartialTotal">€0.00</strong>
            </div>
        </div>
        <div class="preconto-modal-footer">
            <button type="button" class="btn-preconto-cancel" id="cancelPreconto">ANNULLA</button>
            <button type="button" class="btn-preconto-print" id="confirmPreconto">
                <i class="fas fa-print me-2"></i>STAMPA
            </button>
        </div>
    </div>
</div>

<style>
.preconto-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 3000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preconto-modal-content {
    background: white;
    width: 600px;
    max-width: 95%;
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    overflow: hidden;
}

.preconto-modal-header {
    background: #17a2b8;
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.preconto-modal-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
}

.preconto-close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 28px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
}

.preconto-close-btn:hover {
    opacity: 0.8;
}

.preconto-modal-body {
    padding: 20px 25px;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    touch-action: pan-y;
    flex: 1;
}

.preconto-modal-description {
    text-align: center;
    font-size: 1.1rem;
    margin-bottom: 20px;
    color: #333;
}

.preconto-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 15px;
}

.preconto-option-label {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.preconto-option-label:hover {
    border-color: #17a2b8;
    background: #f8f9fa;
}

.preconto-option-label input[type="radio"] {
    width: 20px;
    height: 20px;
    margin-right: 15px;
    accent-color: #17a2b8;
}

.preconto-option-label input[type="radio"]:checked + .preconto-option-text {
    color: #17a2b8;
    font-weight: 600;
}

.preconto-option-text {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
    color: #3d3d3d;
}

.preconto-option-text i {
    font-size: 1.2rem;
    width: 25px;
    text-align: center;
}

.split-count-container {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-top: 10px;
}

.split-count-container label {
    display: block;
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.split-count-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 15px;
}

.split-btn {
    width: 50px;
    height: 50px;
    border: none;
    background: #17a2b8;
    color: white;
    font-size: 24px;
    font-weight: bold;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s;
}

.split-btn:hover {
    background: #138496;
}

.split-count-controls input {
    width: 80px;
    height: 50px;
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    border: 2px solid #dee2e6;
    border-radius: 8px;
}

.split-preview {
    text-align: center;
    padding: 15px;
    background: white;
    border-radius: 8px;
    border: 2px solid #17a2b8;
}

.split-preview span {
    font-size: 1.1rem;
}

.split-preview strong {
    color: #17a2b8;
    font-size: 1.3rem;
}

/* Items select */
.items-select-container {
    background: #f8f9fa;
    border-radius: 8px;
    margin-top: 10px;
}

.items-select-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
    border-bottom: 1px solid #dee2e6;
    background: #e9ecef;
}

.items-select-all-btn {
    font-size: 0.75rem;
    padding: 3px 10px;
    border: 1px solid #17a2b8;
    background: white;
    color: #17a2b8;
    border-radius: 4px;
    cursor: pointer;
}

.items-select-all-btn:hover {
    background: #17a2b8;
    color: white;
}

.preconto-items-list {
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    padding: 8px 0;
}

.preconto-item-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 14px;
    border-bottom: 1px solid #f0f0f0;
    cursor: default;
    transition: background 0.15s;
}

.preconto-item-row:last-child {
    border-bottom: none;
}

.preconto-item-row.selected {
    background: #e8f5e9;
}

.preconto-item-name {
    flex: 1;
    font-size: 0.9rem;
    color: #333;
}

.preconto-item-price {
    font-size: 0.9rem;
    font-weight: 600;
    color: #dc3545;
    min-width: 60px;
    text-align: right;
}

/* Qty spinbox per preconto items */
.preconto-qty-ctrl {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.pqi-dec, .pqi-inc {
    width: 26px;
    height: 26px;
    border: 1px solid #adb5bd;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    padding: 0;
    touch-action: manipulation;
}

.pqi-dec:hover, .pqi-inc:hover {
    background: #17a2b8;
    color: white;
    border-color: #17a2b8;
}

.preconto-item-qty-input {
    width: 42px;
    text-align: center;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 3px 2px;
    font-size: 0.9rem;
    -moz-appearance: textfield;
}

.preconto-item-qty-input::-webkit-inner-spin-button,
.preconto-item-qty-input::-webkit-outer-spin-button {
    -webkit-appearance: none;
}

.pqi-max {
    font-size: 0.75rem;
    color: #6c757d;
    white-space: nowrap;
}

/* Qty spinbox per autoconsumo items */
.autoconsumo-qty-ctrl {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.aqi-dec, .aqi-inc {
    width: 26px;
    height: 26px;
    border: 1px solid #adb5bd;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    padding: 0;
}

.aqi-dec:hover, .aqi-inc:hover {
    background: #6c757d;
    color: white;
    border-color: #6c757d;
}

.autoconsumo-qty-input {
    width: 40px;
    text-align: center;
    border: 1px solid #adb5bd;
    border-radius: 4px;
    padding: 3px 2px;
    font-size: 0.9rem;
    -moz-appearance: textfield;
}

.autoconsumo-qty-input::-webkit-inner-spin-button,
.autoconsumo-qty-input::-webkit-outer-spin-button {
    -webkit-appearance: none;
}

.preconto-covers-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-top: 1px solid #dee2e6;
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
}

.preconto-discount-row {
    padding: 10px 14px;
    border-top: 2px solid #dee2e6;
    margin-top: 10px;
    font-size: 0.9rem;
    background: #f8f9fa;
    border-radius: 0 0 6px 6px;
}

.preconto-discount-type-btns {
    display: flex;
    gap: 12px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.preconto-discount-type-label {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    color: #333;
}

.preconto-discount-type-label input[type="radio"] {
    accent-color: #dc3545;
}

.preconto-partial-total {
    padding: 10px 14px;
    font-size: 1rem;
    color: #333;
    text-align: right;
    border-top: 2px solid #dee2e6;
}

.preconto-partial-total strong {
    color: #dc3545;
    font-size: 1.2rem;
}

.preconto-modal-footer {
    padding: 15px 20px;
    background: #f8f9fa;
    display: flex;
    gap: 10px;
    flex-shrink: 0;
    border-top: 1px solid #dee2e6;
}

.btn-preconto-cancel {
    flex: 1;
    padding: 13px;
    border: 2px solid #6c757d;
    background: white;
    color: #6c757d;
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-preconto-cancel:hover {
    background: #6c757d;
    color: white;
}

.btn-preconto-print {
    flex: 2;
    padding: 13px;
    border: none;
    background: #17a2b8;
    color: white;
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-preconto-print:hover {
    background: #138496;
}

@media (max-width: 600px) {
    .preconto-modal-content {
        max-height: 96vh;
    }

    .preconto-modal-body {
        padding: 14px 14px;
    }


    /* Touch target più grandi su mobile */
    .pqi-dec, .pqi-inc {
        width: 40px;
        height: 40px;
        font-size: 20px;
    }

    .preconto-item-qty-input {
        width: 48px;
        height: 40px;
        font-size: 1rem;
    }

    .preconto-item-row {
        padding: 10px 10px;
        gap: 8px;
    }

    .items-select-all-btn {
        font-size: 0.85rem;
        padding: 6px 12px;
        min-height: 36px;
    }
}
</style>
