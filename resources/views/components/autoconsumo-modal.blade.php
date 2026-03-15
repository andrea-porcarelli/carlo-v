<!-- Autoconsumo Modal -->
<div id="autoconsumoModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:3500; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:8px; width:92%; max-width:920px; height:85vh; display:flex; flex-direction:column; overflow:hidden;">
        <!-- Header -->
        <div style="background:#6c757d; color:white; padding:14px 20px; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
            <h5 style="margin:0; font-weight:700; text-transform:uppercase; letter-spacing:1px;">
                <i class="fas fa-eraser me-2"></i>Autoconsumo — Tavolo <span id="autoconsumoTableNumber">-</span>
            </h5>
            <button id="btnAutoconsumoCancel" style="background:#dc3545; border:none; color:white; font-size:0.8rem; font-weight:700; padding:6px 16px; border-radius:4px; cursor:pointer; text-transform:uppercase; letter-spacing:0.5px;">Annulla</button>
        </div>

        <!-- Mode selection -->
        <div id="autoconsumoModeSelect" style="padding:36px 30px; text-align:center;">
            <p style="color:#6c757d; margin-bottom:28px; font-size:0.95rem;">Scegli come applicare l'autoconsumo a questo tavolo:</p>
            <div style="display:flex; gap:20px; justify-content:center; flex-wrap:wrap;">
                <button id="btnAutoconsumoFull" style="padding:22px 40px; background:#6c757d; color:white; border:none; border-radius:8px; font-weight:700; font-size:0.95rem; cursor:pointer; text-transform:uppercase; min-width:220px;">
                    <div style="font-size:2rem; margin-bottom:8px;"><i class="fas fa-eraser"></i></div>
                    Tutto in autoconsumo
                    <div style="font-size:0.75rem; font-weight:400; margin-top:6px; opacity:0.85;">Segna l'intero tavolo come autoconsumo</div>
                </button>
                <button id="btnAutoconsumoPartial" style="padding:22px 40px; background:#17a2b8; color:white; border:none; border-radius:8px; font-weight:700; font-size:0.95rem; cursor:pointer; text-transform:uppercase; min-width:220px;">
                    <div style="font-size:2rem; margin-bottom:8px;"><i class="fas fa-users"></i></div>
                    Assegna per operatore
                    <div style="font-size:0.75rem; font-weight:400; margin-top:6px; opacity:0.85;">Assegna ogni piatto a un operatore specifico</div>
                </button>
            </div>
        </div>

        <!-- Partial assignment view -->
        <div id="autoconsumoPartialView" style="display:none; flex:1; overflow:hidden; padding:16px 20px; gap:14px; flex-direction:row; min-height:0;">
            <!-- Items list -->
            <div style="flex:1; display:flex; flex-direction:column; min-width:0;">
                <div style="font-weight:700; text-transform:uppercase; font-size:0.85rem; letter-spacing:1px; border-bottom:3px solid #dc3545; padding-bottom:8px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                    <span>Piatti dell'ordine</span>
                    <button id="btnSelectAllItems" style="background:#dee2e6; border:none; border-radius:4px; padding:3px 10px; font-size:0.75rem; font-weight:600; cursor:pointer;">Seleziona tutti</button>
                </div>
                <div id="autoconsumoItemsList" style="flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:6px;"></div>
            </div>

            <!-- Operators -->
            <div style="width:210px; flex-shrink:0; display:flex; flex-direction:column;">
                <div style="font-weight:700; text-transform:uppercase; font-size:0.85rem; letter-spacing:1px; border-bottom:3px solid #dc3545; padding-bottom:8px; margin-bottom:10px;">Assegna a</div>
                <div id="autoconsumoOperatorsList" style="flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:8px;"></div>
            </div>
        </div>

        <!-- Footer -->
        <div id="autoconsumoFooter" style="display:none; padding:14px 20px; border-top:2px solid #dee2e6; flex-shrink:0; justify-content:space-between; align-items:center; gap:10px;">
            <div id="autoconsumoLegend" style="font-size:0.82rem; color:#6c757d; flex:1;"></div>
            <div style="display:flex; gap:10px; flex-shrink:0;">
                <button id="btnAutoconsumoBack" style="padding:9px 20px; background:#dee2e6; border:none; border-radius:4px; font-weight:600; cursor:pointer; text-transform:uppercase; font-size:0.85rem;">Indietro</button>
                <button id="btnAutoconsumoConfirm" style="padding:9px 24px; background:#6c757d; color:white; border:none; border-radius:4px; font-weight:700; text-transform:uppercase; font-size:0.85rem; cursor:pointer;">
                    <i class="fas fa-check me-2"></i>Conferma autoconsumo
                </button>
            </div>
        </div>
    </div>
</div>
