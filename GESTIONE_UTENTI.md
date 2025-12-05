# Gestione Utenti - Backoffice

## 📋 Panoramica

È stata implementata una sezione completa per la gestione degli utenti del sistema nel backoffice. La pagina è accessibile dal menu principale sotto la voce **Utenti** (visibile solo agli amministratori).

## 🎯 Funzionalità Implementate

### 1. **Lista Utenti (Index)**
- Datatable con tutti gli utenti del sistema
- Ordinamento per data di creazione (più recente prima)
- Colonne visualizzate:
  - **ID**: Numero identificativo utente
  - **Utente**: Nome completo e email
  - **Ruolo**: Badge colorato (Rosso per Admin, Blu per Operator)
  - **Data Creazione**: Timestamp creazione account
  - **Azioni**: Pulsanti modifica ed elimina

### 2. **Filtri di Ricerca**
- **Nome o Email**: Ricerca full-text su nome e email
- **Ruolo**: Filtro dropdown (Tutti/Amministratore/Operatore)
- Pulsante "Cerca" per applicare i filtri
- Pulsante "Aggiungi" per creare nuovo utente

### 3. **Creazione Nuovo Utente**
Form completo con validazione:
- **Nome Completo**: Campo obbligatorio
- **Email**: Campo obbligatorio, validato e univoco
- **Ruolo**: Select dropdown (Admin/Operatore)
- **Password**: Obbligatoria, minimo 8 caratteri
- **Conferma Password**: Deve corrispondere alla password

Alert informativi:
- Info sulla lunghezza minima password
- Feedback immediato su errori di validazione

### 4. **Modifica Utente**
Form di modifica con:
- **Tutti i campi** della creazione
- **Password opzionale**: Lasciare vuoto per non modificare
- **Info Account**: Box informativo con:
  - Data di creazione
  - Ultimo aggiornamento
  - Stato verifica email
- **Pulsante Elimina**: Disponibile solo se non sei tu stesso

### 5. **Eliminazione Utente**
- Conferma richiesta prima dell'eliminazione
- Protezione: Non puoi eliminare il tuo account
- Eliminazione sia dalla lista che dalla pagina di modifica

## 🔒 Sicurezza e Validazione

### Regole di Validazione
**Creazione:**
```php
'name' => 'required|string|max:255'
'email' => 'required|email|unique:users,email'
'password' => 'required|string|min:8|confirmed'
'role' => 'required|in:admin,operator'
```

**Modifica:**
```php
'name' => 'required|string|max:255'
'email' => 'required|email|unique:users,email (escluso utente corrente)'
'password' => 'nullable|string|min:8|confirmed'
'role' => 'required|in:admin,operator'
```

### Protezioni Implementate
- ✅ Non puoi eliminare il tuo account
- ✅ Email univoche nel sistema
- ✅ Password hashate con bcrypt
- ✅ Validazione lato server
- ✅ Feedback errori dettagliati
- ✅ Transazioni database per consistenza

## 👥 Ruoli Utente

### Amministratore (admin)
- Accesso completo al backoffice
- Gestione utenti
- Gestione ristorante (vendite, piatti, categorie, ecc.)
- Gestione fornitori e fatture
- Accesso ai log di sistema

### Operatore (operator)
- Accesso limitato alle funzionalità operative
- Può gestire ordini e tavoli
- Non può accedere a configurazioni avanzate
- Non può gestire altri utenti

## 📁 File Creati

### Controller
```
app/Http/Controllers/Backoffice/UserController.php
```

Metodi implementati:
- `index()`: Visualizza lista utenti
- `datatable()`: Fornisce dati per datatable (con filtri)
- `create()`: Form creazione nuovo utente
- `store()`: Salva nuovo utente
- `show($id)`: Form modifica utente
- `edit($id)`: Aggiorna utente esistente
- `destroy($id)`: Elimina utente

### Views
```
resources/views/backoffice/users/index.blade.php
resources/views/backoffice/users/create.blade.php
resources/views/backoffice/users/edit.blade.php
```

### Routes
Aggiunte in `routes/web.php`:
```php
Route::group(['prefix' => '/users', 'as' => 'users.'], function() {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/datatable', [UserController::class, 'datatable'])->name('datatable');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{id}', [UserController::class, 'show'])->name('show');
    Route::put('/{id}', [UserController::class, 'edit'])->name('edit');
    Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
});
```

### Menu
Aggiornato `resources/views/backoffice/components/nav-bar.blade.php`:
- Aggiunta voce "Utenti" con icona users
- Visibile solo per amministratori
- Posizionata dopo Fornitori e prima dei Logs

## 🔧 Dettagli Tecnici

### Database
La tabella `users` contiene già:
```php
- id (bigint)
- name (string)
- email (string, unique)
- role (enum: 'admin', 'operator')
- password (hashed)
- remember_token
- email_verified_at (timestamp)
- created_at, updated_at
```

### Query Filtri
```php
// Filtro ruolo
->where('role', $filters['role'])

// Filtro ricerca
->where(function($q) {
    $q->where('name', 'like', '%search%')
      ->orWhere('email', 'like', '%search%');
})
```

### Password Hashing
```php
// Creazione
'password' => Hash::make($validated['password'])

// Modifica (solo se fornita)
if (!empty($validated['password'])) {
    $updateData['password'] = Hash::make($validated['password']);
}
```

### AJAX Operations
Tutte le operazioni (create, edit, delete) utilizzano AJAX:
```javascript
$.ajax({
    url: form.attr('action'),
    type: 'POST|PUT|DELETE',
    data: form.serialize(),
    success: function(response) { ... },
    error: function(xhr) { ... }
});
```

## 🎨 Design e UI

### Badge Ruoli
- **Amministratore**: Badge rosso (`badge-danger`)
- **Operatore**: Badge blu (`badge-info`)

### Icone Font Awesome
- `fa-users`: Menu e lista utenti
- `fa-user-plus`: Creazione utente
- `fa-user-edit`: Modifica utente
- `fa-edit`: Azione modifica
- `fa-trash`: Azione elimina
- `fa-save`: Salva modifiche
- `fa-arrow-left`: Torna indietro
- `fa-lock`: Sezione password
- `fa-key`: Cambio password
- `fa-info-circle`: Informazioni account

### Alert Box
- **Info** (blu): Informazioni generali
- **Warning** (giallo): Requisiti password
- **Secondary** (grigio): Info account esistente

## 📊 Esempio Visualizzazione

### Lista Utenti
```
┌────────────────────────────────────────────────────────────┐
│ # │ Utente                 │ Ruolo          │ Creazione    │
├───┼────────────────────────┼────────────────┼──────────────┤
│ 1 │ Mario Rossi           │ [Amministratore]│ 12/11/2025   │
│   │ mario@example.com     │ (badge rosso)  │ 14:30        │
├───┼────────────────────────┼────────────────┼──────────────┤
│ 2 │ Lucia Verdi           │ [Operatore]    │ 11/11/2025   │
│   │ lucia@example.com     │ (badge blu)    │ 09:15        │
└────────────────────────────────────────────────────────────┘
```

### Form Creazione/Modifica
```
┌─────────────────────────────────────────┐
│ Informazioni Utente                     │
├─────────────────────────────────────────┤
│ Nome Completo: [________________]       │
│ Email:         [________________]       │
│ Ruolo:         [▼ Amministratore]       │
│                                         │
│ ⚠️ La password deve essere di almeno    │
│    8 caratteri                          │
│                                         │
│ Password:      [________________]       │
│ Conferma:      [________________]       │
│                                         │
│ [Annulla]              [Crea Utente]   │
└─────────────────────────────────────────┘
```

## 🚀 Utilizzo

### Accedere alla Gestione Utenti
1. Login al backoffice come **Amministratore**
2. Menu laterale → **Utenti**
3. Visualizza lista completa utenti

### Creare un Nuovo Utente
1. Click su "Aggiungi" (icona +)
2. Compilare il form:
   - Nome completo
   - Email (univoca)
   - Selezionare ruolo
   - Inserire password (min 8 caratteri)
   - Confermare password
3. Click su "Crea Utente"
4. Conferma e redirect alla lista

### Modificare un Utente
1. Dalla lista, click sull'icona ✏️ (Modifica)
2. Modificare i campi desiderati
3. **Opzionale**: Cambiare password
   - Lasciare vuoto per mantenerla
4. Click su "Salva Modifiche"
5. Conferma e redirect alla lista

### Eliminare un Utente
**Metodo 1 - Dalla Lista:**
1. Click sull'icona 🗑️ (Elimina)
2. Confermare l'eliminazione
3. L'utente viene rimosso

**Metodo 2 - Dalla Modifica:**
1. Aprire la modifica utente
2. Click su "Elimina Utente" (rosso)
3. Confermare l'eliminazione
4. Redirect alla lista

**Nota**: Non puoi eliminare il tuo account!

### Cercare Utenti
1. Usare il campo "Nome o Email" per ricerca testuale
2. Selezionare un ruolo specifico dal dropdown
3. Click su "Cerca"
4. La tabella si aggiorna con i risultati filtrati

## 🔮 Sviluppi Futuri

### Funzionalità Pianificate
- [ ] Gestione permessi granulare
- [ ] Ruoli personalizzabili
- [ ] Assegnazione multipla ruoli
- [ ] Log attività per utente
- [ ] Verifica email obbligatoria
- [ ] Reset password via email
- [ ] Blocco/Sblocco account
- [ ] Sessioni attive utente
- [ ] Ultimo accesso
- [ ] Export lista utenti CSV/Excel
- [ ] Importazione bulk utenti
- [ ] Gruppi/Team utenti

### Miglioramenti Possibili
- Autenticazione a due fattori (2FA)
- Single Sign-On (SSO)
- Integrazione LDAP/Active Directory
- Password policy configurabile
- Scadenza password
- Storico modifiche utente
- Notifiche email su creazione/modifica
- Avatar profilo utente
- Preferenze personalizzate

## 📝 Note Importanti

### Accesso alla Gestione
- **Solo amministratori** possono gestire gli utenti
- Gli operatori NON vedono la voce di menu
- Protezione a livello di route e middleware

### Password
- Minimo 8 caratteri richiesti
- Hashing automatico con bcrypt
- In modifica, lasciare vuoto per non cambiarla
- Conferma sempre richiesta

### Email
- Deve essere univoca nel sistema
- Validazione formato email
- Non case-sensitive (es: Mario@test.com = mario@test.com)

### Eliminazione
- Conferma sempre richiesta
- Non reversibile (soft delete disponibile)
- Protezione contro auto-eliminazione
- Considera dipendenze (ordini, attività, ecc.)

## ⚠️ Attenzione

### Prima di Eliminare un Utente
Verifica che l'utente non sia:
- Referenziato in ordini come `waiter_id`
- Autore di log o attività importanti
- L'unico amministratore del sistema

### Cambio Ruolo
Cambiare un amministratore in operatore:
- Perderà accesso alla gestione utenti
- Perderà accesso ai log
- Perderà accesso alle configurazioni
- Non potrà tornare admin da solo

## ✅ Checklist Implementazione

- ✅ Controller UserController creato
- ✅ Routes aggiunte e protette
- ✅ View index con datatable funzionante
- ✅ View create con form e validazione
- ✅ View edit con form e info account
- ✅ Filtri di ricerca implementati
- ✅ Validazione completa lato server
- ✅ Gestione errori e feedback utente
- ✅ Protezione anti auto-eliminazione
- ✅ Password hashing sicuro
- ✅ AJAX per tutte le operazioni
- ✅ Menu item aggiunto (solo admin)
- ✅ Design consistente con backoffice
- ✅ Badge ruoli colorati
- ✅ Icone appropriate
- ✅ Transazioni database
- ✅ Logging errori
- ✅ Documentazione completa

## 🎉 Conclusione

La sezione Gestione Utenti è ora completamente funzionale e integrata nel backoffice. Permette agli amministratori di:
- Creare nuovi utenti con ruoli specifici
- Modificare utenti esistenti
- Eliminare utenti (con protezioni)
- Cercare e filtrare utenti
- Gestire password in modo sicuro

Il sistema è pronto per l'utilizzo in produzione con tutte le protezioni e validazioni necessarie.

**Importante**: Assicurarsi sempre che ci sia almeno un utente amministratore attivo nel sistema!
