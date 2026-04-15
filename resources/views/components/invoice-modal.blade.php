<div id="invoiceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 2100; align-items: center; justify-content: center;">
    <div style="background: #f5f5f5; width: 720px; max-width: 96vw; max-height: 92vh; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.5);">

        <!-- Header -->
        <div style="background: #6f42c1; color: white; padding: 16px 22px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
            <h5 style="margin: 0; font-weight: 700; font-size: 1.05rem; letter-spacing: 1px;">
                <i class="fas fa-file-invoice me-2"></i> FATTURAZIONE — TAVOLO <span id="invoiceTableNumber">-</span>
            </h5>
            <button id="closeInvoiceModal" style="background: rgba(255,255,255,0.15); border: none; color: white; width: 32px; height: 32px; border-radius: 6px; font-size: 18px; cursor: pointer; line-height: 1; transition: background .15s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">&times;</button>
        </div>

        <!-- Summary + Split Control -->
        <div style="background: #fff; padding: 14px 22px; border-bottom: 2px solid #e9ecef; flex-shrink: 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <!-- Totals -->
                <div style="display: flex; gap: 28px; flex-wrap: wrap;">
                    <div>
                        <div style="font-size: 0.68rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Totale tavolo</div>
                        <div style="font-size: 1.35rem; font-weight: 700; color: #000;" id="invoiceTotalTable">€0.00</div>
                    </div>
                    <div>
                        <div style="font-size: 0.68rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Coperti</div>
                        <div style="font-size: 1.35rem; font-weight: 700; color: #000;" id="invoiceCoversCount">-</div>
                    </div>
                    <div>
                        <div style="font-size: 0.68rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Rimanente</div>
                        <div style="font-size: 1.35rem; font-weight: 700;" id="invoiceRemainingDisplay">€0.00</div>
                    </div>
                </div>

                <!-- Split selector -->
                <div style="background: #faf8ff; border: 2px solid #6f42c1; border-radius: 8px; padding: 10px 16px; display: flex; align-items: center; gap: 12px;">
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

        <!-- Required fields hint -->
        <div style="padding: 8px 22px; background: #fff; border-bottom: 1px solid #e9ecef; flex-shrink: 0;">
            <span style="font-size: 0.75rem; color: #999;"><span style="color: #dc3545;">*</span> Campi obbligatori</span>
        </div>

        <!-- Invoice Rows -->
        <div style="flex: 1; overflow-y: auto; padding: 16px 22px;">
            <div id="invoiceRowsContainer"></div>
        </div>

        <!-- Remaining Payment -->
        <div id="invoiceRemainingSection" style="padding: 12px 22px; background: #fff3cd; border-top: 2px solid #ffc107; flex-shrink: 0; display: none;">
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
        <div style="padding: 14px 22px; background: #1a1a1a; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; gap: 10px;">
            <button id="cancelInvoiceModal" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background .15s;" onmouseover="this.style.background='#5a6268'" onmouseout="this.style.background='#6c757d'">
                <i class="fas fa-times me-1"></i> Annulla
            </button>
            <button id="confirmInvoicePayment" style="padding: 10px 28px; background: #6f42c1; color: white; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 1rem; transition: background .15s;" onmouseover="this.style.background='#5a32a3'" onmouseout="this.style.background='#6f42c1'">
                <i class="fas fa-check me-2"></i> CONFERMA INCASSO
            </button>
        </div>
    </div>
</div>

<style>
.invoice-row {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 12px;
    background: #fff;
    position: relative;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: border-color .15s;
}
.invoice-row:hover { border-color: #6f42c1; }
.invoice-row label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    display: block;
    margin-bottom: 4px;
    letter-spacing: 0.3px;
}
.invoice-row label .req { color: #dc3545; margin-left: 1px; }
.invoice-row input[type="text"],
.invoice-row input[type="number"],
.invoice-row select {
    border: 1px solid #ced4da;
    border-radius: 5px;
    padding: 8px 10px;
    font-size: 0.88rem;
    width: 100%;
    box-sizing: border-box;
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
}
.invoice-row select {
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236c757d' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 28px;
    cursor: pointer;
}
.invoice-row input:focus,
.invoice-row select:focus {
    outline: none;
    border-color: #6f42c1;
    box-shadow: 0 0 0 2px rgba(111,66,193,0.15);
}
.invoice-row .invoice-field-error {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 2px rgba(220,53,69,0.15) !important;
}
.invoice-row-badge {
    display: inline-block;
    background: #6f42c1;
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 10px;
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
    padding: 4px 8px;
    border-radius: 4px;
    transition: background .12s;
}
.btn-remove-invoice-row:hover { background: #ffeef0; }
.invoice-customer-search {
    border: 1px solid #ced4da;
    border-radius: 5px;
    padding: 8px 10px;
    font-size: 0.88rem;
    transition: border-color .15s, box-shadow .15s;
}
.invoice-customer-search:focus {
    outline: none;
    border-color: #6f42c1;
    box-shadow: 0 0 0 2px rgba(111,66,193,0.15);
}
</style>
