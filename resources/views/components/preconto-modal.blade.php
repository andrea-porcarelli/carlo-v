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
    width: 450px;
    max-width: 95%;
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
    padding: 25px;
}

.preconto-modal-description {
    text-align: center;
    font-size: 1.1rem;
    margin-bottom: 25px;
    color: #333;
}

.preconto-options {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.preconto-option-label {
    display: flex;
    align-items: center;
    padding: 15px;
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
    margin-top: 15px;
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

.preconto-modal-footer {
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    gap: 10px;
}

.btn-preconto-cancel {
    flex: 1;
    padding: 15px;
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
    padding: 15px;
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
</style>
