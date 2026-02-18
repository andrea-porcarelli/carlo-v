<div id="invoiceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 2100; align-items: center; justify-content: center;">
    <div style="background: white; width: 640px; max-width: 95vw; max-height: 90vh; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.5);">

        <!-- Header -->
        <div style="background: #6f42c1; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
            <h5 style="margin: 0; font-weight: 700; font-size: 1rem; letter-spacing: 1px;">
                <i class="fas fa-file-invoice me-2"></i> FATTURAZIONE — TAVOLO <span id="invoiceTableNumber">-</span>
            </h5>
            <button id="closeInvoiceModal" style="background: none; border: none; color: white; font-size: 22px; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <!-- Summary + Split Control -->
        <div style="background: #f8f9fa; padding: 14px 20px; border-bottom: 2px solid #dee2e6; flex-shrink: 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <!-- Totals -->
                <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                    <div>
                        <div style="font-size: 0.7rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">Totale tavolo</div>
                        <div style="font-size: 1.3rem; font-weight: 700; color: #000;" id="invoiceTotalTable">€0.00</div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">Coperti</div>
                        <div style="font-size: 1.3rem; font-weight: 700; color: #000;" id="invoiceCoversCount">-</div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">Rimanente</div>
                        <div style="font-size: 1.3rem; font-weight: 700;" id="invoiceRemainingDisplay">€0.00</div>
                    </div>
                </div>

                <!-- Split selector -->
                <div style="background: white; border: 2px solid #6f42c1; border-radius: 8px; padding: 10px 16px; display: flex; align-items: center; gap: 12px;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #6f42c1; text-transform: uppercase; letter-spacing: 0.5px;">Dividi per</div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <button id="btnSplitMinus" style="width: 30px; height: 30px; background: #6f42c1; color: white; border: none; border-radius: 4px; font-size: 1.1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center;">−</button>
                        <span id="invoiceSplitCount" style="font-size: 1.4rem; font-weight: 700; color: #6f42c1; min-width: 28px; text-align: center;">1</span>
                        <button id="btnSplitPlus" style="width: 30px; height: 30px; background: #6f42c1; color: white; border: none; border-radius: 4px; font-size: 1.1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center;">+</button>
                    </div>
                    <div style="font-size: 0.8rem; color: #6c757d; border-left: 1px solid #dee2e6; padding-left: 12px;">
                        <span style="font-size: 0.7rem; color: #6c757d;">cad.</span><br>
                        <strong id="invoicePerPersonAmount" style="color: #6f42c1;">€0.00</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Rows -->
        <div style="flex: 1; overflow-y: auto; padding: 15px 20px;">
            <div id="invoiceRowsContainer"></div>
        </div>

        <!-- Remaining Payment -->
        <div id="invoiceRemainingSection" style="padding: 12px 20px; background: #fff3cd; border-top: 2px solid #ffc107; flex-shrink: 0; display: none;">
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <i class="fas fa-exclamation-triangle" style="color: #856404;"></i>
                <span style="font-size: 0.85rem; font-weight: 600; color: #856404;">
                    RIMANENTE <strong id="invoiceRemainingLabel">€0.00</strong> DA INCASSARE CON:
                </span>
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 600; margin: 0;">
                    <input type="radio" name="remainingMethod" value="pos" checked style="accent-color: #28a745;">
                    <span style="color: #28a745;"><i class="fas fa-credit-card me-1"></i>POS</span>
                </label>
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 600; margin: 0;">
                    <input type="radio" name="remainingMethod" value="contanti" style="accent-color: #17a2b8;">
                    <span style="color: #17a2b8;"><i class="fas fa-coins me-1"></i>Contanti</span>
                </label>
            </div>
        </div>

        <!-- Action Bar -->
        <div style="padding: 14px 20px; background: #000; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; gap: 10px;">
            <button id="cancelInvoiceModal" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-times me-1"></i> Annulla
            </button>
            <button id="confirmInvoicePayment" style="padding: 10px 24px; background: #6f42c1; color: white; border: none; border-radius: 4px; font-weight: 700; cursor: pointer; font-size: 1rem;">
                <i class="fas fa-check me-2"></i> CONFERMA INCASSO
            </button>
        </div>
    </div>
</div>

<style>
.invoice-row {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 12px 15px;
    margin-bottom: 10px;
    background: #fff;
    position: relative;
}
.invoice-row:hover { border-color: #6f42c1; }
.invoice-row label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    display: block;
    margin-bottom: 3px;
    letter-spacing: 0.3px;
}
.invoice-row input[type="text"],
.invoice-row input[type="number"] {
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 7px 10px;
    font-size: 0.9rem;
    width: 100%;
    box-sizing: border-box;
}
.invoice-row input:focus {
    outline: none;
    border-color: #6f42c1;
    box-shadow: 0 0 0 2px rgba(111,66,193,0.15);
}
.invoice-row-badge {
    display: inline-block;
    background: #6f42c1;
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px;
    margin-bottom: 8px;
}
.btn-remove-invoice-row {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    font-size: 0.95rem;
    padding: 3px 7px;
    border-radius: 3px;
}
.btn-remove-invoice-row:hover { background: #ffeef0; }
</style>
