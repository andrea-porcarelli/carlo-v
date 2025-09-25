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
                    <div class="control-panel">
                        <h5 class="section-title">
                            <i class="fas fa-book-open me-2"></i> MENU
                        </h5>

                        <div class="menu-category">
                            <h6 class="category-header" data-category="antipasti">
                                <span>ANTIPASTI</span>
                                <i class="fas fa-chevron-right category-arrow"></i>
                            </h6>
                            <div class="category-items" id="antipasti-items" style="display: none;">
                                <div class="menu-item" data-item="Bruschetta" data-price="8.00">
                                    <span class="menu-item-name">Bruschetta</span>
                                    <span class="menu-item-price">€8.00</span>
                                </div>
                                <div class="menu-item" data-item="Antipasto della Casa" data-price="12.00">
                                    <span class="menu-item-name">Antipasto Casa</span>
                                    <span class="menu-item-price">€12.00</span>
                                </div>
                                <div class="menu-item" data-item="Tagliere Misto" data-price="15.00">
                                    <span class="menu-item-name">Tagliere Misto</span>
                                    <span class="menu-item-price">€15.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="menu-category">
                            <h6 class="category-header" data-category="primi">
                                <span>PRIMI</span>
                                <i class="fas fa-chevron-right category-arrow"></i>
                            </h6>
                            <div class="category-items" id="primi-items" style="display: none;">
                                <div class="menu-item" data-item="Spaghetti Carbonara" data-price="14.00">
                                    <span class="menu-item-name">Carbonara</span>
                                    <span class="menu-item-price">€14.00</span>
                                </div>
                                <div class="menu-item" data-item="Risotto ai Porcini" data-price="16.00">
                                    <span class="menu-item-name">Risotto Porcini</span>
                                    <span class="menu-item-price">€16.00</span>
                                </div>
                                <div class="menu-item" data-item="Penne all'Arrabbiata" data-price="13.00">
                                    <span class="menu-item-name">Penne Arrabbiata</span>
                                    <span class="menu-item-price">€13.00</span>
                                </div>
                                <div class="menu-item" data-item="Gnocchi al Gorgonzola" data-price="15.50">
                                    <span class="menu-item-name">Gnocchi Gorgonzola</span>
                                    <span class="menu-item-price">€15.50</span>
                                </div>
                            </div>
                        </div>

                        <div class="menu-category">
                            <h6 class="category-header" data-category="secondi">
                                <span>SECONDI</span>
                                <i class="fas fa-chevron-right category-arrow"></i>
                            </h6>
                            <div class="category-items" id="secondi-items" style="display: none;">
                                <div class="menu-item" data-item="Bistecca alla Griglia" data-price="22.00">
                                    <span class="menu-item-name">Bistecca Griglia</span>
                                    <span class="menu-item-price">€22.00</span>
                                </div>
                                <div class="menu-item" data-item="Salmone al Forno" data-price="20.00">
                                    <span class="menu-item-name">Salmone Forno</span>
                                    <span class="menu-item-price">€20.00</span>
                                </div>
                                <div class="menu-item" data-item="Pollo alle Erbe" data-price="18.00">
                                    <span class="menu-item-name">Pollo Erbe</span>
                                    <span class="menu-item-price">€18.00</span>
                                </div>
                                <div class="menu-item" data-item="Branzino in Crosta" data-price="24.00">
                                    <span class="menu-item-name">Branzino Crosta</span>
                                    <span class="menu-item-price">€24.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="menu-category">
                            <h6 class="category-header" data-category="bevande">
                                <span>BEVANDE</span>
                                <i class="fas fa-chevron-right category-arrow"></i>
                            </h6>
                            <div class="category-items" id="bevande-items" style="display: none;">
                                <div class="menu-item" data-item="Acqua Naturale" data-price="2.50">
                                    <span class="menu-item-name">Acqua Naturale</span>
                                    <span class="menu-item-price">€2.50</span>
                                </div>
                                <div class="menu-item" data-item="Vino della Casa" data-price="5.00">
                                    <span class="menu-item-name">Vino Casa</span>
                                    <span class="menu-item-price">€5.00</span>
                                </div>
                                <div class="menu-item" data-item="Birra Artigianale" data-price="6.50">
                                    <span class="menu-item-name">Birra Artigianale</span>
                                    <span class="menu-item-price">€6.50</span>
                                </div>
                                <div class="menu-item" data-item="Cocktail della Casa" data-price="8.00">
                                    <span class="menu-item-name">Cocktail Casa</span>
                                    <span class="menu-item-price">€8.00</span>
                                </div>
                            </div>
                        </div>
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

                        <div class="mini-control" id="showReceipt">
                            <i class="fas fa-receipt"></i>
                            <div class="mini-control-label">CONTO</div>
                        </div>

                        <div class="mini-control" id="resetAll">
                            <i class="fas fa-broom"></i>
                            <div class="mini-control-label">RESET</div>
                        </div>

                        <div class="mini-control" id="saveAll">
                            <i class="fas fa-save"></i>
                            <div class="mini-control-label">SALVA</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt Modal-like Overlay -->
            <div id="receiptOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000;">
                <div style="position: absolute; right: 20px; top: 80px; width: 400px; background: white; padding: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    <div class="table-info-card">
                        <div class="table-info-number" id="selectedTableNumber">-</div>
                        <div class="table-info-status">TAVOLO SELEZIONATO</div>
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

        <!-- Add Table View -->
        <div id="addTableView" class="page-content">
            <div class="row g-0">
                <div class="col-left">
                    <div class="control-panel">
                        <h5 class="section-title">
                            <i class="fas fa-cogs me-2"></i> CONTROLLI
                        </h5>

                        <div class="stats-card">
                            <div class="stats-number" id="newTableCount">0</div>
                            <div class="stats-label">Tavoli Aggiunti</div>
                        </div>

                        <div class="stats-card">
                            <div class="stats-number" id="nextTableNumber">21</div>
                            <div class="stats-label">Prossimo Numero</div>
                        </div>

                        <button class="action-btn" id="btnSaveTables">
                            <i class="fas fa-save"></i> SALVA LAYOUT
                        </button>
                        <button class="action-btn" id="btnResetLayout">
                            <i class="fas fa-undo"></i> RESET LAYOUT
                        </button>
                    </div>
                </div>

                <div class="col-center">
                    <div class="dining-area">
                        <h4 class="dining-title">AGGIUNGI NUOVI TAVOLI</h4>
                        <div id="addTableArea" class="position-relative">
                            <div class="empty-state">
                                <i class="fas fa-mouse-pointer"></i>
                                <h5>CLICCA PER AGGIUNGERE</h5>
                                <p>I tavoli possono essere trascinati</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-right">
                    <div class="mini-panel">
                        <div class="mini-control">
                            <i class="fas fa-info"></i>
                            <div class="mini-control-label">HELP</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <div id="productModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 500px; background: white; border-radius: 0; box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
            <div style="background: #000; color: white; padding: 20px; text-align: center;">
                <h4 id="modalProductName" style="margin: 0; font-weight: 700; text-transform: uppercase;">PRODOTTO</h4>
                <div id="modalProductPrice" style="color: #dc3545; font-size: 1.2rem; font-weight: 700; margin-top: 5px;">€0.00</div>
                <button style="position: absolute; top: 15px; right: 15px; background: #dc3545; border: none; color: white; width: 30px; height: 30px; cursor: pointer; font-size: 18px;" id="closeProductModalBtn">×</button>
            </div>

            <div style="padding: 30px;">
                <!-- Quantity -->
                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #000; text-transform: uppercase;">QUANTITÀ</label>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <button class="btn-red" style="width: 40px; height: 40px; font-size: 18px; font-weight: 700;" id="decreaseQty">−</button>
                        <input type="number" id="productQuantity" value="1" min="1" style="width: 80px; height: 40px; text-align: center; border: 2px solid #dee2e6; font-size: 18px; font-weight: 700;">
                        <button class="btn-red" style="width: 40px; height: 40px; font-size: 18px; font-weight: 700;" id="increaseQty">+</button>
                    </div>
                </div>

                <!-- Notes -->
                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 10px; color: #000; text-transform: uppercase;">NOTE</label>
                    <textarea id="productNotes" placeholder="Aggiungi note per la cucina..." style="width: 100%; height: 80px; border: 2px solid #dee2e6; padding: 10px; resize: vertical; font-family: inherit;"></textarea>
                </div>

                <!-- Extras -->
                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 15px; color: #000; text-transform: uppercase;">SUPPLEMENTI</label>
                    <div id="extrasContainer">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" value="2.00" data-name="Parmigiano extra" style="margin-right: 8px;" class="extra-checkbox">
                                Parmigiano extra
                            </label>
                            <span style="color: #dc3545; font-weight: 700;">+€2.00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" value="1.50" data-name="Peperoncino" style="margin-right: 8px;" class="extra-checkbox">
                                Peperoncino
                            </label>
                            <span style="color: #dc3545; font-weight: 700;">+€1.50</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" value="3.00" data-name="Bacon extra" style="margin-right: 8px;" class="extra-checkbox">
                                Bacon extra
                            </label>
                            <span style="color: #dc3545; font-weight: 700;">+€3.00</span>
                        </div>
                    </div>
                </div>

                <!-- Removals -->
                <div style="margin-bottom: 30px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 15px; color: #000; text-transform: uppercase;">RIMUOVI</label>
                    <div id="removalsContainer">
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" data-name="Senza aglio" style="margin-right: 8px;" class="removal-checkbox">
                                Senza aglio
                            </label>
                        </div>
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" data-name="Senza cipolla" style="margin-right: 8px;" class="removal-checkbox">
                                Senza cipolla
                            </label>
                        </div>
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" data-name="Senza glutine" style="margin-right: 8px;" class="removal-checkbox">
                                Senza glutine
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Total and Actions -->
                <div style="border-top: 2px solid #dee2e6; padding-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <span style="font-size: 1.2rem; font-weight: 700; color: #000;">TOTALE RIGA:</span>
                        <span id="modalTotal" style="font-size: 1.5rem; font-weight: 700; color: #dc3545;">€0.00</span>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <button class="btn-red" style="flex: 1; padding: 15px; font-size: 16px;" id="addProductBtn">
                            <i class="fas fa-plus me-2"></i> AGGIUNGI
                        </button>
                        <button style="flex: 1; padding: 15px; font-size: 16px; background: #6c757d; border: none; color: white; font-weight: 600; text-transform: uppercase;" id="cancelProductBtn">
                            ANNULLA
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="notification">
        <span id="notificationText">Operazione completata!</span>
    </div>
@endsection
