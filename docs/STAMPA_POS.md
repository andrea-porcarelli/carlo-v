# Sistema di Stampa POS

Questo documento descrive come completare l'installazione e utilizzare il sistema di stampa per stampanti POS 80mm.

> **📦 Stai usando Docker?** Leggi la [guida Docker specifica](DOCKER_STAMPANTE_POS.md) per configurare la stampante con container Docker.

## Prerequisiti

### 1. Installazione delle estensioni PHP

Il pacchetto `mike42/escpos-php` richiede le seguenti estensioni PHP:

```bash
# Su Ubuntu/Debian
sudo apt-get update
sudo apt-get install php8.3-mbstring php8.3-intl

# Su Fedora/RHEL/CentOS
sudo dnf install php-mbstring php-intl

# Su macOS (con Homebrew)
brew install php
# Le estensioni sono già incluse

# Dopo l'installazione, riavvia il server web/PHP-FPM
sudo systemctl restart php8.3-fpm  # o il tuo servizio PHP
```

### 2. Verifica dell'installazione

Dopo aver installato le estensioni, verifica che siano attive:

```bash
php -m | grep -E "(mbstring|intl)"
```

Dovresti vedere:
```
intl
mbstring
```

### 3. Completa l'installazione di Composer

Dopo aver installato le estensioni, completa l'installazione del pacchetto:

```bash
composer install
```

## Configurazione

### 1. Configurare le Stampanti

1. Accedi al backoffice dell'applicazione
2. Vai alla sezione "Stampanti"
3. Aggiungi una nuova stampante specificando:
   - **Nome**: Nome identificativo della stampante (es. "Cucina", "Bar", "Pizza")
   - **IP**: Indirizzo IP della stampante POS (es. "192.168.1.100")
   - **Attiva**: Spunta per rendere la stampante operativa

### 2. Associare Categorie alle Stampanti

1. Accedi alla sezione "Categorie"
2. Per ogni categoria, seleziona la stampante di destinazione
   - Esempio: Categoria "Primi" → Stampante "Cucina"
   - Esempio: Categoria "Bevande" → Stampante "Bar"
   - Esempio: Categoria "Pizza" → Stampante "Pizza"

## Come Funziona

### Flusso di Stampa

Quando un operatore aggiunge o modifica un prodotto in un tavolo:

1. **Sistema raggruppamento automatico**:
   - Gli articoli vengono raggruppati automaticamente per stampante in base alla categoria del prodotto

2. **Cosa viene stampato**:
   - Numero del tavolo (in grande)
   - Data e ora dell'operazione
   - Tipo di operazione (NUOVO ORDINE, MODIFICA, ANNULLAMENTO)
   - Elenco dei prodotti aggiunti/modificati:
     - Quantità
     - Nome del piatto
     - Note (se presenti)
     - Aggiunte/Extra con prezzo (se presenti)
     - Rimozioni (se presenti)

3. **Solo prodotti interessati**:
   - Vengono stampati SOLO i prodotti aggiunti o modificati nell'operazione corrente
   - NON viene stampato l'intero ordine del tavolo

### Esempio di Comanda Stampata

```
=====================================
      TAVOLO 12
=====================================
21/12/2025 14:35:22

*** NUOVO ORDINE ***

------------------------------------------------
2x    Spaghetti Carbonara
      Note: Cottura al dente
      + Parmigiano extra (€2,00)

1x    Pizza Margherita
      - Senza basilico

1x    Bistecca alla Fiorentina
      Note: Cottura media
------------------------------------------------
```

## Gestione degli Errori

Il sistema di stampa è progettato per essere **non bloccante**:

- Se la stampante non è raggiungibile, l'ordine viene comunque salvato
- Gli errori di stampa vengono registrati nei log ma non impediscono l'operazione
- L'utente riceve sempre conferma dell'operazione, anche se la stampa fallisce

### Verificare gli Errori di Stampa

I log si trovano in `storage/logs/laravel.log`. Cerca messaggi con:

```bash
grep "Errore durante la stampa" storage/logs/laravel.log
```

## Configurazione di Rete delle Stampanti

### Configurazione Stampante POS

Le stampanti POS 80mm supportano tipicamente la stampa di rete su porta **9100** (standard RAW printing).

1. **Accedi all'interfaccia web della stampante** (es. http://192.168.1.100)
2. Configura:
   - IP statico (consigliato)
   - Porta: 9100
   - Protocollo: RAW/TCP

### Test di Connettività

Puoi verificare la connessione alla stampante con:

```bash
# Test connessione porta 9100
nc -zv 192.168.1.100 9100

# O con telnet
telnet 192.168.1.100 9100
```

Se la connessione ha successo, la stampante è raggiungibile.

### Test di Stampa Manuale

Puoi testare la stampa direttamente con:

```bash
echo "Test stampa" | nc 192.168.1.100 9100
```

## Risoluzione Problemi

### La stampante non stampa

1. **Verifica che la stampante sia online**:
   ```bash
   ping 192.168.1.100
   ```

2. **Verifica che la porta 9100 sia aperta**:
   ```bash
   nc -zv 192.168.1.100 9100
   ```

3. **Controlla i log**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **Verifica la configurazione**:
   - La categoria ha una stampante associata?
   - La stampante è attiva nel backoffice?
   - L'IP della stampante è corretto?

### Le estensioni PHP non sono installate

Se vedi errori relativi a `mbstring` o `intl`:

```bash
# Verifica quali estensioni sono installate
php -m

# Installa le estensioni mancanti
sudo apt-get install php8.3-mbstring php8.3-intl

# Riavvia PHP
sudo systemctl restart php8.3-fpm
```

### Stampante raggiungibile ma non stampa

1. Verifica che la stampante supporti il protocollo ESC/POS
2. Controlla che la porta sia 9100 (standard per stampanti di rete)
3. Alcuni modelli richiedono configurazioni specifiche (consulta il manuale)

## Caratteristiche Tecniche

- **Protocollo**: ESC/POS (standard per stampanti termiche)
- **Connessione**: TCP/IP (porta 9100)
- **Larghezza carta**: 80mm (48 caratteri per riga)
- **Timeout connessione**: 5 secondi
- **Codifica**: UTF-8 (supporto caratteri italiani)
- **Taglio carta**: Automatico (se supportato dalla stampante)

## Sviluppo e Personalizzazione

### File Principali

- **Servizio di stampa**: `app/Services/PrinterService.php`
- **Interfaccia**: `app/Interfaces/PrinterServiceInterface.php`
- **Integrazione controller**: `app/Http/Controllers/Frontoffice/TableOrderController.php`
- **Modello stampante**: `app/Models/Printer.php`

### Personalizzare il Layout di Stampa

Modifica il metodo `printToDevice()` in `app/Services/PrinterService.php` per:

- Cambiare dimensioni del testo
- Aggiungere logo o intestazione
- Modificare il formato della comanda
- Aggiungere informazioni aggiuntive

### Estendere le Funzionalità

Il `PrinterServiceInterface` può essere esteso per:

- Stampare ricevute/scontrini
- Stampare riepiloghi giornalieri
- Stampare liste inventario
- Generare report stampati

## Supporto

Per problemi tecnici o domande:

1. Controlla i log: `storage/logs/laravel.log`
2. Verifica la connettività di rete
3. Consulta la documentazione del produttore della stampante
4. Verifica che le estensioni PHP siano installate correttamente
