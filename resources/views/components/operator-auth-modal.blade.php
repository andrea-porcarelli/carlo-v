<!-- Operator Authentication Modal -->
<div id="operatorAuthModal" class="operator-modal" style="display: none;">
    <div class="operator-modal-overlay"></div>
    <div class="operator-modal-content">
        <div class="operator-modal-header">
            <h4 class="operator-modal-title">
                <i class="fas fa-lock me-2"></i> CONFERMA OPERAZIONE
            </h4>
            <button class="operator-modal-close" id="closeOperatorAuthModal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="operator-modal-body">
            <p class="operator-modal-description">
                Inserisci la tua password per confermare l'operazione
            </p>

            <form id="operatorAuthForm" autocomplete="off">
                <div class="operator-form-group">
                    <label for="operatorPassword">
                        <i class="fas fa-key me-2"></i> Password Operatore
                    </label>
                    <input
                        type="password"
                        id="operatorPin"
                        name="operatorPin"
                        class="operator-form-control"
                        placeholder="Inserisci la tua password"
                        required
                        autocomplete="off"
                        maxlength="5"
                    >
                </div>

                <!-- Tastiera numerica -->
                <div class="operator-numpad" id="operatorNumpad">
                    <button type="button" class="operator-numpad-btn" data-key="1">1</button>
                    <button type="button" class="operator-numpad-btn" data-key="2">2</button>
                    <button type="button" class="operator-numpad-btn" data-key="3">3</button>
                    <button type="button" class="operator-numpad-btn" data-key="4">4</button>
                    <button type="button" class="operator-numpad-btn" data-key="5">5</button>
                    <button type="button" class="operator-numpad-btn" data-key="6">6</button>
                    <button type="button" class="operator-numpad-btn" data-key="7">7</button>
                    <button type="button" class="operator-numpad-btn" data-key="8">8</button>
                    <button type="button" class="operator-numpad-btn" data-key="9">9</button>
                    <button type="button" class="operator-numpad-btn" data-key="0">0</button>
                    <button type="button" class="operator-numpad-btn operator-numpad-clear" id="operatorNumpadClear">
                        <i class="fas fa-backspace"></i>
                    </button>
                </div>

                <div class="operator-error-message" id="operatorAuthError" style="display: none;">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <span id="operatorAuthErrorText"></span>
                </div>
            </form>

            <!-- Anteprima piatti (visibile solo se è apertura tavolo) -->
            <div id="operatorAuthDishesPreview" style="display: none; margin-top: 20px;">
                <div class="operator-dishes-preview-header" id="operatorAuthDishesHeader" style="cursor: pointer; user-select: none;">
                    <i class="fas fa-list me-2"></i> Piatti ordinati:
                    <i class="fas fa-chevron-down" id="operatorAuthDishesChevron" style="float: right; transition: transform 0.3s;"></i>
                </div>
                <ul id="operatorAuthDishesList" class="operator-dishes-list" style="display: none; margin-top: 8px;"></ul>
            </div>
        </div>

        <div class="operator-modal-footer">
            <button type="button" class="operator-btn operator-btn-secondary" id="cancelOperatorAuth">
                <i class="fas fa-times me-2"></i> ANNULLA
            </button>
            <button type="submit" form="operatorAuthForm" class="operator-btn operator-btn-primary" id="confirmOperatorAuth">
                <i class="fas fa-check me-2"></i> CONFERMA
            </button>
        </div>
    </div>
</div>

<style>
.operator-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.operator-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
}

.operator-modal-content {
    position: relative;
    background: #1a1a1a;
    border: 2px solid #dc3545;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 40px rgba(220, 53, 69, 0.3);
}

.operator-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #333;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.operator-modal-title {
    margin: 0;
    color: #dc3545;
    font-size: 1.25rem;
    font-weight: 600;
}

.operator-modal-close {
    background: none;
    border: none;
    color: #999;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

.operator-modal-close:hover {
    color: #dc3545;
}

.operator-modal-body {
    padding: 24px;
}

.operator-modal-description {
    color: #ccc;
    margin-bottom: 20px;
    font-size: 0.95rem;
}

.operator-form-group {
    margin-bottom: 20px;
}

.operator-form-group label {
    display: block;
    color: #fff;
    font-weight: 500;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.operator-form-control {
    width: 100%;
    padding: 12px 16px;
    background: #2a2a2a;
    border: 1px solid #444;
    border-radius: 4px;
    color: #fff;
    font-size: 1rem;
    transition: border-color 0.2s;
}

.operator-form-control:focus {
    outline: none;
    border-color: #dc3545;
}

.operator-form-control option {
    background: #2a2a2a;
    color: #fff;
}

.operator-error-message {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid #dc3545;
    border-radius: 4px;
    padding: 12px 16px;
    color: #dc3545;
    font-size: 0.9rem;
    margin-top: 16px;
}

.operator-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #333;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.operator-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.operator-btn-primary {
    background: #dc3545;
    color: #fff;
}

.operator-btn-primary:hover {
    background: #c82333;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.operator-btn-secondary {
    background: #444;
    color: #fff;
}

.operator-btn-secondary:hover {
    background: #555;
}

.operator-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Anteprima piatti */
.operator-dishes-preview-header {
    color: #fff;
    font-weight: 600;
    margin-bottom: 10px;
    font-size: 0.9rem;
}

.operator-dishes-list {
    list-style: none;
    margin: 0;
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid #dc3545;
    border-radius: 2px;
    padding: 6px 8px;
}

.operator-dishes-list li {
    color: #ccc;
    padding: 6px 0;
    font-size: 0.9rem;
    border-bottom: 1px solid #444;
}

.operator-dishes-list li:last-child {
    border-bottom: none;
}

/* Tastiera numerica */
.operator-numpad {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
    margin-top: 20px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

.operator-numpad-btn {
    padding: 6px;
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

.operator-numpad-btn:hover {
    background: #3a3a3a;
    border-color: #dc3545;
}

.operator-numpad-btn:active {
    background: #4a4a4a;
    transform: scale(0.95);
}

.operator-numpad-clear {
    grid-column: span 5;
    background: #dc3545;
    border-color: #c82333;
}

.operator-numpad-clear:hover {
    background: #c82333;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .operator-modal-content {
        width: 95%;
        margin: 20px;
    }

    .operator-modal-header,
    .operator-modal-body,
    .operator-modal-footer {
        padding: 16px;
    }

    .operator-modal-title {
        font-size: 1.1rem;
    }

    .operator-btn {
        padding: 10px 20px;
        font-size: 0.9rem;
    }

    .operator-numpad {
        grid-template-columns: repeat(6, 1fr);
    }

    .operator-numpad-clear {
        grid-column: span 2;
    }
}
</style>

<script>
(function() {
    const operatorPin = document.getElementById('operatorPin');
    const numpadButtons = document.querySelectorAll('.operator-numpad-btn:not(.operator-numpad-clear)');
    const numpadClear = document.getElementById('operatorNumpadClear');

    // Gestisci i clic sui tasti numerici
    numpadButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const key = btn.dataset.key;
            if (operatorPin.value.length < 5) {
                operatorPin.value += key;
            }
        });
    });

    // Gestisci il tasto Clear/Backspace
    if (numpadClear) {
        numpadClear.addEventListener('click', (e) => {
            e.preventDefault();
            operatorPin.value = operatorPin.value.slice(0, -1);
        });
    }

    // Gestisci il toggle della lista dei piatti
    const dishesHeader = document.getElementById('operatorAuthDishesHeader');
    const dishesList = document.getElementById('operatorAuthDishesList');
    const dishesChevron = document.getElementById('operatorAuthDishesChevron');

    if (dishesHeader) {
        dishesHeader.addEventListener('click', (e) => {
            e.preventDefault();
            const isVisible = dishesList.style.display !== 'none';
            dishesList.style.display = isVisible ? 'none' : 'block';
            dishesChevron.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
        });
    }

    // Funzione pubblica per mostrare la preview dei piatti
    window.showOperatorAuthWithDishes = function(dishes) {
        const dishesList = document.getElementById('operatorAuthDishesList');
        const dishesPreview = document.getElementById('operatorAuthDishesPreview');

        if (dishes && dishes.length > 0) {
            dishesList.innerHTML = '';
            dishes.forEach(dish => {
                const li = document.createElement('li');
                li.textContent = dish.quantity + 'x ' + dish.name;
                dishesList.appendChild(li);
            });
            dishesPreview.style.display = 'block';
        } else {
            dishesPreview.style.display = 'none';
        }
    };

    // Funzione pubblica per pulire la preview
    window.clearOperatorAuthDishes = function() {
        document.getElementById('operatorAuthDishesPreview').style.display = 'none';
        document.getElementById('operatorAuthDishesList').innerHTML = '';
    };
})();
</script>
