@extends('app.mobile.layout')

@section('main-content')
    <div class="mobile-container">
        <!-- Mobile Header -->
        <header class="mobile-header">
            <div class="mobile-header-top">
                <div class="mobile-logo">
                    <i class="fas fa-utensils"></i> CARLO V
                </div>
                <div class="mobile-header-stats">
                    <div class="stat-badge stat-occupied">
                        <span id="occupiedCountMobile">0</span>
                    </div>
                    <div class="stat-badge stat-free">
                        <span id="freeCountMobile">20</span>
                    </div>
                </div>
            </div>
            <div class="mobile-header-nav">
                <button class="mobile-nav-btn active" id="btnMainViewMobile">
                    <i class="fas fa-home"></i>
                    <span>SALA</span>
                </button>
                <button class="mobile-nav-btn" id="btnAddTableMobile">
                    <i class="fas fa-plus"></i>
                    <span>AGGIUNGI</span>
                </button>
                <button class="mobile-nav-btn" id="btnMenuMobile">
                    <i class="fas fa-utensils"></i>
                    <span>MENU</span>
                </button>
            </div>
        </header>

        <!-- Main Restaurant View (Mobile) -->
        <div id="mainViewMobile" class="mobile-page active">
            <!-- Dining Area -->
            <div class="mobile-dining-area">
                <div class="mobile-section-title">
                    <i class="fas fa-table me-2"></i> SALA RISTORANTE
                </div>
                <div class="mobile-tables-grid" id="tablesContainerMobile">
                    <!-- Tables will be generated here -->
                </div>
            </div>
        </div>

        <!-- Menu View (Mobile) -->
        <div id="menuViewMobile" class="mobile-page">
            <div class="mobile-section-title">
                <i class="fas fa-utensils me-2"></i> MENU
            </div>

            <!-- Menu will be loaded via Livewire or AJAX -->
            <div class="mobile-menu-container">
                @livewire('dish-selector')
            </div>

            <!-- Temporary Cart (Mobile) -->
            <div id="temporaryCartMobile" class="mobile-cart-container" style="display: none;">
                <div class="mobile-cart-header">
                    <span><i class="fas fa-shopping-cart"></i> CARRELLO</span>
                    <button id="clearCartMobile" class="mobile-cart-clear-btn">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div id="cartItemsMobile" class="mobile-cart-items">
                    <!-- Cart items will be added here -->
                </div>
                <button id="confirmCartMobile" class="mobile-action-btn btn-success">
                    <i class="fas fa-check"></i> CONFERMA ORDINE
                </button>
            </div>
        </div>

        <!-- Bottom Action Bar (Floating) -->
        <div class="mobile-action-bar" id="mobileActionBar" style="display: none;">
            <div class="mobile-action-bar-content">
                <div class="selected-table-info">
                    <div class="selected-table-number">
                        <span class="table-badge" id="selectedTableNumberMobile">-</span>
                    </div>
                    <div class="selected-table-total" id="selectedTableTotalMobile"></div>
                </div>
                <button class="mobile-manage-btn" id="btnManageTableMobile">
                    <i class="fas fa-cog"></i> GESTISCI
                </button>
            </div>
        </div>
    </div>

    <!-- Manage Table Modal (Mobile) -->
    <div id="manageModalMobile" class="mobile-modal">
        <div class="mobile-modal-content mobile-modal-fullheight">
            <div class="mobile-modal-header mobile-modal-header-compact">
                <div class="mobile-modal-title">
                    <div class="table-number-medium">
                        TAVOLO <span id="manageTableNumberMobile">-</span>
                    </div>
                    <div class="table-status-text" id="manageCoversInfoMobile">
                        <i class="fas fa-users"></i> <span id="manageCoversMobile">0</span> coperti
                    </div>
                </div>
                <button class="mobile-close-btn" id="closeManageModalMobile">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Add Product Button -->
            <div class="mobile-add-product-bar">
                <button class="mobile-add-product-btn" id="btnAddProductMobile">
                    <i class="fas fa-plus-circle"></i> AGGIUNGI PRODOTTO
                </button>
            </div>

            <!-- Order Items -->
            <div class="mobile-modal-body" id="manageReceiptItemsMobile">
                <div class="mobile-empty-state-small">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Nessun ordine</p>
                </div>
            </div>

            <!-- Total -->
            <div class="mobile-total-bar">
                <span class="mobile-total-label">TOTALE</span>
                <span id="manageTotalAmountMobile" class="mobile-total-amount">€0.00</span>
            </div>

            <!-- Actions Grid -->
            <div class="mobile-modal-footer mobile-actions-grid">
                <button class="mobile-grid-btn btn-green" id="btnMarciaMobile">
                    <i class="fas fa-play-circle"></i>
                    <span>MARCIA</span>
                </button>
                <button class="mobile-grid-btn btn-cyan" id="btnPrecontoMobile">
                    <i class="fas fa-receipt"></i>
                    <span>PRE-CONTO</span>
                </button>
                <button class="mobile-grid-btn btn-red" id="btnPayBillMobile">
                    <i class="fas fa-money-bill"></i>
                    <span>INCASSA</span>
                </button>
                <button class="mobile-grid-btn btn-gray" id="btnClearBillMobile">
                    <i class="fas fa-eraser"></i>
                    <span>SVUOTA</span>
                </button>
                <button class="mobile-grid-btn btn-yellow" id="btnFreeTableMobile">
                    <i class="fas fa-door-open"></i>
                    <span>LIBERA</span>
                </button>
                <button class="mobile-grid-btn btn-purple" id="btnComunicaMobile">
                    <i class="fas fa-bullhorn"></i>
                    <span>COMUNICA</span>
                </button>
            </div>
        </div>
    </div>

    <!-- PreConto Modal (Mobile) -->
    <div id="precontoModalMobile" class="mobile-modal">
        <div class="mobile-modal-content">
            <div class="mobile-modal-header">
                <div class="mobile-modal-title">
                    <div class="table-number-large">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="table-status-text">PRE-CONTO</div>
                </div>
                <button class="mobile-close-btn" id="closePrecontoMobile">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mobile-modal-body">
                <div class="mobile-preconto-info">
                    <div class="preconto-table">Tavolo <span id="precontoTableNumberMobile">-</span></div>
                    <div class="preconto-total">Totale: <span id="precontoTotalAmountMobile">€0.00</span></div>
                </div>

                <div class="mobile-form-group">
                    <label class="mobile-form-label">Tipo di PreConto</label>
                    <div class="mobile-radio-group">
                        <label class="mobile-radio-item">
                            <input type="radio" name="precontoTypeMobile" value="full" checked>
                            <span class="radio-label">Conto Intero</span>
                        </label>
                        <label class="mobile-radio-item">
                            <input type="radio" name="precontoTypeMobile" value="split">
                            <span class="radio-label">Dividi per Persone</span>
                        </label>
                    </div>
                </div>

                <div id="splitCountContainerMobile" class="mobile-form-group" style="display: none;">
                    <label class="mobile-form-label">Numero Persone</label>
                    <div class="mobile-quantity-control">
                        <button type="button" class="mobile-qty-btn" id="decreaseSplitMobile">-</button>
                        <input type="number" id="splitCountMobile" class="mobile-qty-input" value="2" min="2" max="20">
                        <button type="button" class="mobile-qty-btn" id="increaseSplitMobile">+</button>
                    </div>
                    <div class="split-preview">
                        <span id="perPersonAmountMobile">€0.00</span> a persona
                    </div>
                </div>
            </div>

            <div class="mobile-modal-footer">
                <button class="mobile-action-btn btn-secondary" id="cancelPrecontoMobile">
                    <i class="fas fa-times"></i> ANNULLA
                </button>
                <button class="mobile-action-btn btn-primary" id="confirmPrecontoMobile">
                    <i class="fas fa-print"></i> STAMPA
                </button>
            </div>
        </div>
    </div>

    <!-- Comunica Modal (Mobile) -->
    <div id="comunicaModalMobile" class="mobile-modal">
        <div class="mobile-modal-content">
            <div class="mobile-modal-header" style="background: #6f42c1;">
                <div class="mobile-modal-title">
                    <div class="table-number-large">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="table-status-text">COMUNICAZIONE</div>
                </div>
                <button class="mobile-close-btn" id="closeComunicaMobile">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mobile-modal-body">
                <div class="mobile-form-group">
                    <label class="mobile-form-label">
                        <i class="fas fa-print"></i> Stampante
                    </label>
                    <select id="comunicaPrinterSelectMobile" class="mobile-select">
                        <option value="">-- Seleziona --</option>
                        @php
                            $printers = \App\Models\Printer::where('is_active', true)->orderBy('label')->get();
                        @endphp
                        @foreach($printers as $printer)
                            <option value="{{ $printer->id }}">{{ $printer->label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mobile-form-group">
                    <label class="mobile-form-label">
                        <i class="fas fa-comment-alt"></i> Messaggio
                    </label>
                    <textarea id="comunicaMessageMobile" class="mobile-textarea" rows="4" placeholder="Scrivi il messaggio..."></textarea>
                </div>

                <div class="mobile-form-group">
                    <label class="mobile-form-label">
                        <i class="fas fa-chair"></i> Tavolo
                    </label>
                    <input type="text" id="comunicaTableNumberMobile" class="mobile-input" readonly>
                </div>
            </div>

            <div class="mobile-modal-footer">
                <button class="mobile-action-btn btn-secondary" id="cancelComunicaMobile">
                    <i class="fas fa-times"></i> ANNULLA
                </button>
                <button class="mobile-action-btn btn-purple-solid" id="confirmComunicaMobile">
                    <i class="fas fa-paper-plane"></i> INVIA
                </button>
            </div>
        </div>
    </div>

    <!-- Product Modal (Mobile) -->
    <x-product-modal :isMobile="true" />

    <!-- Operator Authentication Modal -->
    <x-operator-auth-modal />

    <!-- Covers Selection Modal -->
    <x-covers-modal />

    <!-- Notification (Mobile) -->
    <div id="notificationMobile" class="mobile-notification">
        <span id="notificationTextMobile">Operazione completata!</span>
    </div>
@endsection
