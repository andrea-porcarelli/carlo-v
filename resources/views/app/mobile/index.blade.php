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
        </div>

        <!-- Bottom Action Bar (Floating) -->
        <div class="mobile-action-bar" id="mobileActionBar" style="display: none;">
            <div class="mobile-action-bar-content">
                <div class="selected-table-info">
                    <div class="selected-table-number">
                        <i class="fas fa-table me-2"></i>
                        <span id="selectedTableNumberMobile">-</span>
                    </div>
                </div>
                <div class="mobile-action-buttons-inline">
                    <button class="mobile-action-btn-small" id="showReceiptMobile">
                        <i class="fas fa-receipt"></i>
                    </button>
                    <button class="mobile-action-btn-small" id="btnFreeTableMobile">
                        <i class="fas fa-door-open"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal (Mobile) -->
    <div id="receiptModalMobile" class="mobile-modal">
        <div class="mobile-modal-content">
            <div class="mobile-modal-header">
                <div class="mobile-modal-title">
                    <div class="table-number-large">
                        <span id="receiptTableNumberMobile">-</span>
                    </div>
                    <div class="table-status-text">TAVOLO SELEZIONATO</div>
                </div>
                <button class="mobile-close-btn" id="closeReceiptMobile">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mobile-modal-body" id="receiptItemsMobile">
                <div class="mobile-empty-state-small">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Nessun ordine</p>
                </div>
            </div>

            <div class="mobile-modal-footer">
                <div class="mobile-total-section">
                    <span class="mobile-total-label">TOTALE:</span>
                    <span id="totalAmountMobile" class="mobile-total-amount">€0.00</span>
                </div>

                <div class="mobile-footer-actions">
                    <button class="mobile-action-btn btn-primary" id="btnPayBillMobile">
                        <i class="fas fa-money-bill me-2"></i> INCASSA
                    </button>
                    <button class="mobile-action-btn btn-secondary" id="btnClearBillMobile">
                        <i class="fas fa-eraser me-2"></i> SVUOTA
                    </button>
                </div>
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
