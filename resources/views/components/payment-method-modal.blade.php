<div id="paymentMethodModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; width: 520px; max-width: 96%; max-height: 90vh; display: flex; flex-direction: column; border-radius: 8px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
        <!-- Header -->
        <div style="background: #000; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
            <h5 style="margin: 0; font-weight: 700; font-size: 1rem; letter-spacing: 1px;">
                <i class="fas fa-money-bill me-2"></i> INCASSA
            </h5>
            <button id="closePaymentMethodModal" style="background: none; border: none; color: white; font-size: 22px; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <!-- Table Info -->
        <div style="padding: 12px 20px; background: #f8f9fa; border-bottom: 1px solid #dee2e6; text-align: center; flex-shrink: 0;">
            <span style="font-size: 0.9rem; color: #6c757d;">TAVOLO</span>
            <span id="pmTableNumber" style="font-size: 2rem; font-weight: 700; color: #dc3545; margin: 0 12px;">-</span>
            <span style="font-size: 0.9rem; color: #6c757d;">TOTALE</span>
            <span id="pmTotalAmount" style="font-size: 1.5rem; font-weight: 700; color: #000; margin-left: 8px;">€0.00</span>
        </div>

        <!-- Scrollable body -->
        <div style="overflow-y: auto; flex: 1;">

            <!-- NORMAL PAYMENT VIEW (no splits) -->
            <div id="normalPaymentView" style="padding: 20px; display: flex; flex-direction: column; gap: 12px;">

                <!-- Step 1: Scontrino o Fattura -->
                <div id="pmStep1" style="display: flex; flex-direction: column; gap: 12px;">
                    <button onclick="tableOrdersManager._pmShowStep('scontrino')" class="pm-doctype-btn" style="background:#28a745;">
                        <i class="fas fa-receipt" style="font-size:1.4rem; width:32px; text-align:center;"></i>
                        <div>
                            <div style="font-size:1rem; letter-spacing:1px;">SCONTRINO</div>
                            <div style="font-size:0.75rem; font-weight:400; opacity:0.85;">Contanti · Pagamento elettronico</div>
                        </div>
                    </button>
                    <button onclick="tableOrdersManager._pmShowStep('fattura')" class="pm-doctype-btn" style="background:#6f42c1;">
                        <i class="fas fa-file-invoice" style="font-size:1.4rem; width:32px; text-align:center;"></i>
                        <div>
                            <div style="font-size:1rem; letter-spacing:1px;">FATTURA</div>
                            <div style="font-size:0.75rem; font-weight:400; opacity:0.85;">Contanti · POS · Bonifico · Assegno</div>
                        </div>
                    </button>
                </div>

                <!-- Step 2a: Scontrino → metodi -->
                <div id="pmStepScontrino" style="display: none; flex-direction: column; gap: 10px;">
                    <button onclick="tableOrdersManager._pmShowStep(null)" class="pm-back-btn">
                        <i class="fas fa-arrow-left me-2"></i> Indietro
                    </button>
                    <button onclick="tableOrdersManager.executePayment('contanti')" class="pm-method-btn" style="background:#17a2b8;">
                        <i class="fas fa-coins" style="font-size:1.3rem; width:28px; text-align:center;"></i>
                        <div>
                            <div style="font-size:1rem; letter-spacing:1px;">CONTANTI</div>
                            <div style="font-size:0.75rem; font-weight:400; opacity:0.85;">Pagamento in contanti</div>
                        </div>
                    </button>
                    <button onclick="tableOrdersManager.executePayment('pos')" class="pm-method-btn" style="background:#28a745;">
                        <i class="fas fa-credit-card" style="font-size:1.3rem; width:28px; text-align:center;"></i>
                        <div>
                            <div style="font-size:1rem; letter-spacing:1px;">PAGAMENTO ELETTRONICO</div>
                            <div style="font-size:0.75rem; font-weight:400; opacity:0.85;">Carta · POS</div>
                        </div>
                    </button>
                </div>

                <!-- Step 2b: Fattura → metodi -->
                <div id="pmStepFattura" style="display: none; flex-direction: column; gap: 10px;">
                    <button onclick="tableOrdersManager._pmShowStep(null)" class="pm-back-btn">
                        <i class="fas fa-arrow-left me-2"></i> Indietro
                    </button>
                    <button onclick="tableOrdersManager.openInvoiceModal('fattura_contanti')" class="pm-method-btn" style="background:#17a2b8;">
                        <i class="fas fa-coins" style="font-size:1.3rem; width:28px; text-align:center;"></i>
                        <div>
                            <div style="font-size:1rem; letter-spacing:1px;">CONTANTI</div>
                            <div style="font-size:0.75rem; font-weight:400; opacity:0.85;">Pagamento in contanti</div>
                        </div>
                    </button>
                    <button onclick="tableOrdersManager.openInvoiceModal('fattura_pos')" class="pm-method-btn" style="background:#28a745;">
                        <i class="fas fa-credit-card" style="font-size:1.3rem; width:28px; text-align:center;"></i>
                        <div>
                            <div style="font-size:1rem; letter-spacing:1px;">PAGAMENTO ELETTRONICO</div>
                            <div style="font-size:0.75rem; font-weight:400; opacity:0.85;">Carta · POS</div>
                        </div>
                    </button>
                    <button onclick="tableOrdersManager.openInvoiceModal('bonifico')" class="pm-method-btn" style="background:#fd7e14;">
                        <i class="fas fa-university" style="font-size:1.3rem; width:28px; text-align:center;"></i>
                        <div>
                            <div style="font-size:1rem; letter-spacing:1px;">BONIFICO</div>
                            <div style="font-size:0.75rem; font-weight:400; opacity:0.85;">Trasferimento bancario</div>
                        </div>
                    </button>
                    <button onclick="tableOrdersManager.openInvoiceModal('assegno')" class="pm-method-btn" style="background:#6c757d;">
                        <i class="fas fa-money-check" style="font-size:1.3rem; width:28px; text-align:center;"></i>
                        <div>
                            <div style="font-size:1rem; letter-spacing:1px;">ASSEGNO</div>
                            <div style="font-size:0.75rem; font-weight:400; opacity:0.85;">Pagamento con assegno</div>
                        </div>
                    </button>
                </div>

            </div>

            <!-- SPLIT PAYMENT VIEW (preconto splits exist) -->
            <div id="splitPaymentView" style="display: none; padding: 16px;">
                <div style="font-size: 0.85rem; color: #6c757d; margin-bottom: 12px; text-align: center;">
                    Seleziona la quota da pagare e il metodo di pagamento
                </div>
                <div id="splitPaymentList"></div>
            </div>

        </div>
    </div>
</div>

<style>
.pm-doctype-btn {
    padding: 16px;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: filter 0.15s;
}
.pm-doctype-btn:hover { filter: brightness(1.08); }

.pm-method-btn {
    padding: 14px 16px;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: filter 0.15s;
}
.pm-method-btn:hover { filter: brightness(1.08); }

.pm-back-btn {
    padding: 8px 14px;
    background: none;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    color: #6c757d;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    text-align: left;
    transition: background 0.15s;
    align-self: flex-start;
}
.pm-back-btn:hover { background: #f8f9fa; }

.split-pay-row {
    border: 2px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 10px;
    overflow: hidden;
}
.split-pay-row.paid {
    border-color: #28a745;
    opacity: 0.65;
}
.split-pay-row-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
.split-pay-row.paid .split-pay-row-header {
    background: #d4edda;
}
.split-pay-label {
    font-weight: 700;
    font-size: 0.9rem;
    color: #333;
}
.split-pay-total {
    font-size: 1.1rem;
    font-weight: 700;
    color: #dc3545;
}
.split-pay-row.paid .split-pay-total {
    color: #28a745;
}
.split-pay-status {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
}
.split-pay-status.pending { background: #fff3cd; color: #856404; }
.split-pay-status.paid    { background: #d4edda; color: #155724; }

/* Doctype row (Scontrino / Fattura choice in split rows) */
.split-doctype-row {
    display: flex;
    gap: 6px;
    padding: 8px 14px;
}
.split-docbtn {
    flex: 1;
    padding: 8px 6px;
    border: none;
    border-radius: 5px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    transition: filter 0.15s;
}
.split-docbtn:hover { filter: brightness(1.1); }
.split-docbtn.scontrino { background: #28a745; }
.split-docbtn.fattura   { background: #6f42c1; }

/* Method buttons inside split rows */
.split-pay-btns {
    display: flex;
    gap: 5px;
    padding: 8px 14px;
    flex-wrap: wrap;
}
.split-pay-btn {
    flex: 1;
    min-width: 70px;
    padding: 7px 4px;
    border: none;
    border-radius: 5px;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    letter-spacing: 0.3px;
    color: white;
    transition: filter 0.15s;
}
.split-pay-btn:hover { filter: brightness(1.1); }
.split-pay-btn.pos      { background: #28a745; }
.split-pay-btn.contanti { background: #17a2b8; }
.split-pay-btn.bonifico { background: #fd7e14; }
.split-pay-btn.assegno  { background: #6c757d; }
.split-pay-btn.fattura  { background: #6f42c1; }
.split-pay-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.split-back-btn {
    flex: 0 0 auto;
    padding: 7px 10px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    background: white;
    color: #6c757d;
    font-size: 0.8rem;
    cursor: pointer;
}
.split-back-btn:hover { background: #f8f9fa; }

.split-remainder-row {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    margin-top: 6px;
    overflow: hidden;
}
</style>
