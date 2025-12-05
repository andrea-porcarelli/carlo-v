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
                <div id="{{ $isMobile ? 'modalProductPriceMobile' : 'modalProductPrice' }}"
                     class="{{ $isMobile ? 'product-price' : '' }}"
                     style="{{ !$isMobile ? 'color: #dc3545; font-size: 1.2rem; font-weight: 700; margin-top: 5px;' : '' }}"
                >€0.00</div>
            </div>
            <button
                class="{{ $isMobile ? 'mobile-close-btn' : '' }}"
                style="{{ !$isMobile ? 'position: absolute; top: 15px; right: 15px; background: #dc3545; border: none; color: white; width: 30px; height: 30px; cursor: pointer; font-size: 18px;' : '' }}"
                id="{{ $isMobile ? 'closeProductModalMobile' : 'closeProductModalBtn' }}"
            >
                {{ $isMobile ? '' : '×' }}
                @if($isMobile)<i class="fas fa-times"></i>@endif
            </button>
        </div>

        <!-- Body -->
        <div class="{{ $isMobile ? 'mobile-modal-body' : '' }}"
             style="{{ !$isMobile ? 'padding: 30px;' : '' }}"
        >
            <!-- Quantity -->
            <div class="{{ $isMobile ? 'mobile-form-group' : '' }}"
                 style="{{ !$isMobile ? 'margin-bottom: 25px;' : '' }}"
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

            <!-- Notes -->
            <div class="{{ $isMobile ? 'mobile-form-group' : '' }}"
                 style="{{ !$isMobile ? 'margin-bottom: 25px;' : '' }}"
            >
                <label class="{{ $isMobile ? 'mobile-form-label' : '' }}"
                       style="{{ !$isMobile ? 'display: block; font-weight: 700; margin-bottom: 10px; color: #000; text-transform: uppercase;' : '' }}"
                >NOTE</label>
                <textarea
                    id="{{ $isMobile ? 'productNotesMobile' : 'productNotes' }}"
                    placeholder="Aggiungi note per la cucina..."
                    class="{{ $isMobile ? 'mobile-textarea' : '' }}"
                    style="{{ !$isMobile ? 'width: 100%; height: 80px; border: 2px solid #dee2e6; padding: 10px; resize: vertical; font-family: inherit;' : '' }}"
                ></textarea>
            </div>

            <!-- Extras -->
            <div class="{{ $isMobile ? 'mobile-form-group' : '' }}"
                 style="{{ !$isMobile ? 'margin-bottom: 25px;' : '' }}"
            >
                <label class="{{ $isMobile ? 'mobile-form-label' : '' }}"
                       style="{{ !$isMobile ? 'display: block; font-weight: 700; margin-bottom: 15px; color: #000; text-transform: uppercase;' : '' }}"
                >SUPPLEMENTI</label>
                <div id="{{ $isMobile ? 'extrasContainerMobile' : 'extrasContainer' }}"
                     class="{{ $isMobile ? 'mobile-checkbox-group' : '' }}"
                >
                    <!-- Extras will be dynamically loaded -->
                </div>
            </div>

            <!-- Removals -->
            <div class="{{ !$isMobile ? '' : 'mobile-form-group' }}"
                 style="{{ !$isMobile ? 'margin-bottom: 30px;' : '' }}"
            >
                <label class="{{ $isMobile ? 'mobile-form-label' : '' }}"
                       style="{{ !$isMobile ? 'display: block; font-weight: 700; margin-bottom: 15px; color: #000; text-transform: uppercase;' : '' }}"
                >RIMUOVI</label>
                <div id="{{ $isMobile ? 'removalsContainerMobile' : 'removalsContainer' }}"
                     class="{{ $isMobile ? 'mobile-checkbox-group' : '' }}"
                >
                    <!-- Removals will be dynamically loaded -->
                </div>
            </div>

            <!-- Total (Mobile) -->
            @if($isMobile)
            <div class="mobile-modal-total">
                <span class="mobile-total-label">TOTALE RIGA:</span>
                <span id="modalTotalMobile" class="mobile-total-amount">€0.00</span>
            </div>
            @endif
        </div>

        <!-- Footer -->
        @if(!$isMobile)
        <div style="border-top: 2px solid #dee2e6; padding: 20px 30px 30px 30px;">
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
