@extends('app.layout')

@section('main-content')
    <div class="main-container">
        <!-- Header -->
        <nav class="navbar">
            <div class="container-fluid">
                <span class="navbar-brand">
                    <i class="fas fa-utensils"></i> {{ \Illuminate\Support\Str::upper(\App\Models\Setting::getRestaurantName()) }}
                </span>
                <div>
                    <button class="btn me-3 js-fullscreen-btn" onclick="openFullscreen()" style="background:#007bff; color:white; border:none;">
                        <i class="fas fa-expand me-2"></i> SCHERMO INTERO
                    </button>
                    <button class="btn btn-red me-3" id="btnLogOperativo">
                        <i class="fas fa-chart-line me-2"></i> LOG OPERATIVO
                    </button>
                    @if($isAdmin)
                    <button class="btn btn-red me-3" id="btnAddTable">
                        <i class="fas fa-plus me-2"></i> AGGIUNGI TAVOLI
                    </button>
                    @endif
                    <button class="btn btn-orange" id="btnBanco">
                        <i class="fas fa-store me-2"></i> VENDITA AL BANCO
                    </button>
                </div>
            </div>
        </nav>

        <!-- Main Restaurant View -->
        <div id="mainView" class="page-content active">
            <div class="row g-0">
                <!-- Dining Area (full width) -->
                <div class="col-sala">
                    <div class="dining-area">
                        <h5 class="dining-title">SALA RISTORANTE</h5>
                        <div class="dining-grid" id="tablesContainer">
                            <!-- Tables will be generated here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modify Order Overlay (Full View with Menu) -->
            <div id="modifyOrderOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 1000;">
                <div style="width: 100%; height: 100%; padding: 15px; display:flex; flex-direction:column; gap:10px;">
                    <!-- Top bar -->
                    <div style="display:flex; justify-content:flex-end; flex-shrink:0; gap:8px;">
                        <button style="background: #6c757d; border: none; color: white; height: 36px; min-width: 220px; padding: 0 16px; cursor: pointer; font-size: 13px; font-weight: 700; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;" id="closeModifyNoPrintBtn"><i class="fas fa-times"></i> Chiudi senza stampare</button>
                        <button style="background: #dc3545; border: none; color: white; height: 36px; min-width: 220px; padding: 0 16px; cursor: pointer; font-size: 13px; font-weight: 700; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;" id="closeModifyBtn"><i class="fas fa-sign-out-alt"></i> Chiudi e invia</button>
                    </div>

                    <div style="display:flex; gap:10px; flex:1; min-height:0;">
                        <!-- Ordine corrente (sinistra) -->
                        <div class="overlay-col-order">
                            <div class="overlay-order-header">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="font-size:1.5rem;font-weight:700;color:white;" id="modifySelectedTableNumber">-</span>
                                    <div>
                                        <div style="font-size:0.8rem;color:rgba(255,255,255,0.75);text-transform:uppercase;letter-spacing:1px;">ORDINE CORRENTE</div>
                                        <div style="font-size:0.75rem;color:rgba(255,255,255,0.6);" id="modifyCoversInfo">
                                            <i class="fas fa-users" id="modifyCoversIcon"></i> <span id="modifyCoversCount">0</span><span id="modifyCoversLabel"> coperti</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="modifyReceiptItems" style="flex:1;min-height:0;overflow-y:auto;padding:12px;">
                                <div class="empty-state">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p>Nessun ordine</p>
                                </div>
                            </div>
                            <!-- Footer: sconto + totale -->
                            <div class="overlay-order-footer">
                                <div class="overlay-discount-row">
                                    <span class="discount-label"><i class="fas fa-tag me-1"></i>SCONTO</span>
                                    <div class="discount-controls">
                                        <button class="discount-type-btn active" id="discountTypePct"
                                            onclick="if(!document.getElementById('modifyDiscountInput').disabled){ this.classList.add('active'); document.getElementById('discountTypeVal').classList.remove('active'); }">%</button>
                                        <button class="discount-type-btn" id="discountTypeVal"
                                            onclick="if(!document.getElementById('modifyDiscountInput').disabled){ this.classList.add('active'); document.getElementById('discountTypePct').classList.remove('active'); }">€</button>
                                        <input type="number" id="modifyDiscountInput" min="0" step="0.5" placeholder="0">
                                        <button id="btnApplyDiscount" class="discount-apply-btn"
                                            onclick="tableOrdersManager.requestDiscountAuth()">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button id="btnResetDiscount" class="discount-reset-btn" style="display:none;"
                                            onclick="tableOrdersManager.resetDiscount()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="overlay-total-row">
                                    <span id="modifyOriginalAmount" class="original-total"></span>
                                    <div class="total-label">TOTALE</div>
                                    <div id="modifyTotalAmount" class="total-value">€0.00</div>
                                </div>
                            </div>
                        </div>

                        <!-- Menu con categorie integrate (centro + destra) -->
                        <div class="overlay-col-menu" style="padding:0; overflow:hidden;">
                            <div id="modifyMenuContainer" style="height:100%;">
                                @livewire('dish-selector')
                            </div>
                        </div>
                    </div>

                    <!-- Barra azioni in basso -->
                    <div class="overlay-actions-bar">
                        <button class="action-btn-v" id="btnMarciaTavolo" style="background:#28a745;">
                            <i class="fas fa-play-circle"></i> MARCIA
                        </button>
                        <button class="action-btn-v" id="btnInviaOrdine" style="background:#0d6efd; display:none;">
                            <i class="fas fa-paper-plane"></i> INVIA ORDINE
                        </button>
                        <button class="action-btn-v" id="btnPreconto" style="background:#17a2b8;">
                            <i class="fas fa-receipt"></i> PRE-CONTO
                        </button>
                        <button class="action-btn-v" id="btnModifyPayBill" style="background:#dc3545;">
                            <i class="fas fa-money-bill"></i> INCASSA
                        </button>
                        <button class="action-btn-v" id="btnModifyFreeAmount" style="background:#6c757d;">
                            <i class="fas fa-eraser"></i> AUTO
                        </button>
                        <button class="action-btn-v" id="btnModifyFreeTable" style="background:#20c997;color:#fff;">
                            <i class="fas fa-cash-register"></i> CHIUDI CONTO
                        </button>
                        <button class="action-btn-v" id="btnChiudiTavolo" style="background:#20c997;color:#fff; display:none;">
                            <i class="fas fa-door-open"></i> CHIUDI TAVOLO
                        </button>
                        <button class="action-btn-v" id="btnSpostaTavolo" style="background:#fd7e14;">
                            <i class="fas fa-exchange-alt"></i> SPOSTA
                        </button>
                        <button class="action-btn-v" id="btnRistampaOrdine" style="background:#343a40;">
                            <i class="fas fa-print"></i> RISTAMPA
                        </button>
                        <button class="action-btn-v" id="btnModifyComunica" style="background:#6f42c1;">
                            <i class="fas fa-bullhorn"></i> COMUNICA
                        </button>
                    </div>
                </div>
            </div>

            <!-- Receipt Modal-like Overlay (for CONTO button) -->
            <div id="receiptOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000;">
                <div style="position: absolute; right: 20px; top: 80px; width: 400px; background: white; padding: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    <div class="table-info-card">
                        <div class="table-info-number" id="selectedTableNumber">-</div>
                        <div class="table-info-status">
                            TAVOLO SELEZIONATO
                            <span id="coversInfo" style="display: none; margin-left: 10px; font-size: 0.9rem; color: #666;">
                                <i class="fas fa-users" id="coversIcon"></i> <span id="coversCount">0</span><span id="coversLabel"> coperti</span>
                            </span>
                        </div>
                        <button style="position: absolute; top: 10px; right: 10px; background: #dc3545; border: none; color: white; width: 30px; height: 30px; cursor: pointer;" id="closeReceiptBtn">×</button>
                    </div>

                    <div style="padding: 20px;" id="receiptItems">
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <p>Nessun ordine</p>
                        </div>
                    </div>

                    <div class="total-section">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="font-size: 1.2rem; font-weight: 600;">TOTALE:</span>
                            <span id="totalAmount" class="total-amount">€0.00</span>
                        </div>
                    </div>

                    <div style="padding: 20px;">
                        <button class="action-btn" id="btnPayBill">
                            <i class="fas fa-money-bill"></i> INCASSA
                        </button>
                        <button class="action-btn" id="btnFreeAmount">
                            <i class="fas fa-eraser"></i> AUTOCONSUMO
                        </button>
                        <button class="action-btn" id="btnFreeTable">
                            <i class="fas fa-door-open"></i> LIBERA
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <x-product-modal :isMobile="false" />

    <!-- Operator Authentication Modal -->
    <x-operator-auth-modal />

    <!-- Covers Selection Modal -->
    <x-covers-modal />

    <!-- PreConto Modal -->
    <x-preconto-modal />

    <!-- Comunica Modal -->
    <x-comunica-modal />

    <!-- Payment Method Modal -->
    <x-payment-method-modal />

    <!-- Invoice Modal -->
    <x-invoice-modal />

    <!-- Cash Drawer Overlay -->
    <div id="cashDrawerOverlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.88); align-items:center; justify-content:center; flex-direction:column;">
        <div style="background:#1a1a2e; border-radius:16px; padding:48px 40px; max-width:420px; width:90%; text-align:center; box-shadow:0 24px 80px rgba(0,0,0,0.6); border:1px solid rgba(255,255,255,0.08);">
            <div style="font-size:3rem; margin-bottom:24px;">💵</div>
            <h2 style="color:#fff; margin:0 0 8px 0; font-size:1.4rem; font-weight:700; letter-spacing:0.5px;">Pagamento in corso</h2>
            <p id="cashDrawerAmount" style="color:#a0aec0; font-size:1.1rem; margin:0 0 32px 0;"></p>

            <div style="display:flex; justify-content:center; margin-bottom:28px;">
                <div style="width:48px; height:48px; border:4px solid rgba(255,255,255,0.15); border-top-color:#4299e1; border-radius:50%; animation:cashDrawerSpin 0.8s linear infinite;"></div>
            </div>

            <p id="cashDrawerStatus" style="color:#cbd5e0; font-size:0.95rem; margin:0 0 32px 0; min-height:1.4em;">In attesa del pagamento dalla cassa automatica...</p>

            <button id="cashDrawerCancelBtn"
                onclick="tableOrdersManager.cancelCashDrawerTransaction()"
                style="background:transparent; border:2px solid #e53e3e; color:#e53e3e; padding:12px 28px; border-radius:8px; font-size:0.95rem; font-weight:600; cursor:pointer; letter-spacing:0.3px; transition:all 0.15s;">
                Annulla transazione
            </button>

            <div id="cashDrawerFallbackSection" style="display:none; margin-top:20px; padding-top:20px; border-top:1px solid rgba(255,255,255,0.1);">
                <p style="color:#f6ad55; font-size:0.9rem; margin:0 0 14px 0; line-height:1.5;">
                    ⚠️ La cassa automatica non risponde.<br>Puoi chiudere il tavolo manualmente.
                </p>
                <button id="cashDrawerFallbackBtn"
                    onclick="tableOrdersManager._executeCashDrawerFallback()"
                    style="background:transparent; border:2px solid #f6ad55; color:#f6ad55; padding:12px 28px; border-radius:8px; font-size:0.95rem; font-weight:600; cursor:pointer; letter-spacing:0.3px; transition:all 0.15s;">
                    Chiudi comunque senza cassa
                </button>
            </div>
        </div>
    </div>
    <style>
        @keyframes cashDrawerSpin { to { transform: rotate(360deg); } }
    </style>

    <!-- Revolut Payment Overlay -->
    <div id="revolutPaymentOverlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.88); align-items:center; justify-content:center;">
        <div style="background:#0f172a; border-radius:16px; padding:44px 40px; max-width:460px; width:90%; text-align:center; box-shadow:0 24px 80px rgba(0,0,0,0.6); border:1px solid rgba(255,255,255,0.08);">
            <div style="font-size:3rem; margin-bottom:14px;">💳</div>
            <h2 style="color:#fff; margin:0 0 6px 0; font-size:1.4rem; font-weight:700; letter-spacing:0.5px;">In attesa di pagamento</h2>
            <p id="revolutPaymentTerminal" style="color:#94a3b8; font-size:0.85rem; margin:0;"></p>
            <p id="revolutPaymentAmount" style="color:#e2e8f0; font-size:1.6rem; margin:10px 0 24px 0; font-weight:700;"></p>

            <div style="display:flex; justify-content:center; margin-bottom:22px;">
                <div style="width:48px; height:48px; border:4px solid rgba(255,255,255,0.15); border-top-color:#22c55e; border-radius:50%; animation:cashDrawerSpin 0.8s linear infinite;"></div>
            </div>

            <p id="revolutPaymentStatus" style="color:#cbd5e1; font-size:0.95rem; margin:0 0 8px 0; min-height:1.4em;">Avvicinare la carta al terminale Revolut</p>
            <p id="revolutPaymentCountdown" style="color:#64748b; font-size:0.85rem; margin:0 0 24px 0; min-height:1.2em;"></p>

            <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                <button id="revolutPaymentCancelBtn"
                    onclick="tableOrdersManager.cancelRevolutPayment()"
                    style="background:transparent; border:2px solid #ef4444; color:#ef4444; padding:12px 28px; border-radius:8px; font-size:0.95rem; font-weight:600; cursor:pointer; letter-spacing:0.3px; transition:all 0.15s;">
                    Annulla transazione
                </button>
                <button id="revolutPaymentMockBtn"
                    onclick="tableOrdersManager.mockCompleteRevolutPayment()"
                    style="display:none; background:transparent; border:2px solid #22c55e; color:#22c55e; padding:12px 28px; border-radius:8px; font-size:0.95rem; font-weight:600; cursor:pointer; letter-spacing:0.3px; transition:all 0.15s;">
                    Simula pagamento OK
                </button>
            </div>

            <div id="revolutPaymentTimeoutSection" style="display:none; margin-top:20px; padding-top:20px; border-top:1px solid rgba(255,255,255,0.1);">
                <p style="color:#fbbf24; font-size:0.9rem; margin:0; line-height:1.5;">
                    ⚠️ Il pagamento sta impiegando più del previsto.<br>Verifica sul terminale o annulla per riprovare.
                </p>
            </div>
        </div>
    </div>

    <!-- Cash Drawer Fallback Alert (rimane visibile fino alla conferma dell'operatore) -->
    <div id="cashDrawerFallbackAlert" style="display:none; position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,0.92); align-items:center; justify-content:center;">
        <div style="background:#2d1a00; border:2px solid #f6ad55; border-radius:16px; padding:40px 36px; max-width:440px; width:90%; text-align:center; box-shadow:0 24px 80px rgba(0,0,0,0.7);">
            <div style="font-size:3rem; margin-bottom:20px;">⚠️</div>
            <h2 style="color:#f6ad55; margin:0 0 16px 0; font-size:1.3rem; font-weight:700; letter-spacing:0.3px;">Cassa automatica non raggiungibile</h2>
            <p style="color:#fbd38d; font-size:1rem; margin:0 0 12px 0; line-height:1.6;">
                Il tavolo è stato chiuso e il pagamento è stato registrato come <strong>contanti</strong>.
            </p>
            <p style="color:#fbd38d; font-size:1rem; margin:0 0 32px 0; line-height:1.6;">
                La cassa automatica non ha confermato l'operazione.<br>
                <strong>Verifica manualmente l'incasso.</strong>
            </p>
            <button
                onclick="document.getElementById('cashDrawerFallbackAlert').style.display='none'"
                style="background:#f6ad55; border:none; color:#1a1a00; padding:14px 40px; border-radius:8px; font-size:1rem; font-weight:700; cursor:pointer; letter-spacing:0.3px;">
                Ho verificato, chiudi
            </button>
        </div>
    </div>

    <!-- Remove Reason Modal -->
    <div id="removeReasonModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:3000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:8px; padding:30px; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.5);">
            <h4 style="margin:0 0 20px 0; font-weight:700; text-transform:uppercase; text-align:center; color:#000;">
                <i class="fas fa-trash-alt me-2" style="color:#dc3545;"></i>Motivo della rimozione
            </h4>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                <button class="remove-reason-btn" data-reason="Rientro" style="padding:12px 8px; background:#f8f9fa; border:2px solid #dee2e6; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.9rem; transition:all 0.15s;">Rientro</button>
                <button class="remove-reason-btn" data-reason="Il cliente ha sostituito" style="padding:12px 8px; background:#f8f9fa; border:2px solid #dee2e6; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.9rem; transition:all 0.15s;">Il cliente ha sostituito</button>
                <button class="remove-reason-btn" data-reason="Errore nel piatto" style="padding:12px 8px; background:#f8f9fa; border:2px solid #dee2e6; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.9rem; transition:all 0.15s;">Errore nel piatto</button>
                <button class="remove-reason-btn" data-reason="Omaggiato" style="padding:12px 8px; background:#f8f9fa; border:2px solid #dee2e6; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.9rem; transition:all 0.15s;">Omaggiato</button>
                <button class="remove-reason-btn" data-reason="Errore sala" style="padding:12px 8px; background:#f8f9fa; border:2px solid #dee2e6; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.9rem; transition:all 0.15s;">Errore sala</button>
                <button class="remove-reason-btn" data-reason="Errore cucina" style="padding:12px 8px; background:#f8f9fa; border:2px solid #dee2e6; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.9rem; transition:all 0.15s;">Errore cucina</button>
            </div>
            <button id="cancelRemoveReason" style="width:100%; padding:12px; background:#6c757d; border:none; color:white; font-weight:600; text-transform:uppercase; border-radius:6px; cursor:pointer;">ANNULLA</button>
        </div>
    </div>

    <!-- Move Table Modal -->
    <div id="moveTableModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:3000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:8px; padding:28px; max-width:480px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.5);">
            <h4 style="margin:0 0 6px 0; font-weight:700; text-transform:uppercase; color:#000;">
                <i class="fas fa-exchange-alt me-2" style="color:#fd7e14;"></i>Sposta Tavolo
            </h4>
            <p style="margin:0 0 18px 0; color:#6c757d; font-size:0.9rem;">
                Tavolo <strong id="moveSourceTableNumber">-</strong> → seleziona destinazione
            </p>
            <div id="moveTableGrid" style="display:grid; grid-template-columns:repeat(6,1fr); gap:8px; max-height:280px; overflow-y:auto; margin-bottom:18px;">
                <!-- Populated by JS -->
            </div>
            <button id="cancelMoveTable" style="width:100%; padding:11px; background:#6c757d; border:none; color:white; font-weight:600; text-transform:uppercase; border-radius:6px; cursor:pointer;">ANNULLA</button>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="notification">
        <span id="notificationText">Operazione completata!</span>
        <span id="notificationClose" style="display:none; margin-left:auto; font-weight:700; cursor:pointer; opacity:0.6;" onclick="document.getElementById('notification').style.display='none'">✕</span>
    </div>

    <!-- Autoconsumo Modal -->
    <x-autoconsumo-modal />

    <!-- Operational Log Modal -->
    <x-operational-log-modal />

    <!-- Admin Auth Modal -->
    <div id="adminAuthModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:10000; align-items:center; justify-content:center;">
        <div style="background:#1a1a1a; border:2px solid #ffc107; border-radius:8px; padding:30px; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(255,193,7,0.2);">
            <h4 style="margin:0 0 8px 0; font-weight:700; text-transform:uppercase; text-align:center; color:#ffc107;">
                <i class="fas fa-shield-alt me-2"></i>Accesso Amministratore
            </h4>
            <p style="color:#aaa; font-size:0.85rem; margin-bottom:20px; text-align:center;">Inserisci la password admin per accedere al log operativo</p>
            <div style="margin-bottom:12px;">
                <input type="password" id="adminAuthPassword" placeholder="Password admin" inputmode="numeric" maxlength="10" autocomplete="off"
                    style="width:100%; padding:12px 16px; background:#2a2a2a; border:1px solid #555; border-radius:4px; color:#fff; font-size:1rem; box-sizing:border-box;">
            </div>

            <div class="admin-numpad" id="adminAuthNumpad">
                <button type="button" class="admin-numpad-btn" data-key="1">1</button>
                <button type="button" class="admin-numpad-btn" data-key="2">2</button>
                <button type="button" class="admin-numpad-btn" data-key="3">3</button>
                <button type="button" class="admin-numpad-btn" data-key="4">4</button>
                <button type="button" class="admin-numpad-btn" data-key="5">5</button>
                <button type="button" class="admin-numpad-btn" data-key="6">6</button>
                <button type="button" class="admin-numpad-btn" data-key="7">7</button>
                <button type="button" class="admin-numpad-btn" data-key="8">8</button>
                <button type="button" class="admin-numpad-btn" data-key="9">9</button>
                <button type="button" class="admin-numpad-btn" data-key="0">0</button>
                <button type="button" class="admin-numpad-btn admin-numpad-clear" id="adminAuthNumpadClear">
                    <i class="fas fa-backspace"></i>
                </button>
            </div>

            <div id="adminAuthError" style="display:none; background:rgba(255,193,7,0.1); border:1px solid #ffc107; border-radius:4px; padding:10px 14px; color:#ffc107; font-size:0.85rem; margin-bottom:14px;">
                <i class="fas fa-exclamation-circle me-2"></i><span id="adminAuthErrorText"></span>
            </div>
            <div style="display:flex; gap:10px;">
                <button id="cancelAdminAuth" style="flex:1; padding:12px; background:#444; border:none; color:white; font-weight:600; text-transform:uppercase; border-radius:6px; cursor:pointer;">ANNULLA</button>
                <button id="confirmAdminAuth" style="flex:1; padding:12px; background:#ffc107; border:none; color:#000; font-weight:700; text-transform:uppercase; border-radius:6px; cursor:pointer;"><i class="fas fa-sign-in-alt me-1"></i>ACCEDI</button>
            </div>
        </div>
    </div>

    <style>
        .admin-numpad {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin: 0 0 14px 0;
            padding: 14px;
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
        }
        .admin-numpad-btn {
            padding: 12px 6px;
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 4px;
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-numpad-btn:hover { background: #3a3a3a; border-color: #ffc107; }
        .admin-numpad-btn:active { background: #4a4a4a; transform: scale(0.95); }
        .admin-numpad-clear {
            grid-column: span 5;
            background: #dc3545;
            border-color: #c82333;
            color: #fff;
        }
        .admin-numpad-clear:hover { background: #c82333; }
        @media (max-width: 768px) {
            .admin-numpad { grid-template-columns: repeat(6, 1fr); }
            .admin-numpad-clear { grid-column: span 2; }
        }
    </style>

    <script>
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('adminAuthPassword');
            if (!input) return;
            document.querySelectorAll('#adminAuthNumpad .admin-numpad-btn:not(.admin-numpad-clear)').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const max = parseInt(input.getAttribute('maxlength') || '10', 10);
                    if (input.value.length < max) input.value += btn.dataset.key;
                });
            });
            const clearBtn = document.getElementById('adminAuthNumpadClear');
            if (clearBtn) clearBtn.addEventListener('click', (e) => {
                e.preventDefault();
                input.value = input.value.slice(0, -1);
            });
        });
    })();
    </script>
@endsection
