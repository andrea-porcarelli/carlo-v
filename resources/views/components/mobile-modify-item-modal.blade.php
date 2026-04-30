<!-- Mobile-only modal: modifica voce ordine (qty +/- e prezzo) -->
<div id="mobileModifyItemModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:3500; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:18px; max-width:380px; width:92%; box-shadow:0 20px 60px rgba(0,0,0,0.5);">

        <h4 id="mmiDishName" style="margin:0 0 14px 0; font-weight:700; text-transform:uppercase; text-align:center; color:#000; font-size:1rem;">—</h4>

        <!-- Quantity -->
        <div style="margin-bottom:14px;">
            <label style="display:block; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#6c757d; margin-bottom:6px;">Quantità</label>
            <div style="display:flex; align-items:stretch; gap:8px;">
                <button type="button" id="mmiQtyMinus" style="flex:0 0 56px; background:#dc3545; color:#fff; border:none; border-radius:6px; font-size:1.6rem; font-weight:700; cursor:pointer;">−</button>
                <input type="number" id="mmiQty" min="0" step="1" value="1" inputmode="numeric"
                       style="flex:1; min-width:0; text-align:center; font-size:1.4rem; font-weight:700; border:2px solid #dee2e6; border-radius:6px; padding:8px;">
                <button type="button" id="mmiQtyPlus" style="flex:0 0 56px; background:#28a745; color:#fff; border:none; border-radius:6px; font-size:1.6rem; font-weight:700; cursor:pointer;">+</button>
            </div>
        </div>

        <!-- Price -->
        <div style="margin-bottom:18px;">
            <label style="display:block; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#6c757d; margin-bottom:6px;">Prezzo unitario (€)</label>
            <input type="number" id="mmiPrice" min="0" step="0.01" inputmode="decimal"
                   style="width:100%; box-sizing:border-box; text-align:center; font-size:1.2rem; font-weight:700; border:2px solid #dee2e6; border-radius:6px; padding:10px; color:#dc3545;">
        </div>

        <div style="display:flex; gap:8px;">
            <button type="button" id="mmiCancel" style="flex:1; padding:12px; background:#6c757d; border:none; color:#fff; font-weight:700; text-transform:uppercase; border-radius:6px; cursor:pointer; font-size:0.85rem;">Annulla</button>
            <button type="button" id="mmiConfirm" style="flex:2; padding:12px; background:#0d6efd; border:none; color:#fff; font-weight:700; text-transform:uppercase; border-radius:6px; cursor:pointer; font-size:0.85rem;">Conferma</button>
        </div>
    </div>
</div>
