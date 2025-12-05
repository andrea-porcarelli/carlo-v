# Installazione Sistema Gestione Tavoli

## Panoramica

Questo documento descrive l'installazione del sistema unificato di gestione tavoli e ordini per Carlo V.

### Caratteristiche principali:
- ✅ Modale unificata per desktop e mobile
- ✅ Gestione tavoli tramite database MySQL
- ✅ Sistema di ordini con prodotti, supplementi e note
- ✅ API RESTful per tutte le operazioni
- ✅ Supporto completo mobile e desktop

## File Creati

### Database
- `database/migrations/2025_11_12_001_create_restaurant_tables_table.php` - Tavoli del ristorante
- `database/migrations/2025_11_12_002_create_table_orders_table.php` - Ordini per tavolo
- `database/migrations/2025_11_12_003_create_order_items_table.php` - Prodotti negli ordini
- `database/seeders/RestaurantTablesSeeder.php` - Seeder per 20 tavoli iniziali

### Models
- `app/Models/RestaurantTable.php` - Model per i tavoli
- `app/Models/TableOrder.php` - Model per gli ordini
- `app/Models/OrderItem.php` - Model per i prodotti negli ordini

### Controllers
- `app/Http/Controllers/Frontoffice/TableOrderController.php` - Controller API per gestione tavoli e ordini

### Views
- `resources/views/components/product-modal.blade.php` - Componente modale unificato

### JavaScript
- `public/app/js/table-orders.js` - Logica JavaScript unificata per gestione ordini

### Routes
- Le route API sono state aggiunte a `routes/web.php` sotto il prefisso `/api/tables`

## Installazione

### 1. Eseguire le migrazioni

```bash
php artisan migrate
```

Questo creerà le seguenti tabelle:
- `restaurant_tables` - Tavoli del ristorante
- `table_orders` - Ordini aperti sui tavoli
- `order_items` - Prodotti aggiunti agli ordini

### 2. Popolare i tavoli iniziali

```bash
php artisan db:seed --class=RestaurantTablesSeeder
```

Questo creerà 20 tavoli numerati da 1 a 20.

### 3. Verificare l'installazione

Accedere all'applicazione e verificare che:
- I tavoli siano visibili nella vista principale
- Sia possibile selezionare un tavolo
- La modale di aggiunta prodotto si apra correttamente
- I prodotti vengano aggiunti al tavolo

## Struttura Database

### Tabella `restaurant_tables`
| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | bigint | ID univoco |
| table_number | integer | Numero del tavolo (univoco) |
| capacity | integer | Posti a sedere |
| position_x | decimal | Posizione X nel layout |
| position_y | decimal | Posizione Y nel layout |
| status | enum | Stato: free, occupied, reserved |
| is_active | boolean | Tavolo attivo/disattivo |

### Tabella `table_orders`
| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | bigint | ID univoco |
| restaurant_table_id | bigint | FK tavolo |
| status | enum | Stato: open, paid, cancelled |
| total_amount | decimal | Totale ordine |
| opened_at | timestamp | Data apertura |
| closed_at | timestamp | Data chiusura |
| waiter_id | bigint | FK cameriere (users) |

### Tabella `order_items`
| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | bigint | ID univoco |
| table_order_id | bigint | FK ordine |
| dish_id | bigint | FK piatto (dishes) |
| quantity | integer | Quantità |
| unit_price | decimal | Prezzo unitario |
| subtotal | decimal | Subtotale (auto-calcolato) |
| notes | text | Note per la cucina |
| extras | json | Supplementi {"nome": prezzo} |
| removals | json | Rimozioni ["senza aglio", ...] |
| status | enum | pending, preparing, ready, served, cancelled |

## API Endpoints

### GET `/api/tables`
Ottiene tutti i tavoli con stato e ordini attivi

### GET `/api/tables/{table}`
Ottiene dettagli tavolo con ordine corrente

### POST `/api/tables/{table}/items`
Aggiunge un prodotto all'ordine del tavolo

Payload:
```json
{
    "dish_id": 1,
    "quantity": 2,
    "notes": "Cottura media",
    "extras": {
        "Parmigiano extra": 2.00,
        "Bacon extra": 3.00
    },
    "removals": ["Senza aglio", "Senza cipolla"]
}
```

### DELETE `/api/tables/items/{item}`
Rimuove un prodotto dall'ordine

### POST `/api/tables/{table}/clear`
Svuota tutti i prodotti dal tavolo

### POST `/api/tables/{table}/pay`
Incassa il conto e chiude l'ordine

### POST `/api/tables/save`
Crea o aggiorna un tavolo

### DELETE `/api/tables/{table}`
Elimina un tavolo (solo se non ha ordini attivi)

## Utilizzo Frontend

### Inizializzazione
Il sistema si inizializza automaticamente al caricamento della pagina rilevando se è mobile o desktop.

```javascript
// Viene creato automaticamente
let tableOrdersManager = new TableOrdersManager(isMobile);
```

### Aprire la modale prodotto
```javascript
// Da integrare con il componente dish-selector
tableOrdersManager.openProductModal({
    id: 1,
    name: "Carbonara",
    price: 12.50
});
```

### Funzioni disponibili
- `loadTables()` - Ricarica tutti i tavoli
- `selectTable(tableId)` - Seleziona un tavolo
- `addProductToTable()` - Aggiunge prodotto al tavolo corrente
- `removeItem(itemId)` - Rimuove un prodotto
- `clearTable()` - Svuota il tavolo
- `payTable()` - Incassa e chiude l'ordine

## Integrazione con DishSelector

Nel componente Livewire `DishSelector`, quando un piatto viene cliccato:

```javascript
// Esempio di integrazione
document.addEventListener('dish-clicked', (event) => {
    const dish = event.detail;
    tableOrdersManager.openProductModal(dish);
});
```

## Note Importanti

1. **Soft Deletes**: Tutte le tabelle usano soft deletes per mantenere lo storico
2. **Auto-calcolo**: I subtotali e totali vengono calcolati automaticamente dai Model
3. **Transazioni**: Tutte le operazioni critiche usano transazioni database
4. **CSRF**: Tutte le richieste POST includono il token CSRF
5. **Mobile First**: L'interfaccia è completamente responsive

## Troubleshooting

### Le migrazioni falliscono
- Verificare che il database sia accessibile
- Controllare che la tabella `dishes` esista (dipendenza)
- Verificare che la tabella `users` esista (dipendenza)

### I tavoli non vengono caricati
- Verificare che il seeder sia stato eseguito
- Controllare la console browser per errori JavaScript
- Verificare le route API con: `php artisan route:list`

### La modale non si apre
- Verificare che il meta tag CSRF sia presente nei layout
- Controllare che il file `table-orders.js` sia caricato
- Verificare la console browser per errori

## Sviluppi Futuri

- [ ] Gestione stampe in cucina
- [ ] Storico ordini e statistiche
- [ ] Prenotazioni tavoli
- [ ] Split payment
- [ ] Integrazione con stampanti fiscali
