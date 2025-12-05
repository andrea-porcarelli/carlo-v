# Pagina Vendite - Backoffice

## 📋 Panoramica

È stata implementata una nuova sezione nel backoffice per la visualizzazione e gestione delle vendite completate del ristorante. La pagina è accessibile dal menu **Ristorante > Vendite**.

## 🎯 Funzionalità Implementate

### 1. **Lista Vendite (Index)**
- Datatable con tutte le vendite completate (ordini con status "paid")
- Ordinamento per data di chiusura (più recente prima)
- Colonne visualizzate:
  - **ID**: Numero identificativo vendita
  - **Tavolo / Data**: Numero tavolo e data/ora chiusura
  - **N° Prodotti**: Conteggio prodotti nell'ordine
  - **Totale**: Importo totale vendita
  - **Cameriere**: Nome del cameriere che ha gestito l'ordine
  - **Durata**: Tempo trascorso dall'apertura alla chiusura (in minuti)
  - **Azioni**: Pulsante per visualizzare i dettagli

### 2. **Filtri di Ricerca**
- **Data da**: Filtra vendite dalla data specificata
- **Data a**: Filtra vendite fino alla data specificata
- **Numero Tavolo**: Filtra per numero specifico del tavolo

### 3. **Statistiche Riepilogative**
Card con statistiche in tempo reale (calcolate sui dati filtrati):
- **Totale Vendite**: Somma di tutte le vendite visualizzate
- **Numero Ordini**: Conteggio ordini
- **Media per Ordine**: Media dell'importo per ordine
- **Prodotti Venduti**: Totale prodotti venduti

### 4. **Pagina Dettaglio Vendita**
Visualizzazione completa di una vendita specifica con:

#### Informazioni Vendita
- ID Vendita
- Numero Tavolo (con badge)
- Stato (Pagato)
- Data e ora di apertura
- Data e ora di chiusura
- Durata (in minuti e formato umano)
- Nome cameriere

#### Totale Vendita
Card grande con il totale dell'ordine e numero di prodotti

#### Elenco Prodotti Ordinati
Tabella dettagliata con:
- **Numero progressivo**
- **Nome prodotto** con categoria
- **Quantità**
- **Prezzo unitario**
- **Subtotale**

Per ogni prodotto vengono visualizzati:
- ✅ **Supplementi** (extras): Nome e prezzo aggiuntivo
  - Visualizzati con badge verde e icona check
- ❌ **Rimozioni**: Ingredienti rimossi
  - Visualizzati con badge giallo e icona minus
- 📝 **Note per la cucina**: Note speciali
  - Visualizzate con badge grigio e alert box
- ⚠️ **Allergeni**: Lista allergeni del piatto
  - Visualizzati con badge rosso e icona warning

#### Azioni Disponibili
- **Torna alle Vendite**: Ritorna alla lista
- **Stampa**: Stampa la vendita (ottimizzata per stampa)
- **Esporta PDF**: (Funzionalità in sviluppo)

## 📁 File Creati

### Controller
```
app/Http/Controllers/Backoffice/SalesController.php
```
- `index()`: Visualizza la pagina index
- `datatable()`: Fornisce i dati per la datatable
- `show($id)`: Visualizza i dettagli di una vendita
- `export()`: (Placeholder per futura implementazione export)

### Views
```
resources/views/backoffice/sales/index.blade.php
resources/views/backoffice/sales/show.blade.php
```

### Routes
Aggiunte in `routes/web.php`:
```php
Route::group(['prefix' => '/sales', 'as' => 'sales.'], function() {
    Route::get('/', [SalesController::class, 'index'])->name('index');
    Route::get('/datatable', [SalesController::class, 'datatable'])->name('datatable');
    Route::get('/{id}', [SalesController::class, 'show'])->name('show');
    Route::post('/export', [SalesController::class, 'export'])->name('export');
});
```

### Menu
Aggiornato `resources/views/backoffice/components/nav-bar-restaurant.blade.php`:
- Aggiunta voce "Vendite" con icona cash-register
- Prima voce del menu Ristorante

## 🔧 Dettagli Tecnici

### Query Database
```php
TableOrder::with(['restaurantTable', 'items.dish', 'waiter'])
    ->where('status', 'paid')
    ->orderBy('closed_at', 'desc')
```

### Eager Loading
Per ottimizzare le performance, vengono caricati:
- `restaurantTable`: Info tavolo
- `items.dish.category`: Prodotti con categoria
- `items.dish.allergens`: Allergeni dei piatti
- `waiter`: Info cameriere

### Calcoli
- **Subtotale prodotto**: Calcolato automaticamente dal Model `OrderItem`
  - Formula: `(prezzo_unitario + somma_extra) * quantità`
- **Totale ordine**: Somma di tutti i subtotali
- **Durata**: Differenza tra `opened_at` e `closed_at`

### Filtri Datatable
Implementati tramite query scope:
```php
// Filtro data da
->whereDate('closed_at', '>=', $filters['date_from'])

// Filtro data a
->whereDate('closed_at', '<=', $filters['date_to'])

// Filtro numero tavolo
->whereHas('restaurantTable', function($q) {
    $q->where('table_number', $filters['table_number']);
})
```

## 🎨 Design e Stile

### Consistenza con il Backoffice
La pagina segue lo stesso design pattern delle altre pagine:
- Layout con panel Bootstrap
- Breadcrumb navigation
- Datatable con paginazione e ricerca
- Form controls standardizzati
- Card con statistiche

### Colori e Badge
- **Verde (success)**: Totali, subtotali, supplementi
- **Rosso (danger)**: Allergeni, rimozioni
- **Giallo (warning)**: Note cucina
- **Blu (primary)**: Info generali
- **Grigio (secondary)**: Dati neutri

### Icone Font Awesome
- `fa-cash-register`: Menu vendite
- `fa-info-circle`: Informazioni
- `fa-check-circle`: Status pagato
- `fa-plus-circle`: Supplementi
- `fa-minus-circle`: Rimozioni
- `fa-sticky-note`: Note
- `fa-exclamation-triangle`: Allergeni
- `fa-print`: Stampa
- `fa-file-pdf`: Export PDF

## 📊 Esempio Visualizzazione

### Lista Vendite
```
┌────────────────────────────────────────────────────────────┐
│ # │ Tavolo/Data        │ Prodotti │ Totale   │ Cameriere  │
├───┼────────────────────┼──────────┼──────────┼────────────┤
│ 5 │ Tavolo 3          │ 4 prod.  │ €45.50   │ Mario      │
│   │ 12/11/2025 20:30  │          │          │            │
├───┼────────────────────┼──────────┼──────────┼────────────┤
│ 4 │ Tavolo 7          │ 2 prod.  │ €28.00   │ Lucia      │
│   │ 12/11/2025 19:45  │          │          │            │
└────────────────────────────────────────────────────────────┘
```

### Dettaglio Prodotto
```
Carbonara                                      Qta: 2   €12.00   €28.00
├─ Categoria: Primi Piatti
├─ ✅ Supplementi:
│  ├─ Parmigiano extra (+€2.00)
│  └─ Bacon extra (+€3.00)
├─ ❌ Rimozioni:
│  └─ Senza aglio
├─ 📝 Note:
│  └─ Cottura al dente
└─ ⚠️ Allergeni:
   ├─ Glutine
   ├─ Uova
   └─ Latticini
```

## 🚀 Utilizzo

### Accesso alla Pagina
1. Login al backoffice
2. Menu laterale → **Ristorante**
3. Click su **Vendite**

### Visualizzare Vendite Specifiche
1. Utilizzare i filtri di ricerca:
   - Selezionare data da/a
   - Inserire numero tavolo (opzionale)
   - Click su "Cerca"
2. Click sull'icona 👁️ nella colonna azioni

### Stampare una Vendita
1. Aprire il dettaglio vendita
2. Click su "Stampa"
3. La pagina si adatta automaticamente per la stampa

## 🔮 Sviluppi Futuri

### Implementazioni Pianificate
- [ ] Export PDF con logo e intestazione
- [ ] Export CSV/Excel per analisi dati
- [ ] Grafici e statistiche avanzate
- [ ] Filtro per cameriere
- [ ] Filtro per fascia oraria
- [ ] Confronto vendite tra periodi
- [ ] Report giornalieri/settimanali/mensili
- [ ] Invio automatico report via email
- [ ] Integrazione con contabilità
- [ ] Dashboard real-time con statistiche live

### Possibili Miglioramenti
- Ricerca full-text nei prodotti
- Filtri avanzati (range prezzo, categoria prodotti)
- Esportazione multipla (selezione vendite)
- Stampa batch di più vendite
- Annotazioni post-vendita
- Link a gestione fiscale/scontrini

## 📝 Note Importanti

### Dati Visualizzati
- Vengono mostrate **solo** le vendite completate (status = 'paid')
- Gli ordini ancora aperti non appaiono in questa lista
- Le vendite cancellate non vengono visualizzate

### Performance
- La datatable usa server-side processing per gestire grandi volumi
- Eager loading ottimizza le query al database
- Le statistiche vengono calcolate lato client sui dati filtrati

### Sicurezza
- Accesso protetto da middleware auth
- Solo utenti loggati possono accedere
- Validazione dei parametri nelle query

## ✅ Checklist Implementazione

- ✅ Controller SalesController creato
- ✅ Route aggiunte e testate
- ✅ View index con datatable
- ✅ View dettaglio con tutte le info
- ✅ Filtri di ricerca funzionanti
- ✅ Statistiche riepilogative
- ✅ Menu item aggiunto
- ✅ Design consistente con backoffice
- ✅ Visualizzazione supplementi
- ✅ Visualizzazione rimozioni
- ✅ Visualizzazione note
- ✅ Visualizzazione allergeni
- ✅ Funzione stampa ottimizzata
- ✅ Documentazione completa

## 🎉 Conclusione

La sezione Vendite è ora completamente funzionale e integrata nel backoffice. Permette una visualizzazione chiara e dettagliata di tutte le transazioni completate, con particolare attenzione ai dettagli dei prodotti ordinati (supplementi, note, allergeni).

Il sistema è pronto per l'utilizzo in produzione e può essere facilmente esteso con nuove funzionalità in futuro.
