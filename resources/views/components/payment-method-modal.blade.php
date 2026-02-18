<div id="paymentMethodModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; width: 420px; border-radius: 8px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
        <!-- Header -->
        <div style="background: #000; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin: 0; font-weight: 700; font-size: 1rem; letter-spacing: 1px;">
                <i class="fas fa-money-bill me-2"></i> METODO DI PAGAMENTO
            </h5>
            <button id="closePaymentMethodModal" style="background: none; border: none; color: white; font-size: 22px; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <!-- Table Info -->
        <div style="padding: 15px 20px 10px; background: #f8f9fa; border-bottom: 1px solid #dee2e6; text-align: center;">
            <span style="font-size: 0.9rem; color: #6c757d;">TAVOLO</span>
            <span id="pmTableNumber" style="font-size: 2rem; font-weight: 700; color: #dc3545; margin: 0 12px;">-</span>
            <span style="font-size: 0.9rem; color: #6c757d;">TOTALE</span>
            <span id="pmTotalAmount" style="font-size: 1.5rem; font-weight: 700; color: #000; margin-left: 8px;">€0.00</span>
        </div>

        <!-- Payment Buttons -->
        <div style="padding: 20px; display: flex; flex-direction: column; gap: 12px;">
            <button id="btnPayPos" style="padding: 16px; background: #28a745; color: white; border: none; border-radius: 6px; font-size: 1rem; font-weight: 700; cursor: pointer; text-align: left; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-credit-card" style="font-size: 1.3rem; width: 28px; text-align: center;"></i>
                <div>
                    <div style="font-size: 1rem; letter-spacing: 1px;">POS</div>
                    <div style="font-size: 0.75rem; font-weight: 400; opacity: 0.85;">Pagamento con carta</div>
                </div>
            </button>
            <button id="btnPayContanti" style="padding: 16px; background: #17a2b8; color: white; border: none; border-radius: 6px; font-size: 1rem; font-weight: 700; cursor: pointer; text-align: left; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-coins" style="font-size: 1.3rem; width: 28px; text-align: center;"></i>
                <div>
                    <div style="font-size: 1rem; letter-spacing: 1px;">CONTANTI</div>
                    <div style="font-size: 0.75rem; font-weight: 400; opacity: 0.85;">Pagamento in contanti</div>
                </div>
            </button>
            <button id="btnPayFattura" style="padding: 16px; background: #6f42c1; color: white; border: none; border-radius: 6px; font-size: 1rem; font-weight: 700; cursor: pointer; text-align: left; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-file-invoice" style="font-size: 1.3rem; width: 28px; text-align: center;"></i>
                <div>
                    <div style="font-size: 1rem; letter-spacing: 1px;">FATTURA</div>
                    <div style="font-size: 0.75rem; font-weight: 400; opacity: 0.85;">Emissione fattura per uno o più ospiti</div>
                </div>
            </button>
        </div>
    </div>
</div>
