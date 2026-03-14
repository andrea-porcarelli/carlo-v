@extends('app.layout')

@section('main-content')
    <div class="main-container">
        <!-- Header -->
        <nav class="navbar">
            <div class="container-fluid">
                <span class="navbar-brand">
                    <i class="fas fa-utensils"></i> CARLO V
                </span>
                <div>
                    <button class="btn btn-red active me-3" id="btnMainView">
                        <i class="fas fa-home me-2"></i> SALA PRINCIPALE
                    </button>
                    <button class="btn btn-red me-3" id="btnAddTable">
                        <i class="fas fa-plus me-2"></i> AGGIUNGI TAVOLI
                    </button>
                    <button class="btn btn-orange" id="btnBanco">
                        <i class="fas fa-store me-2"></i> VENDITA AL BANCO
                    </button>
                </div>
            </div>
        </nav>

        <!-- Main Restaurant View -->
        <div id="mainView" class="page-content active">
            <div class="row g-0">
                <!-- Center - Dining Area (10/12) -->
                <div class="col-sala">
                    <div class="dining-area">
                        <h5 class="dining-title">SALA RISTORANTE</h5>
                        <div class="dining-grid" id="tablesContainer">
                            <!-- Tables will be generated here -->
                        </div>
                    </div>
                </div>

                <!-- Right Panel - Occupied Summary (2/12) -->
                <div class="col-summary">
                    <div class="summary-panel">
                        <div class="summary-header">TAVOLI OCCUPATI</div>
                        <div id="occupiedSummary">
                            <div style="text-align:center;color:#6c757d;padding:20px;">Nessun tavolo occupato</div>
                        </div>
                        <div class="summary-stats">
                            <div class="quick-stats">
                                <div class="quick-stats-number" id="occupiedCount">0</div>
                                <div class="quick-stats-label">OCCUPATI</div>
                            </div>
                            <div class="quick-stats">
                                <div class="quick-stats-number" id="freeCount">20</div>
                                <div class="quick-stats-label">LIBERI</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modify Order Overlay (Full View with Menu) -->
            <div id="modifyOrderOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 1000;">
                <div style="position: relative; width: 100%; height: 100%; padding: 15px;">
                    <!-- Close Button -->
                    <button style="position: absolute; top: 15px; right: 15px; background: #dc3545; border: none; color: white; height: 36px; padding: 0 16px; cursor: pointer; font-size: 13px; font-weight: 700; z-index: 1001; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;" id="closeModifyBtn"><i class="fas fa-sign-out-alt"></i> Chiudi e invia</button>

                    <!-- Header -->
                    <div style="text-align: center; color: white; margin-bottom: 12px;">
                        <h2 style="margin: 0; font-size: 1.4rem; font-weight: 700;">
                            TAVOLO <span id="modifyTableNumber">-</span>
                        </h2>
                        <p style="margin: 2px 0 0 0; color: #6c757d; font-size: 0.85rem;" id="modifyCoversInfo">
                            <i class="fas fa-users" id="modifyCoversIcon"></i> <span id="modifyCoversCount">0</span><span id="modifyCoversLabel"> coperti</span>
                        </p>
                    </div>

                    <div style="display:flex; gap:10px; height:calc(100% - 80px);">
                        <!-- 4/12: Ordine -->
                        <div class="overlay-col-order">
                            <div class="overlay-order-header">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="font-size:1.5rem;font-weight:700;color:#dc3545;" id="modifySelectedTableNumber">-</span>
                                    <span style="font-size:0.8rem;color:#6c757d;text-transform:uppercase;letter-spacing:1px;">ORDINE CORRENTE</span>
                                </div>
                                <div>
                                    <span style="font-size:0.8rem;color:#6c757d;">TOTALE</span>
                                    <span id="modifyTotalAmount" style="font-size:1.3rem;font-weight:700;color:#dc3545;margin-left:8px;">€0.00</span>
                                </div>
                            </div>
                            <div id="modifyReceiptItems" style="flex:1;overflow-y:auto;padding:12px;">
                                <div class="empty-state">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p>Nessun ordine</p>
                                </div>
                            </div>
                        </div>

                        <!-- 7/12: Menu -->
                        <div class="overlay-col-menu">
                            <div id="modifyMenuContainer">
                                @livewire('dish-selector')
                            </div>
                        </div>

                        <!-- 1/12: Azioni verticali -->
                        <div class="overlay-col-actions">
                            <button class="action-btn-v" id="btnMarciaTavolo" style="background:#28a745;">
                                <i class="fas fa-play-circle"></i> MARCIA
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
                            <button class="action-btn-v" id="btnModifyFreeTable" style="background:#ffc107;color:#000;">
                                <i class="fas fa-door-open"></i> LIBERA
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

                    <div style="padding: 20px; max-height: 300px; overflow-y: auto;" id="receiptItems">
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
    </div>

    <!-- Autoconsumo Modal -->
    <x-autoconsumo-modal />
@endsection
