# Configurazione Stampante POS con Docker

Questa guida spiega come configurare l'accesso alle stampanti POS dalla tua applicazione Laravel in esecuzione su Docker.

## Problema

I container Docker sono isolati dalla rete dell'host. Per accedere a una stampante sulla rete locale (es. `192.168.8.200`), è necessario configurare correttamente il networking.

## Soluzione Implementata

### 1. Estensioni PHP Aggiunte

Il `Dockerfile` è stato aggiornato per includere le estensioni necessarie:

- ✅ `mbstring` - Gestione stringhe multibyte
- ✅ `intl` - Internazionalizzazione
- ✅ Dipendenze di sistema: `libicu-dev`

### 2. Ricostruire l'Immagine Docker

Dopo aver modificato il `Dockerfile`, è necessario ricostruire l'immagine:

```bash
cd docker
docker-compose build app
docker-compose up -d
```

### 3. Verifica Installazione Estensioni

```bash
docker-compose exec app php -m | grep -E "(mbstring|intl)"
```

Dovresti vedere:
```
intl
mbstring
```

### 4. Completa l'Installazione Composer

```bash
docker-compose exec app composer install
```

## Configurazione Networking

### Opzione A: Bridge Network (Default) - **CONSIGLIATO**

Su Linux, la configurazione di default dovrebbe già funzionare. Il container può accedere alla rete locale attraverso il gateway Docker.

**Nessuna modifica necessaria** - Testa subito!

### Opzione B: Network Mode Host (Se Opzione A non funziona)

Se hai problemi di connessione, usa `network_mode: "host"`:

1. Apri `docker/docker-compose.yml`
2. Decommenta la riga `network_mode: "host"` nel servizio `app`:

```yaml
app:
  build: .
  network_mode: "host"  # <-- Decommenta questa riga
  volumes:
    - ../:/var/www/html
  # ... resto della configurazione
```

3. Riavvia i container:

```bash
docker-compose down
docker-compose up -d
```

⚠️ **ATTENZIONE**: Con `network_mode: "host"`:
- Il container userà direttamente la rete dell'host
- Potrebbe causare conflitti di porte
- Non funziona su Docker Desktop per Mac/Windows

### Opzione C: Extra Hosts (Solo per hostname custom)

Se vuoi usare un nome invece dell'IP:

```yaml
app:
  build: .
  extra_hosts:
    - "stampante-cucina:192.168.8.200"
    - "stampante-bar:192.168.8.201"
  # ... resto della configurazione
```

Poi nel codice puoi usare `stampante-cucina` invece di `192.168.8.200`.

## Test di Connettività

### Test 1: Script di Verifica Completo

Usa lo script fornito per testare la connessione:

```bash
docker-compose exec app php docker/test-printer.php 192.168.8.200
```

Lo script verifica:
- ✅ Estensioni PHP installate
- ✅ Connessione TCP alla stampante
- ✅ (Opzionale) Stampa di test

### Test 2: Ping dalla Rete

```bash
# Ping alla stampante
docker-compose exec app ping -c 3 192.168.8.200

# Se ping non è installato, installalo:
docker-compose exec app apt-get update && apt-get install -y iputils-ping
```

### Test 3: Test Porta TCP

```bash
# Verifica se la porta 9100 è raggiungibile
docker-compose exec app nc -zv 192.168.8.200 9100

# Se nc non è installato:
docker-compose exec app apt-get update && apt-get install -y netcat-openbsd
```

### Test 4: Test Manuale con PHP

```bash
docker-compose exec app php -r "
\$fp = @fsockopen('192.168.8.200', 9100, \$errno, \$errstr, 5);
if (\$fp) {
    echo 'Connessione riuscita!\n';
    fclose(\$fp);
} else {
    echo 'Connessione fallita: [\$errno] \$errstr\n';
}
"
```

## Configurazione Stampante nel Backoffice

1. Accedi all'applicazione: http://localhost/backoffice
2. Vai su "Stampanti"
3. Aggiungi stampante:
   - Nome: `Cucina`
   - IP: `192.168.8.200`
   - Attiva: ✓

4. Associa categorie alla stampante:
   - Vai su "Categorie"
   - Seleziona categoria (es. "Primi")
   - Scegli stampante "Cucina"
   - Salva

## Troubleshooting

### Problema: "Connection refused"

**Causa**: La stampante non è raggiungibile o la porta è chiusa.

**Soluzione**:
1. Verifica che la stampante sia accesa
2. Verifica l'IP con `ping 192.168.8.200`
3. Verifica che la porta 9100 sia aperta sulla stampante
4. Controlla il firewall della stampante

### Problema: "Network unreachable"

**Causa**: Il container non può accedere alla rete locale.

**Soluzione**:
1. Usa `network_mode: "host"` (vedi Opzione B sopra)
2. Su Docker Desktop (Mac/Windows), potrebbe essere necessaria una configurazione diversa
3. Verifica le impostazioni di rete Docker:
   ```bash
   docker network inspect docker_default
   ```

### Problema: "Class 'Mike42\Escpos\Printer' not found"

**Causa**: Le estensioni PHP non sono installate.

**Soluzione**:
```bash
docker-compose build app
docker-compose up -d
docker-compose exec app composer install
```

### Problema: Docker Desktop su Mac/Windows

Docker Desktop usa una VM Linux, quindi `network_mode: "host"` non funziona.

**Soluzione**:
1. La configurazione bridge di default dovrebbe funzionare
2. Verifica che la stampante sia sulla stessa rete del computer host
3. Potrebbe essere necessario configurare port forwarding manuale

## Verifica Finale

Dopo aver configurato tutto:

1. Accedi all'applicazione POS
2. Apri un tavolo
3. Aggiungi un prodotto (con categoria associata a una stampante)
4. La stampante dovrebbe stampare automaticamente!

Se non stampa, controlla i log:

```bash
docker-compose exec app tail -f storage/logs/laravel.log
```

Cerca messaggi tipo:
- `Stampante non raggiungibile`
- `Errore durante la stampa POS`

## Comandi Utili

```bash
# Ricostruire tutto
docker-compose build
docker-compose up -d

# Vedere log in tempo reale
docker-compose logs -f app

# Accedere al container
docker-compose exec app bash

# Testare connessione stampante
docker-compose exec app php docker/test-printer.php 192.168.8.200

# Riavviare solo il servizio app
docker-compose restart app

# Verificare estensioni PHP
docker-compose exec app php -m

# Installare tool di networking (ping, nc, etc)
docker-compose exec app apt-get update
docker-compose exec app apt-get install -y iputils-ping netcat-openbsd telnet
```

## Architettura di Rete

```
┌─────────────────────────────────────────┐
│  Host Machine (192.168.8.x)             │
│  ┌───────────────────────────────────┐  │
│  │  Docker Network (172.17.0.x)      │  │
│  │  ┌─────────────┐                  │  │
│  │  │ App         │                  │  │
│  │  │ Container   │──────────────────┼──┼──> Stampante POS
│  │  └─────────────┘     Gateway     │  │    192.168.8.200:9100
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

Con `network_mode: "host"`:
```
┌─────────────────────────────────────────┐
│  Host Machine (192.168.8.x)             │
│  ┌─────────────┐                        │
│  │ App         │ (usa direttamente      │
│  │ Container   │  la rete dell'host)    │
│  └─────────────┘                        │
└───────┬─────────────────────────────────┘
        │
        └──> Stampante POS (192.168.8.200:9100)
```

## Note Importanti

1. **Porta Standard**: Le stampanti POS usano tipicamente la porta **9100** (RAW printing)
2. **Firewall**: Assicurati che non ci siano firewall che bloccano la porta 9100
3. **IP Statico**: Configura la stampante con un IP statico per evitare che cambi
4. **Timeout**: Il sistema usa un timeout di 5 secondi per la connessione
5. **Non bloccante**: Se la stampa fallisce, l'ordine viene comunque salvato

## Link Utili

- Documentazione stampanti POS: Consulta il manuale del produttore
- [Docker Networking](https://docs.docker.com/network/)
- [ESC/POS Protocol](https://github.com/mike42/escpos-php)
