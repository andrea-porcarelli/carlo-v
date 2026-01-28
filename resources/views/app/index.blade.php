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
                    <button class="btn btn-red" id="btnAddTable">
                        <i class="fas fa-plus me-2"></i> AGGIUNGI TAVOLI
                    </button>
                </div>
            </div>
        </nav>

        <!-- Main Restaurant View -->
        <div id="mainView" class="page-content active">
            <div class="row g-0">
                <!-- Left Panel - Menu (30%) -->
                <div class="col-left">
                    @livewire('dish-selector')

                    <!-- Temporary Cart -->
                    <div id="temporaryCart" style="display: none; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; border: 2px solid #dc3545;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h6 style="margin: 0; color: #dc3545; font-weight: 700;">
                                <i class="fas fa-shopping-cart me-2"></i>ORDINE IN PREPARAZIONE
                            </h6>
                            <button id="clearCart" class="btn btn-sm" style="background: #6c757d; color: white; border: none; padding: 4px 8px;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div id="cartItems" style="max-height: 200px; overflow-y: auto; margin-bottom: 10px;">
                            <!-- Cart items will be added here -->
                        </div>
                        <button id="confirmCart" class="btn-red" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 600;">
                            <i class="fas fa-check me-2"></i>CONFERMA ORDINE
                        </button>
                    </div>
                </div>

                <!-- Center - Dining Area (60%) -->
                <div class="col-center">
                    <div class="dining-area">
                        <h5 class="dining-title">SALA RISTORANTE</h5>
                        <div class="dining-grid" id="tablesContainer">
                            <!-- Tables will be generated here -->
                        </div>
                    </div>
                </div>

                <!-- Right Panel - Quick Controls (10%) -->
                <div class="col-right">
                    <div class="mini-panel">
                        <div class="quick-stats">
                            <div class="quick-stats-number" id="occupiedCount">0</div>
                            <div class="quick-stats-label">OCCUPATI</div>
                        </div>

                        <div class="quick-stats">
                            <div class="quick-stats-number" id="freeCount">20</div>
                            <div class="quick-stats-label">LIBERI</div>
                        </div>

                        <div class="mini-control" id="btnModifyTable" disabled>
                            <i class="fas fa-edit"></i>
                            <div class="mini-control-label">GESTISCI</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modify Order Overlay (Full View with Menu) -->
            <div id="modifyOrderOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 1000;">
                <div style="position: relative; width: 100%; height: 100%; padding: 20px;">
                    <!-- Close Button -->
                    <button style="position: absolute; top: 20px; right: 20px; background: #dc3545; border: none; color: white; width: 40px; height: 40px; cursor: pointer; font-size: 24px; z-index: 1001;" id="closeModifyBtn">×</button>

                    <!-- Header -->
                    <div style="text-align: center; color: white; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 2rem; font-weight: 700;">
                            MODIFICA TAVOLO <span id="modifyTableNumber">-</span>
                        </h2>
                        <p style="margin: 5px 0 0 0; color: #6c757d;" id="modifyCoversInfo">
                            <i class="fas fa-users" id="modifyCoversIcon"></i> <span id="modifyCoversCount">0</span><span id="modifyCoversLabel"> coperti</span>
                        </p>
                    </div>

                    <div style="display: flex; gap: 20px; height: calc(100% - 100px);">
                        <!-- Left: Menu Panel -->
                        <div style="flex: 0 0 28%; background: white; padding: 15px; overflow-y: auto; border-radius: 8px;">
                            <h3 style="color: #000; font-weight: 700; margin-bottom: 15px; border-bottom: 3px solid #dc3545; padding-bottom: 8px; font-size: 1.1rem;">
                                <i class="fas fa-utensils me-2"></i> MENU
                            </h3>
                            <div id="modifyMenuContainer">
                                @livewire('dish-selector')
                            </div>

                            <!-- Temporary Cart in Modify View -->
                            <div id="temporaryCartModify" style="display: none; margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 4px; border: 2px solid #dc3545;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <h6 style="margin: 0; color: #dc3545; font-weight: 700; font-size: 0.85rem;">
                                        <i class="fas fa-shopping-cart me-2"></i>ORDINE IN PREPARAZIONE
                                    </h6>
                                    <button id="clearCartModify" class="btn btn-sm" style="background: #6c757d; color: white; border: none; padding: 3px 6px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div id="cartItemsModify" style="max-height: 150px; overflow-y: auto; margin-bottom: 8px;">
                                    <!-- Cart items will be added here -->
                                </div>
                                <button id="confirmCartModify" class="btn-red" style="width: 100%; padding: 10px; font-size: 13px; font-weight: 600;">
                                    <i class="fas fa-check me-2"></i>CONFERMA ORDINE
                                </button>
                            </div>
                        </div>

                        <!-- Right: Order Summary -->
                        <div style="flex: 1; background: white; padding: 0; border-radius: 8px; display: flex; flex-direction: column; min-width: 0;">
                            <!-- Compact Header -->
                            <div style="background: #000; color: white; padding: 12px 20px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <span style="font-size: 1.8rem; font-weight: 700; color: #dc3545;" id="modifySelectedTableNumber">-</span>
                                    <span style="font-size: 0.85rem; color: #6c757d; text-transform: uppercase; letter-spacing: 1px;">ORDINE CORRENTE</span>
                                </div>
                                <div style="text-align: right;">
                                    <span style="font-size: 0.85rem; color: #6c757d;">TOTALE</span>
                                    <span id="modifyTotalAmount" style="font-size: 1.5rem; font-weight: 700; color: #dc3545; margin-left: 10px;">€0.00</span>
                                </div>
                            </div>

                            <!-- Order Items - Maximum Space -->
                            <div style="padding: 15px 20px; flex: 1; overflow-y: auto;" id="modifyReceiptItems">
                                <div class="empty-state">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p>Nessun ordine</p>
                                </div>
                            </div>

                            <!-- Compact Action Bar -->
                            <div style="padding: 12px 20px; background: #f8f9fa; border-top: 2px solid #dee2e6; display: flex; gap: 10px; flex-wrap: wrap;">
                                <button class="action-btn-compact" id="btnMarciaTavolo" style="background: #28a745;">
                                    <i class="fas fa-play-circle"></i> MARCIA
                                </button>
                                <button class="action-btn-compact" id="btnPreconto" style="background: #17a2b8;">
                                    <i class="fas fa-receipt"></i> PRE-CONTO
                                </button>
                                <button class="action-btn-compact" id="btnModifyPayBill" style="background: #dc3545;">
                                    <i class="fas fa-money-bill"></i> INCASSA
                                </button>
                                <button class="action-btn-compact" id="btnModifyClearBill" style="background: #6c757d;">
                                    <i class="fas fa-eraser"></i> SVUOTA
                                </button>
                                <button class="action-btn-compact" id="btnModifyFreeTable" style="background: #ffc107; color: #000;">
                                    <i class="fas fa-door-open"></i> LIBERA
                                </button>
                                <button class="action-btn-compact" id="btnModifyComunica" style="background: #6f42c1;">
                                    <i class="fas fa-bullhorn"></i> COMUNICA
                                </button>
                            </div>
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
                        <button class="action-btn" id="btnClearBill">
                            <i class="fas fa-eraser"></i> SVUOTA
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

    <!-- Notification -->
    <div id="notification" class="notification">
        <span id="notificationText">Operazione completata!</span>
    </div>
@endsection
