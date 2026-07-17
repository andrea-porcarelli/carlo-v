# Chiusura giornaliera Ditron — deploy e verifica

Questo documento descrive **come aggiornare l'agent** sul PC del ristorante (quello dove
gira RistoQuick e su cui hai avviato `DitronAgent` da PowerShell) e come **verificare la
nuova funzione di chiusura giornaliera**.

L'aggiornamento serve perché la Z fiscale (`azzgio tipo=2`) è una funzione nuova:
prima di questa modifica l'agent conosceva solo `POST /emit-receipt`, ora conosce
anche `POST /close-day`.

---

## Cosa serve avere sul PC Windows

- L'agent già installato in `C:\Program Files\DitronAgent\` (avviato precedentemente
  da PowerShell / o registrato come servizio Windows).
- Accesso amministratore alla macchina.
- Il nuovo binario `DitronAgent.exe` — vedi sezione **1. Ottenere il binario aggiornato**.

---

## 1. Ottenere il binario aggiornato

Il codice sorgente C# dell'agent è aggiornato in questo repo, ma **il binario compilato
va rigenerato**. Ci sono due strade — scegli in base a dove sei.

### 1.a — Compilare da Linux (con Docker)

Sulla macchina di sviluppo (Linux), dalla root del repo Carlo V:

```bash
cd ditron/agent
docker compose run --rm buildc
```

A fine build il file aggiornato è in `ditron/agent/artifact/DitronAgent.exe`
(~95 MB — self-contained, include già .NET runtime).

Copialo su chiavetta USB e portalo al PC del ristorante.

### 1.b — Compilare direttamente sul PC Windows

Se preferisci compilare in loco:

1. Installa **.NET SDK 8** da https://dotnet.microsoft.com/download/dotnet/8.0
2. Copia la cartella `ditron/agent` sul PC (es. `C:\Users\Public\DitronAgent-src\`)
3. Apri PowerShell **come amministratore**, poi:

```powershell
cd C:\Users\Public\DitronAgent-src
dotnet publish DitronAgent.csproj -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o C:\Users\Public\DitronAgent-build
```

Il binario è in `C:\Users\Public\DitronAgent-build\DitronAgent.exe`.

---

## 2. Sostituire il binario sul PC Windows

> **Regola d'oro:** non sovrascrivere mai `DitronAgent.exe` mentre il servizio è
> in esecuzione. Windows blocca il file e la copia fallisce silenziosamente
> (o lascia il vecchio binario in uso finché non riavvii).

### 2.a — Se l'agent gira come servizio Windows

Apri **PowerShell come amministratore** e:

```powershell
# 1. Ferma il servizio
Stop-Service -Name DitronAgent

# 2. Sostituisci il binario (path sorgente = dove hai messo il nuovo exe)
Copy-Item -Force `
    -Path 'D:\chiavetta\DitronAgent.exe' `
    -Destination 'C:\Program Files\DitronAgent\DitronAgent.exe'

# 3. Riavvia
Start-Service -Name DitronAgent
Get-Service   -Name DitronAgent    # Status deve essere Running
```

### 2.b — Se l'agent è avviato manualmente da PowerShell

Nella finestra dove l'agent è in esecuzione: `Ctrl+C` per fermarlo.

Poi in un'altra PowerShell **come amministratore**:

```powershell
Copy-Item -Force `
    -Path 'D:\chiavetta\DitronAgent.exe' `
    -Destination 'C:\Program Files\DitronAgent\DitronAgent.exe'
```

Riavvia come prima:

```powershell
cd 'C:\Program Files\DitronAgent'
.\DitronAgent.exe
```

---

## 3. Verifica che l'agent aggiornato risponda

Da una **seconda finestra PowerShell** sullo stesso PC:

```powershell
# Health check — deve tornare mode/scontrini_folder_exists ecc.
Invoke-RestMethod http://localhost:9090/health
```

Ora prova il nuovo endpoint **in modalità NonFiscal** (se `DitronAgent:Mode = NonFiscal`
in `appsettings.json`, il che è raccomandato in fase di test — vedi nota sotto).

```powershell
$body = @{
    idempotency_key = "close_day:test-manual"
    tipo            = 2
} | ConvertTo-Json

Invoke-RestMethod -Method Post `
    -Uri http://localhost:9090/close-day `
    -ContentType 'application/json' `
    -Body $body
```

**Cosa deve succedere:**
- L'agent scrive un file `scontrinoNN.txt` nella cartella spooler (`ScontriniFolder`
  di `appsettings.json`).
- WinEcrCom lo consuma e produce `scontrinoNN.err`.
- L'agent legge l'`.err`, cancella entrambi i file (comportamento standard di
  WinEcrCom) e ti risponde con `ok = true`, `raw_command`, `elapsed_ms`, `mode`.

In `NonFiscal` la cassa NON emette scontrino Z fiscale — stampa una simulazione con
la scritta "Z SIMULATA yyyy-MM-dd". Questo è voluto: consente il test end-to-end
del percorso Carlo V → agent → WinEcrCom → cassa senza toccare l'AdE.

Se `Mode = Fiscal`, il POST `/close-day` **emette per davvero l'azzeramento fiscale**.
Non farlo per test — usalo solo quando sei pronto a passare in produzione.

---

## 4. Firewall (solo se il PC Carlo V è diverso dal PC agent)

Se Carlo V gira su un altro PC in LAN, verifica che la porta 9090 sia
raggiungibile. Regola inbound (già presente se hai seguito le ISTRUZIONI_DITRON.md
originali):

```powershell
New-NetFirewallRule -DisplayName 'DitronAgent HTTP' -Direction Inbound `
    -Protocol TCP -LocalPort 9090 -Action Allow
```

---

## 5. Lato Carlo V — cosa fare dopo il deploy dell'agent

Sul server dove gira Carlo V (macchina Linux, Docker):

```bash
cd docker/
docker exec docker-cv-app-1 php artisan migrate
docker exec docker-cv-app-1 php artisan config:cache
docker exec docker-cv-app-1 php artisan route:cache
```

Le migration nuove creano `ditron_daily_closures` e la setting
`ditron_close_day_tipo` (default 2 = azzeramento breve, come RistoQuick).

Il cron di Laravel (`schedule:run` ogni minuto in `docker-cv-worker-1`) deve
essere attivo — se lo era già per `db:backup` non serve fare altro. In caso di
dubbio:

```bash
docker exec docker-cv-worker-1 crontab -l
```

Deve contenere una riga tipo `* * * * * cd /var/www/html && php artisan schedule:run`.

---

## 6. Come si usa (utente finale)

### Chiusura automatica

Ogni sera alle **23:59**, Laravel esegue `php artisan ditron:close-day --source=auto`.
Il comando:
1. Verifica che `corrispettivo_provider = ditron` (altrimenti no-op silenzioso).
2. Verifica che oggi non sia già stata chiusa (idempotenza per data).
3. Chiama `POST /close-day` sull'agent.
4. Persiste esito in `ditron_daily_closures`.
5. Manda notifica Telegram di esito (successo o errore) sui canali già configurati
   in `TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID`.

### Chiusura manuale (fallback)

Se la Z automatica è fallita (cassa spenta, rete giù, ecc.) o se serve una Z
straordinaria:

1. Backoffice → **Impostazioni**.
2. Nel pannello **"Chiusura giornaliera Ditron"** (visibile solo se sei admin e
   il provider è `ditron`), cliccare **"Esegui chiusura Ditron ora"**.
3. Confermare il popup.

Anche la chiusura manuale manda una notifica Telegram — così hai sempre traccia
di chi/quando/come.

---

## 7. Troubleshooting

| Sintomo | Cosa fare |
|---|---|
| `Invoke-RestMethod http://localhost:9090/close-day` → 404 | L'agent è ancora la vecchia versione. Rifai la sostituzione del binario (sezione 2) verificando di aver **fermato** il servizio prima di copiare. |
| L'agent risponde ma `ok = false`, `error = "Timeout..."` | WinEcrCom non ha prodotto il `.err`. Verifica che il servizio WinEcrCom sia attivo e che `ScontriniFolder` in `appsettings.json` sia il path corretto. |
| Chiusura fallisce con `connect_error` da Laravel | `ditron_agent_url` in Impostazioni Carlo V non è raggiungibile. Testa `Invoke-RestMethod http://IP:9090/health` da un browser/terminal Carlo V. |
| Nessuna notifica Telegram | Verifica che `TELEGRAM_BOT_TOKEN` e `TELEGRAM_CHAT_ID` siano impostati nel `.env` di Carlo V. |
| Vuoi rifare oggi la Z che è già `status=done` | La stessa data non viene richiamata (idempotenza). Se serve davvero, cancella la riga `ditron_daily_closures` di oggi (via phpMyAdmin) e rilancia. |
