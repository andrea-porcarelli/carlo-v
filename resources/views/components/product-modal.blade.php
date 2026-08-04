@props(['isMobile' => false])

<div
    id="{{ $isMobile ? 'productModalMobile' : 'productModal' }}"
    class="{{ $isMobile ? 'mobile-modal' : '' }}"
    style="{{ !$isMobile ? 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000;' : 'display: none;' }}"
>
    <div class="{{ $isMobile ? 'mobile-modal-content' : '' }}"
         style="{{ !$isMobile ? 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 500px; background: white; border-radius: 0; box-shadow: 0 20px 60px rgba(0,0,0,0.5);' : '' }}"
    >
        <!-- Header -->
        <div class="{{ $isMobile ? 'mobile-modal-header' : '' }}"
             style="{{ !$isMobile ? 'background: #000; color: white; padding: 20px; text-align: center;' : '' }}"
        >
            <div class="{{ $isMobile ? 'mobile-modal-title' : '' }}">
                <h4 id="{{ $isMobile ? 'modalProductNameMobile' : 'modalProductName' }}"
                    style="{{ !$isMobile ? 'margin: 0; font-weight: 700; text-transform: uppercase;' : '' }}"
                    class="{{ $isMobile ? '' : '' }}"
                >PRODOTTO</h4>
                <div id="{{ $isMobile ? 'modalProductPriceDisplayMobile' : 'modalProductPriceDisplay' }}"
                     class="{{ $isMobile ? 'product-price' : '' }}"
                     style="{{ !$isMobile ? 'color: #dc3545; font-size: 1.2rem; font-weight: 700; margin-top: 5px;' : '' }}"
                >€0.00</div>
            </div>
            <button
                class="{{ $isMobile ? 'mobile-close-btn' : '' }}"
                style="{{ !$isMobile ? 'position: absolute; top: 15px; right: 15px; background: #dc3545; border: none; color: white; width: 30px; height: 30px; cursor: pointer; font-size: 18px;' : '' }}"
                id="{{ $isMobile ? 'closeProductModalMobile' : 'closeProductModal' }}"
            >
                {{ $isMobile ? '' : '×' }}
                @if($isMobile)<i class="fas fa-times"></i>@endif
            </button>
        </div>

        <!-- Body -->
        <div id="{{ $isMobile ? '' : 'productModal-editView' }}"
             class="{{ $isMobile ? 'mobile-modal-body' : '' }}"
             style="{{ !$isMobile ? 'padding: 16px;' : '' }}"
        >
            <!-- Quantity and Price Row -->
            <div style="{{ !$isMobile ? 'display: flex; gap: 16px; margin-bottom: 10px;' : '' }}">
                <!-- Quantity -->
                <div class="{{ $isMobile ? 'mobile-form-group' : '' }}"
                     style="{{ !$isMobile ? 'flex: 1;' : '' }}"
                >
                    <label class="{{ $isMobile ? 'mobile-form-label' : '' }}"
                           style="{{ !$isMobile ? 'display: block; font-weight: 700; margin-bottom: 10px; color: #000; text-transform: uppercase;' : '' }}"
                    >QUANTITÀ</label>
                    <div class="{{ $isMobile ? 'mobile-quantity-control' : '' }}"
                         style="{{ !$isMobile ? 'display: flex; align-items: center; gap: 15px;' : '' }}"
                    >
                        <button
                            class="{{ $isMobile ? 'mobile-qty-btn' : 'btn-red' }}"
                            style="{{ !$isMobile ? 'width: 40px; height: 40px; font-size: 18px; font-weight: 700;' : '' }}"
                            id="{{ $isMobile ? 'decreaseQtyMobile' : 'decreaseQty' }}"
                        >−</button>
                        <input
                            type="number"
                            id="{{ $isMobile ? 'productQuantityMobile' : 'productQuantity' }}"
                            value="1"
                            min="1"
                            class="{{ $isMobile ? 'mobile-qty-input' : '' }}"
                            style="{{ !$isMobile ? 'width: 80px; height: 40px; text-align: center; border: 2px solid #dee2e6; font-size: 18px; font-weight: 700;' : '' }}"
                        >
                        <button
                            class="{{ $isMobile ? 'mobile-qty-btn' : 'btn-red' }}"
                            style="{{ !$isMobile ? 'width: 40px; height: 40px; font-size: 18px; font-weight: 700;' : '' }}"
                            id="{{ $isMobile ? 'increaseQtyMobile' : 'increaseQty' }}"
                        >+</button>
                    </div>
                </div>

                <!-- Custom Price -->
                <div class="{{ $isMobile ? 'mobile-form-group' : '' }}"
                     style="{{ !$isMobile ? 'flex: 1;' : '' }}"
                >
                    <label class="{{ $isMobile ? 'mobile-form-label' : '' }}"
                           style="{{ !$isMobile ? 'display: block; font-weight: 700; margin-bottom: 10px; color: #000; text-transform: uppercase;' : '' }}"
                    >PREZZO UNITARIO</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 18px; font-weight: 700; color: #dc3545;">€</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            id="{{ $isMobile ? 'productCustomPriceMobile' : 'productCustomPrice' }}"
                            value="0.00"
                            class="{{ $isMobile ? 'mobile-qty-input' : '' }}"
                            style="{{ !$isMobile ? 'width: 100px; height: 40px; text-align: center; border: 2px solid #dee2e6; font-size: 18px; font-weight: 700;' : 'flex: 1;' }}"
                        >
                    </div>
                </div>
            </div>

            @if($isMobile)
            <!-- Toggle: apre/chiude Note+Supplementi+Rimozioni (solo mobile) -->
            <button type="button" id="productExtrasToggle" class="product-extras-toggle" aria-expanded="false">
                <span><i class="fas fa-sliders-h me-1"></i> Note, supplementi e rimozioni</span>
                <i class="fas fa-chevron-down chev"></i>
            </button>
            @endif

            <div id="productExtrasCollapse" class="product-extras-collapse {{ $isMobile ? 'collapsed' : '' }}">
            <!-- Notes -->
            <div class="{{ $isMobile ? 'mobile-form-group' : '' }}"
                 style="{{ !$isMobile ? 'margin-bottom: 10px;' : '' }}"
            >
                <label class="{{ $isMobile ? 'mobile-form-label' : '' }}"
                       style="{{ !$isMobile ? 'display: block; font-weight: 700; margin-bottom: 6px; color: #000; text-transform: uppercase;' : '' }}"
                >NOTE</label>
                <textarea
                    id="{{ $isMobile ? 'productNotesMobile' : 'productNotes' }}"
                    placeholder="Aggiungi note per la cucina..."
                    class="{{ $isMobile ? 'mobile-textarea' : '' }}"
                    style="{{ !$isMobile ? 'width: 100%; height: 50px; border: 2px solid #dee2e6; padding: 8px; resize: vertical; font-family: inherit;' : '' }}"
                ></textarea>
            </div>

            <!-- Extras -->
            <div id="{{ $isMobile ? 'extrasSectionMobile' : 'extrasSection' }}"
                 class="{{ $isMobile ? 'mobile-form-group' : '' }}"
                 style="{{ !$isMobile ? 'margin-bottom: 10px;' : '' }}"
            >
                <label class="{{ $isMobile ? 'mobile-form-label' : '' }}"
                       style="{{ !$isMobile ? 'display: block; font-weight: 700; margin-bottom: 6px; color: #000; text-transform: uppercase;' : '' }}"
                >SUPPLEMENTI</label>
                <div id="{{ $isMobile ? 'extrasContainerMobile' : 'extrasContainer' }}"
                     class="{{ $isMobile ? 'mobile-checkbox-group' : '' }}"
                     style="{{ !$isMobile ? 'display:flex;flex-wrap:wrap;gap:6px;' : '' }}"
                >
                    <!-- Extras will be dynamically loaded -->
                </div>
            </div>

            <!-- Removals -->
            <div id="{{ $isMobile ? 'removalsSectionMobile' : 'removalsSection' }}"
                 class="{{ !$isMobile ? '' : 'mobile-form-group' }}"
                 style="{{ !$isMobile ? 'margin-bottom: 10px;' : '' }}"
            >
                <label class="{{ $isMobile ? 'mobile-form-label' : '' }}"
                       style="{{ !$isMobile ? 'display: block; font-weight: 700; margin-bottom: 6px; color: #000; text-transform: uppercase;' : '' }}"
                >RIMUOVI</label>
                <div id="{{ $isMobile ? 'removalsContainerMobile' : 'removalsContainer' }}"
                     class="{{ $isMobile ? 'mobile-checkbox-group' : '' }}"
                     style="{{ !$isMobile ? 'display:flex;flex-wrap:wrap;gap:6px;' : '' }}"
                >
                    <!-- Removals will be dynamically loaded -->
                </div>
            </div>
            </div><!-- /#productExtrasCollapse -->

            <!-- Total (Mobile) -->
            @if($isMobile)
            <div class="mobile-modal-total">
                <span class="mobile-total-label">TOTALE RIGA:</span>
                <span id="modalTotalMobile" class="mobile-total-amount">€0.00</span>
            </div>
            @endif
        </div>

        <!-- Dish Selection View (desktop only, hidden by default) -->
        @if(!$isMobile)
        <div id="productModal-dishView" style="display:none; flex-direction:column; padding:16px; gap:10px; height:400px;">
            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:#fd7e14; letter-spacing:0.5px;">
                <i class="fas fa-exchange-alt me-1"></i> Seleziona il nuovo piatto
            </div>
            <input type="text" id="dishChangeSearch" placeholder="Cerca piatto per nome..."
                   oninput="tableOrdersManager._filterDishList(this.value)"
                   style="width:100%; padding:8px 12px; border:2px solid #dee2e6; font-size:0.9rem; border-radius:4px; outline:none;">
            <div id="dishChangeCategoryFilter" style="display:flex; flex-wrap:wrap; gap:5px; flex-shrink:0;"></div>
            <div id="dishChangeList" style="flex:1; overflow-y:auto; display:grid; grid-template-columns:1fr 1fr; gap:6px; align-content:start;"></div>
        </div>
        @endif

        <!-- Footer -->
        @if(!$isMobile)
        <div style="border-top: 2px solid #dee2e6; padding: 10px 16px 16px 16px;">
            <!-- Main edit footer -->
            <div id="productModal-mainFooter">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 1rem; font-weight: 700; color: #000;">TOTALE RIGA:</span>
                    <span id="modalTotal" style="font-size: 1.3rem; font-weight: 700; color: #dc3545;">€0.00</span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn-red" style="flex: 1; padding: 11px; font-size: 14px;" id="addProductBtn">
                        <i class="fas fa-plus me-2"></i> AGGIUNGI
                    </button>
                    <button id="changeDishBtn" style="display:none; flex: 0 0 auto; padding: 11px 16px; font-size: 14px; background: #fd7e14; border: none; color: white; font-weight: 600; text-transform: uppercase; cursor: pointer;" onclick="tableOrdersManager.openDishChangeView()">
                        <i class="fas fa-exchange-alt me-1"></i> CAMBIA PIATTO
                    </button>
                    <button style="flex: 0 0 auto; padding: 11px 16px; font-size: 14px; background: #6c757d; border: none; color: white; font-weight: 600; text-transform: uppercase; cursor: pointer;" id="cancelProductBtn">
                        ANNULLA
                    </button>
                </div>
            </div>
            <!-- Dish selection footer (hidden by default) -->
            <div id="dishChangeFooter" style="display:none; gap:8px;">
                <button id="confirmDishChangeBtn"
                        onclick="tableOrdersManager.confirmDishChange()"
                        style="flex:1; padding:11px; font-size:14px; background:#fd7e14; border:none; color:white; font-weight:600; text-transform:uppercase; cursor:pointer; border-radius:4px;">
                    <i class="fas fa-check me-2"></i> CONFERMA PIATTO
                </button>
                <button onclick="tableOrdersManager.closeDishChangeView()"
                        style="flex:0 0 auto; padding:11px 16px; font-size:14px; background:#6c757d; border:none; color:white; font-weight:600; text-transform:uppercase; cursor:pointer; border-radius:4px;">
                    ANNULLA
                </button>
            </div>
        </div>
        @else
        <div class="mobile-modal-footer">
            <button class="mobile-action-btn btn-primary" id="addProductBtnMobile">
                <i class="fas fa-plus me-2"></i> AGGIUNGI
            </button>
            <button class="mobile-action-btn btn-secondary" id="cancelProductBtnMobile">
                ANNULLA
            </button>
        </div>
        @endif
    </div>
</div>
