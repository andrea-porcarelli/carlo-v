<div id="operationalLogModal" style="display:none; position:fixed; inset:0; z-index:9998; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
    <div style="background:#1a1a1a; color:#eee; border:2px solid #ffc107; border-radius:10px; width:1100px; max-width:96%; max-height:92vh; display:flex; flex-direction:column; box-shadow:0 24px 80px rgba(0,0,0,0.6);">

        <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 22px; border-bottom:1px solid #333;">
            <h4 style="margin:0; font-weight:700; color:#ffc107; letter-spacing:1px; text-transform:uppercase;">
                <i class="fas fa-chart-line me-2"></i>Log Operativo
            </h4>
            <div style="display:flex; align-items:center; gap:10px;">
                <label for="logOpDate" style="margin:0; font-size:0.85rem; color:#aaa;">GIORNATA</label>
                <input type="date" id="logOpDate"
                    style="background:#2a2a2a; color:#fff; border:1px solid #555; border-radius:4px; padding:6px 10px; font-size:0.9rem;">
                <button id="closeOperationalLog"
                    style="background:#dc3545; border:none; color:white; width:34px; height:34px; border-radius:4px; cursor:pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div id="logOpBody" style="overflow-y:auto; padding:18px 22px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">

            <div class="logop-card" style="background:#222; border:1px solid #333; border-radius:8px; padding:14px;">
                <h6 style="color:#28a745; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin:0 0 12px 0;">
                    <i class="fas fa-cash-register me-2"></i>Venduto
                </h6>
                <div id="logOpVenduto" style="font-size:0.9rem;">
                    <div style="color:#888; text-align:center; padding:20px 0;">Caricamento…</div>
                </div>
            </div>

            <div class="logop-card" style="background:#222; border:1px solid #333; border-radius:8px; padding:14px;">
                <h6 style="color:#ffc107; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin:0 0 12px 0;">
                    <i class="fas fa-hourglass-half me-2"></i>Da incassare <span style="color:#888; font-weight:400; font-size:0.75rem; text-transform:none;">(tavoli aperti)</span>
                </h6>
                <div id="logOpDaIncassare" style="font-size:0.9rem;">
                    <div style="color:#888; text-align:center; padding:20px 0;">Caricamento…</div>
                </div>
            </div>

            <div id="logOpVendutoDetail" style="grid-column:1 / -1; display:none;"></div>

            <div class="logop-card" style="background:#222; border:1px solid #333; border-radius:8px; padding:14px; grid-column:1 / -1;">
                <h6 style="color:#dc3545; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin:0 0 12px 0;">
                    <i class="fas fa-trash-alt me-2"></i>Articoli cancellati <span id="logOpCancellatiCount" style="color:#888; font-weight:400; font-size:0.8rem; text-transform:none;"></span>
                </h6>
                <div id="logOpCancellati" style="font-size:0.85rem; max-height:220px; overflow-y:auto;">
                    <div style="color:#888; text-align:center; padding:20px 0;">Caricamento…</div>
                </div>
            </div>

            <div class="logop-card" style="background:#222; border:1px solid #333; border-radius:8px; padding:14px; grid-column:1 / -1;">
                <h6 style="color:#17a2b8; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin:0 0 12px 0;">
                    <i class="fas fa-edit me-2"></i>Articoli modificati <span id="logOpModificatiCount" style="color:#888; font-weight:400; font-size:0.8rem; text-transform:none;"></span>
                </h6>
                <div id="logOpModificati" style="font-size:0.85rem; max-height:260px; overflow-y:auto;">
                    <div style="color:#888; text-align:center; padding:20px 0;">Caricamento…</div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .logop-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed #333; }
        .logop-row .label { color:#bbb; }
        .logop-row .value { color:#fff; font-weight:600; font-variant-numeric:tabular-nums; }
        .logop-row.total { border-top:2px solid #ffc107; margin-top:6px; padding-top:10px; }
        .logop-row.total .label, .logop-row.total .value { color:#ffc107; font-size:1rem; }
        .logop-row-clickable { cursor:pointer; border-radius:4px; padding:6px 4px; margin:0 -4px; transition:background .12s; }
        .logop-row-clickable:hover { background:rgba(255,255,255,0.05); }
        .logop-row-clickable.active { background:rgba(40,167,69,0.12); }
        .logop-row-clickable.active .label { color:#fff; }
        .logop-table { width:100%; border-collapse:collapse; }
        .logop-table th { text-align:left; color:#aaa; font-weight:600; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; padding:6px 8px; border-bottom:1px solid #333; position:sticky; top:0; background:#222; }
        .logop-table td { padding:6px 8px; border-bottom:1px solid #2a2a2a; color:#eee; }
        .logop-table tr:hover td { background:#2a2a2a; }
        .logop-empty { color:#666; text-align:center; padding:20px 0; font-style:italic; }
    </style>
</div>