mi c# Ricognizione gestionale fiscale esistente — Procedura operativa

**Scopo del documento.** Raccogliere tutte le informazioni necessarie per sostituire l'attuale gestionale custom (che emette scontrini fiscali sulla cassa Ditron RT via Ethernet) con un'integrazione nativa in Carlo V. Il documento è pensato per essere eseguito on-site da un tecnico, anche non esperto del sistema, **senza interrompere il servizio fiscale**.

**Tempo stimato totale.** 4-6 ore di lavoro, da spalmare su 2 sessioni (una a esercizio chiuso per la parte invasiva, una a esercizio aperto per la cattura del traffico reale).

**Regola d'oro.** *Si copia, non si modifica.* Tutto ciò che viene fatto sul PC del gestionale deve essere non invasivo: lettura, copia file, sniffing passivo. Nessuna modifica, nessuna disinstallazione, nessuna chiusura forzata di processi. Se qualcosa va storto, **non si interviene**: si annota e si chiama indietro.

---

## 0. Cosa preparare prima di andare sul posto

### 0.1 Materiale fisico
- [ ] Chiavetta USB **almeno 32 GB**, formattata exFAT (per portarsi via cartelle gestionale + pcap)
- [ ] Hard disk esterno **almeno 250 GB** (per backup integrale del PC)
- [ ] Cavo Ethernet di scorta (3-5 m)
- [ ] Smartphone con fotocamera per foto del setup fisico
- [ ] Penna + blocco per appunti
- [ ] Adattatore USB-Ethernet (nel caso il PC del gestionale abbia una sola porta NIC occupata)

### 0.2 Software da portare già scaricato sulla chiavetta
> Scaricarli **prima** da un PC con internet — sul PC del gestionale potrebbe non esserci connessione o potremmo non voler installare nulla.

- [ ] **Wireshark** (installer Windows offline): https://www.wireshark.org/download.html
- [ ] **Process Explorer** (Sysinternals, standalone exe): https://learn.microsoft.com/sysinternals/downloads/process-explorer
- [ ] **TCPView** (Sysinternals, standalone exe): https://learn.microsoft.com/sysinternals/downloads/tcpview
- [ ] **Autoruns** (Sysinternals, standalone exe): https://learn.microsoft.com/sysinternals/downloads/autoruns
- [ ] **dnSpy** (decompilatore .NET, standalone): https://github.com/dnSpyEx/dnSpy/releases
- [ ] **PE-bear** o **DIE (Detect It Easy)** (identificatore linguaggio binari): https://github.com/horsicq/DIE-engine/releases
- [ ] **7-Zip** standalone (per eventuali estrazioni)
- [ ] **Notepad++** (per leggere file INI/log con encoding strani)

### 0.3 Informazioni da raccogliere PRIMA di andare on-site
- [ ] Nome del titolare e nome del cassiere "esperto" (sanno cose che noi non vediamo nei file)
- [ ] Orari di chiusura del locale (per pianificare la sessione invasiva)
- [ ] Conferma scritta dal titolare di **non emettere scontrini di prova** durante la ricognizione senza autorizzazione (gli scontrini vanno comunque all'Agenzia Entrate tramite RT)
- [ ] Eventuale accordo col commercialista sulla finestra di test

### 0.4 Regole di sicurezza fiscali
> Da leggere e capire prima di iniziare. **Sono importanti.**

1. La cassa Ditron è un **Registratore Telematico**: ogni scontrino emesso viene inviato all'Agenzia delle Entrate. Non si emettono scontrini "di prova" senza informare commercialista/titolare.
2. **L'azzeramento giornaliero** (chiusura fiscale) è obbligatorio una volta al giorno se ci sono state emissioni. Va capito **chi lo fa e come** prima di toccare qualcosa.
3. Non si stacca mai bruscamente la cassa dalla rete mentre sta emettendo o ricevendo un comando.
4. Non si spegne mai il PC del gestionale con il pulsante di alimentazione: sempre shutdown ordinato.

---

## 1. Sessione 1 — On-site a esercizio aperto (1-2 ore)

> Obiettivo: osservare il sistema in funzione **senza toccarlo**. Foto, comandi di sola lettura, sniff passivo.

### 1.1 Documentazione fotografica del setup fisico
- [ ] Foto del PC del gestionale: marca, modello, etichetta laterale/posteriore
- [ ] Foto di **tutte** le porte del PC con i cavi inseriti (USB, seriale, parallela, Ethernet — annotare cosa va dove)
- [ ] Foto della cassa Ditron: lato anteriore (display), lato posteriore (etichetta modello + seriale + porte)
- [ ] Foto del cavo Ethernet della cassa: dove va? Switch? Router? Direttamente al PC?
- [ ] Foto di eventuali altri dispositivi collegati: cassetto contanti, display cliente, lettore barcode, stampante non fiscale, POS
- [ ] Foto della schermata principale del gestionale quando è aperto (e di tutte le schermate "amministrazione" se accessibili)
- [ ] Foto di uno scontrino emesso (per capire il layout, intestazione, reparti)

### 1.2 Identificazione modello/firmware cassa
- [ ] Trascrivere **modello esatto** della cassa Ditron dall'etichetta posteriore (es. `F5510`, `Ditronetwork`, `Glossy ITK`, ecc.)
- [ ] Trascrivere **numero di serie** (matricola fiscale) della cassa
- [ ] Se la cassa ha un menu di servizio accessibile da tastiera: navigare fino a "info versione" e annotare firmware version

### 1.3 Identificazione PC e OS
- [ ] Premere `Win + Pause` → screenshot/foto della schermata "Sistema"
  - Annotare: versione Windows, architettura (32/64 bit), RAM, nome computer, dominio/workgroup
- [ ] In un Prompt dei comandi (`cmd`):
  ```cmd
  systeminfo > C:\Users\Public\ricognizione\systeminfo.txt
  ipconfig /all > C:\Users\Public\ricognizione\ipconfig.txt
  ```
  > Creare prima la cartella: `mkdir C:\Users\Public\ricognizione`

### 1.4 Identificazione processo del gestionale
> Il gestionale deve essere **aperto e operativo** durante questa fase. Idealmente subito dopo l'emissione di uno scontrino reale dell'esercizio.

- [ ] Lanciare **Process Explorer** (`procexp.exe` dalla chiavetta, senza installare)
- [ ] Cercare nell'elenco processi quello del gestionale (il cassiere di solito sa il nome, oppure si riconosce dall'icona della barra applicazioni)
- [ ] Click destro sul processo → **Properties** → tab **Image**:
  - [ ] Annotare il **path completo** dell'eseguibile (es. `C:\Cassa\Gestionale.exe`)
  - [ ] Annotare la **Version** se presente
  - [ ] Annotare il **Command line** (potrebbe contenere parametri di connessione)
- [ ] Stesso processo → tab **TCP/IP**:
  - [ ] Annotare **tutte** le connessioni `ESTABLISHED`
  - [ ] Identificare quella verso la cassa: IP locale LAN, porta destinazione non standard
  - [ ] Screenshot
- [ ] Stesso processo → tab **Threads** e **Strings**: salvare uno screenshot di Strings (può rivelare nomi di funzioni, URL, riferimenti utili)

### 1.5 Conferma via netstat (ridondante ma utile come traccia testuale)
- [ ] In un Prompt dei comandi:
  ```cmd
  netstat -ano > C:\Users\Public\ricognizione\netstat_iniziale.txt
  ```
- [ ] Poi far emettere uno scontrino reale al cassiere
- [ ] Subito dopo:
  ```cmd
  netstat -ano > C:\Users\Public\ricognizione\netstat_dopo_scontrino.txt
  ```
- [ ] Confrontare i due file per identificare connessione attiva alla cassa

### 1.6 Identificazione natura tecnologica dell'eseguibile
- [ ] Lanciare **DIE (Detect It Easy)** dalla chiavetta
- [ ] Aprire l'eseguibile del gestionale (path raccolto al punto 1.4)
- [ ] Annotare:
  - [ ] Linguaggio rilevato: **.NET / VB6 / Delphi / C++/MFC / altro**
  - [ ] Architettura: **32-bit / 64-bit**
  - [ ] Compilatore / versione runtime
- [ ] Screenshot del risultato
- [ ] Ripetere per tutte le DLL principali nella cartella del gestionale (vedi 2.1)

> **Perché è importante:** se è .NET, possiamo decompilarlo con dnSpy e leggere il codice in chiaro — risparmiando settimane di reverse engineering. Se è VB6/Delphi/C++ nativo, dobbiamo affidarci principalmente allo sniff.

### 1.7 Cattura traffico — sessione "osservativa" (no emissioni di prova)
> A esercizio aperto, sniffiamo solo il traffico spontaneo. Le emissioni controllate le faremo nella sessione 2.

- [ ] Installare Wireshark (Next-Next-Next, **non installare USBPcap** se non serve, ma **installare Npcap** quando richiesto)
- [ ] Avviare Wireshark **come amministratore**
- [ ] Capture → Options → selezionare l'interfaccia di rete del PC (di solito "Ethernet")
- [ ] Nel campo **Capture filter** mettere:
  ```
  host <IP_CASSA>
  ```
  dove `<IP_CASSA>` è l'IP raccolto al punto 1.4 (es. `host 192.168.1.200`)
- [ ] Start
- [ ] Lasciare girare per **almeno 30 minuti durante un servizio reale**
- [ ] Stop → File → Save As → `cattura_01_servizio_normale.pcapng` sulla chiavetta

### 1.8 Intervista al cassiere / titolare
Domande da porre e annotare risposte:

- [ ] Da quanti anni usate questo gestionale?
- [ ] Chi l'ha sviluppato? C'è un contatto del programmatore originale?
- [ ] Esiste un manuale, anche cartaceo, anche in PDF?
- [ ] Esiste una licenza/dongle USB necessaria al funzionamento?
- [ ] Cosa succede se la cassa è spenta quando il gestionale è acceso? (per capire timeout/retry)
- [ ] Chi e quando fa la chiusura giornaliera (azzeramento)?
- [ ] Avete mai avuto interventi tecnici? Da chi? Per cosa?
- [ ] Ci sono operazioni "speciali" oltre alla vendita base? (storno, reso, fattura immediata, sconto cliente)
- [ ] Il display cliente è collegato al PC o alla cassa?
- [ ] Il cassetto contanti si apre come? (impulso dalla cassa? dal PC?)
- [ ] Avete altre stampanti termiche per scontrini non fiscali / cucina?
- [ ] Il gestionale ha una procedura di **backup**? Dove finisce?
- [ ] Si è mai bloccato il sistema? Cosa avete fatto per ripartire?

---

## 2. Sessione 2 — On-site a esercizio chiuso (3-4 ore)

> Obiettivo: backup integrale, raccolta artefatti, cattura traffico con scenari controllati. **A esercizio chiuso** perché alcune operazioni richiedono di emettere scontrini di test.
>
> **Pre-requisito imprescindibile:** autorizzazione scritta del titolare e accordo col commercialista sulla finestra in cui possiamo emettere scontrini di test (che andranno comunque all'AdE — si emetteranno scontrini di importo simbolico e si chiuderà giornaliera regolarmente).

### 2.1 Backup integrale della cartella del gestionale
- [ ] Aprire Esplora Risorse → cartella del gestionale (path raccolto al 1.4)
- [ ] Click destro sulla cartella **padre** → Proprietà → annotare dimensione totale
- [ ] Copiare l'intera cartella sulla chiavetta in `\backup_gestionale\<data>\` (esempio: `\backup_gestionale\2026-05-25\`)
- [ ] Verificare a copia conclusa che le dimensioni coincidano
- [ ] Annotare:
  - [ ] Tutti i file `.exe`, `.dll`, `.ocx` presenti
  - [ ] Tutti i file `.ini`, `.cfg`, `.xml`, `.json`, `.config` (sono i file di configurazione)
  - [ ] Tutti i file `.mdb`, `.accdb`, `.sqlite`, `.db`, `.dbf` (sono database locali)
  - [ ] Eventuali sottocartelle `logs/`, `data/`, `archivio/`

### 2.2 Raccolta file di configurazione "fuori cartella"
Alcuni gestionali tengono i config in posti standard di Windows. Verificare e copiare se presenti:

- [ ] `C:\ProgramData\<nome_gestionale>\` (l'intera cartella)
- [ ] `C:\Users\<utente_corrente>\AppData\Local\<nome_gestionale>\`
- [ ] `C:\Users\<utente_corrente>\AppData\Roaming\<nome_gestionale>\`
- [ ] `C:\Windows\<nome_gestionale>.ini` (vecchi gestionali VB6 mettono qui)

### 2.3 Export del registro Windows relativo al gestionale
- [ ] Apri **Regedit** come amministratore
- [ ] Cerca con `Ctrl+F` il nome del gestionale in:
  - [ ] `HKEY_LOCAL_MACHINE\SOFTWARE\`
  - [ ] `HKEY_LOCAL_MACHINE\SOFTWARE\WOW6432Node\` (se Windows 64-bit)
  - [ ] `HKEY_CURRENT_USER\SOFTWARE\`
- [ ] Per ogni chiave trovata: tasto destro → **Esporta** → salvare `.reg` sulla chiavetta

### 2.4 Identificazione database e schema
> Se al punto 2.1 sono stati trovati file `.mdb`/`.accdb`/`.sqlite`/`.db`:

- [ ] Aprire una **copia** del database (mai l'originale!) con uno strumento adatto:
  - `.mdb`/`.accdb` → MS Access oppure DBeaver
  - `.sqlite`/`.db` → DB Browser for SQLite
  - `.dbf` → DBF Viewer
- [ ] Annotare:
  - [ ] Nomi di tutte le tabelle
  - [ ] Struttura delle tabelle che sembrano riguardare: reparti, articoli, IVA, scontrini, operatori, clienti
  - [ ] Esportare in CSV le tabelle "anagrafica" (reparti, IVA, articoli)

> Se invece il gestionale parla con un SQL Server o MySQL esterno: cercare la stringa di connessione nei `.ini`/`.config` e annotare host/porta/db/utente.

### 2.5 Decompile dell'eseguibile (se .NET)
> Solo se al punto 1.6 è stato confermato che è .NET.

- [ ] Aprire **dnSpy** dalla chiavetta
- [ ] File → Open → eseguibile del gestionale (dalla **copia** in chiavetta, non dal sistema in uso)
- [ ] Esplorare l'albero a sinistra → cercare:
  - [ ] Classi/metodi con nomi come `Print`, `Receipt`, `Scontrino`, `Cash`, `Ecr`, `Fiscal`, `Send`, `TcpClient`, `Socket`
  - [ ] Costanti con stringhe tipo `\x02`, `STX`, `ETX`, opcode numerici
  - [ ] Riferimenti a `System.Net.Sockets.TcpClient` o `System.IO.Ports.SerialPort`
- [ ] Per ogni metodo interessante: tasto destro → **Save Code** → salvare il `.cs`/`.vb` sulla chiavetta in `\decompile\`
- [ ] Screenshot delle classi principali

> Se .NET ma offuscato: dnSpy mostrerà nomi tipo `a.b.c()`. In quel caso non disperare: anche offuscato si può seguire il flusso. Salvare comunque tutto.

### 2.6 Identificazione attività schedulate
> Per capire se l'azzeramento giornaliero è automatico o manuale.

- [ ] **Utilità di pianificazione** (`taskschd.msc`) → cercare task relativi al gestionale → screenshot di ogni task con tab "Generale", "Attivazione", "Azioni"
- [ ] **Servizi** (`services.msc`) → cercare servizi col nome del gestionale o "Ditron" o "ECR" → annotare nome, stato (avviato/fermo), tipo avvio
- [ ] Lanciare **Autoruns** dalla chiavetta → tab **Everything** → cercare voci col nome del gestionale → screenshot

### 2.7 Cattura traffico — scenari controllati
> Il momento clou. Si emettono scontrini di test reali per registrare il protocollo. Wireshark in cattura **prima** di ogni scenario, stop **dopo**, file salvato con nome parlante.

**Procedura per ogni scenario:**
1. Wireshark → Start con filtro `host <IP_CASSA>`
2. Eseguire l'operazione dal gestionale
3. Aspettare che la cassa risponda (lo scontrino esca o l'errore appaia)
4. Wireshark → Stop
5. File → Save As → nome del file come indicato sotto, sulla chiavetta in `\pcap\`

**Scenari da catturare (in quest'ordine):**

- [ ] **`01_avvio_gestionale.pcapng`**
  - Cassa accesa, gestionale chiuso
  - Avvio gestionale → cattura solo l'handshake iniziale (primi 30 secondi)

- [ ] **`02_scontrino_1_articolo.pcapng`**
  - 1 articolo, reparto base (es. "ALIMENTI 10%"), importo €1,00, pagamento contanti

- [ ] **`03_scontrino_2_articoli_stesso_reparto.pcapng`**
  - 2 articoli stesso reparto, importi diversi, pagamento contanti

- [ ] **`04_scontrino_2_reparti_diversi.pcapng`**
  - 1 articolo reparto A (es. 10%) + 1 articolo reparto B (es. 22%), pagamento contanti

- [ ] **`05_scontrino_con_sconto.pcapng`**
  - 1 articolo + sconto (percentuale o assoluto, a seconda di cosa supporta il gestionale)

- [ ] **`06_scontrino_pagamento_misto.pcapng`**
  - 2 articoli, pagamento parte contanti parte POS (o "non riscosso")

- [ ] **`07_scontrino_annullo.pcapng`**
  - Iniziare uno scontrino e annullarlo prima di chiuderlo

- [ ] **`08_storno_riga.pcapng`**
  - Iniziare uno scontrino, aggiungere 2 righe, stornare la prima, chiudere

- [ ] **`09_chiusura_giornaliera.pcapng`**
  - L'azzeramento fiscale di fine giornata (chiusura "Z")
  - **Importante:** questa è l'operazione che archivia gli scontrini di test all'AdE

- [ ] **`10_lettura_xn.pcapng`** (se supportato)
  - La lettura giornaliera "X" (non azzera, solo legge totali)

- [ ] **`11_stato_cassa_idle.pcapng`**
  - Cassa accesa, gestionale aperto, nessuno scontrino in corso, cattura 60 secondi
  - Serve a vedere il "heartbeat"/polling che il gestionale fa sulla cassa

- [ ] **`12_riconnessione.pcapng`** *(opzionale, solo se possibile farlo senza rischi)*
  - Staccare cavo Ethernet dalla cassa per 10 secondi → ricollegare → vedere come il gestionale tenta la riconnessione
  - **Fare solo se la cassa è in IDLE e non c'è uno scontrino in corso**

**Per ogni scontrino di test annotare a parte (file `scenari.txt` sulla chiavetta):**
- Numero progressivo scontrino mostrato sul display cassa
- Importo totale
- Eventuale errore o messaggio
- Ora esatta di emissione (per ritrovare nei pcap)

### 2.8 Log applicativi
- [ ] Cercare nella cartella del gestionale file `.log`/`.txt` di log → copiare gli ultimi 30 giorni
- [ ] Event Viewer di Windows → Applicazioni → filtrare per origine = nome gestionale → esportare gli ultimi 30 giorni come `.evtx`

### 2.9 Snapshot finale del sistema (sicurezza)
> Se possibile, prima di concludere:

- [ ] Creare un'immagine del disco del PC con uno strumento tipo **Macrium Reflect Free** o **Clonezilla Live** salvandola su HD esterno
- [ ] In alternativa minima: backup di `C:\Users\<utente>\` e di tutte le cartelle del gestionale

---

## 3. Cosa portare via dal sito

Al termine, sulla chiavetta/HD esterno devono esserci:

- [ ] `\backup_gestionale\<data>\` — copia integrale cartella del gestionale
- [ ] `\backup_appdata\` — config da ProgramData, AppData, ecc.
- [ ] `\registro\*.reg` — esportazioni del registro
- [ ] `\database\` — copie dei db locali (se presenti)
- [ ] `\decompile\` — sorgenti decompilati (se .NET)
- [ ] `\pcap\*.pcapng` — i 11-12 file di cattura traffico
- [ ] `\ricognizione\` — output di systeminfo, ipconfig, netstat, screenshot
- [ ] `\foto\` — foto del setup fisico (PC, cassa, cavi, scontrini)
- [ ] `\log\` — log applicativi e Event Viewer
- [ ] `\appunti.md` — risposte all'intervista, modello cassa, IP/porte, mappa reparti/IVA, scenari emessi

---

## 4. Analisi off-site (fase di laboratorio)

> Da fare in ufficio, sui dati raccolti. Non richiede più accesso al PC del gestionale, salvo necessità di chiarimenti.

### 4.1 Analisi statica
- [ ] Aprire i .pcap in Wireshark
- [ ] Per ogni connessione TCP verso la cassa: tasto destro → **Follow → TCP Stream**
- [ ] Visualizzare in modalità **Hex Dump**
- [ ] Identificare il framing:
  - byte di inizio (es. `0x02` STX)
  - byte di fine (es. `0x03` ETX)
  - checksum (LRC, CRC16) tipicamente l'ultimo byte/parola prima del fine
  - eventuale sequence number
- [ ] Confrontare scenario `02_scontrino_1_articolo` con `03_scontrino_2_articoli_stesso_reparto`: la differenza è una singola riga "vendi articolo" → si isola subito il comando `VEND` (opcode 10 dall'ini)
- [ ] Confrontare con `ecrcomrt.ini` presente in `DitronRT/ECR/` per mappare opcode → nome comando

### 4.2 Analisi del decompile (se .NET)
- [ ] Aprire i `.cs`/`.vb` salvati con un editor decente (VS Code)
- [ ] Cercare la classe che fa `TcpClient.Connect(...)` o equivalente
- [ ] Cercare i metodi che chiama dopo: probabilmente `SendCommand`, `BuildFrame`, `CalcChecksum`
- [ ] Riscrivere in pseudo-codice / Python l'algoritmo di framing

### 4.3 Prototipo Python
- [ ] Scrivere `ditron_client.py` minimale:
  ```python
  import socket
  s = socket.socket()
  s.connect(("192.168.1.200", PORTA))
  s.send(<frame_di_apertura>)
  print(s.recv(1024).hex())
  ```
- [ ] Far emettere uno scontrino di test dal nostro script (a esercizio chiuso, autorizzato)
- [ ] Se funziona: si passa alla fase di integrazione in Carlo V

---

## 5. Cosa NON fare assolutamente

- ❌ **Non emettere scontrini** durante il servizio per fare test
- ❌ **Non disinstallare** il gestionale né rimuovere file
- ❌ **Non modificare** file di configurazione del gestionale
- ❌ **Non aggiornare** Windows / driver / antivirus durante la ricognizione
- ❌ **Non staccare la cassa** dalla corrente
- ❌ **Non chiudere** il processo del gestionale con Task Manager se non strettamente necessario, e mai mentre c'è uno scontrino in corso
- ❌ **Non condividere** i file raccolti (contengono dati fiscali/IVA del cliente) al di fuori del team Carlo V
- ❌ **Non emettere scontrini di test** senza accordo preventivo con il commercialista del cliente

---

## 6. Cosa fare se qualcosa va storto

| Sintomo | Cosa fare |
|---------|-----------|
| Il gestionale si blocca durante uno scontrino | Non chiuderlo. Chiamare il cassiere. Aspettare timeout naturale (di solito 30-60s). Se proprio non risponde dopo 5 minuti: tasto destro su gestionale in barra applicazioni → Chiudi (NON Process Explorer → Kill) |
| La cassa va in errore | Annotare codice errore visualizzato sul display. Spegnere e riaccendere la cassa **solo** se autorizzati dal titolare |
| Wireshark non vede traffico | Verificare di aver scelto l'interfaccia giusta (probabilmente "Ethernet" non "Wi-Fi"). Verificare che il filtro IP cassa sia corretto |
| Process Explorer non si avvia | Verificare di averlo lanciato come amministratore. Se Windows lo blocca per SmartScreen: tasto destro → Proprietà → Sblocca |
| Backup chiavetta troppo lento | Continuare in background, nel mentre lavorare sui pcap. La cartella gestionale tipica è 50-500 MB, si fa in pochi minuti |
| Il PC del gestionale non ha porte USB libere | Usare lo USB hub di scorta che ci si è portati, o spegnere il PC, sostituire un cavo, riaccendere (ma solo con autorizzazione titolare) |

---

## 7. Output di questo documento

Al termine della ricognizione si deve essere in grado di rispondere con certezza a queste domande:

1. ✅ Modello esatto cassa Ditron, firmware, IP, porta TCP
2. ✅ Linguaggio e architettura del gestionale (.NET? VB6? Delphi?)
3. ✅ È decompilabile? (sì se .NET non offuscato)
4. ✅ Quali reparti, IVA, anagrafica articoli sono usati?
5. ✅ Come avviene l'azzeramento giornaliero?
6. ✅ Quali sono i 7 scenari operativi base e come appaiono in protocollo?
7. ✅ Esiste un dongle / licenza hardware da rispettare?
8. ✅ Quanto è critico il display cliente / cassetto contanti / altre periferiche?

**Solo dopo aver risposto a tutto questo si può iniziare a scrivere il client TCP per Carlo V.**

---

## Appendice A — Comandi rapidi da Prompt cmd

```cmd
:: Crea cartella di output
mkdir C:\Users\Public\ricognizione

:: Info sistema
systeminfo > C:\Users\Public\ricognizione\systeminfo.txt
ipconfig /all > C:\Users\Public\ricognizione\ipconfig.txt

:: Connessioni attive
netstat -ano > C:\Users\Public\ricognizione\netstat.txt

:: Lista processi
tasklist /v > C:\Users\Public\ricognizione\tasklist.txt

:: Servizi
sc query state= all > C:\Users\Public\ricognizione\services.txt

:: Routing
route print > C:\Users\Public\ricognizione\route.txt

:: ARP (per vedere chi sta in LAN)
arp -a > C:\Users\Public\ricognizione\arp.txt
```

## Appendice B — Filtri Wireshark utili

```
host 192.168.1.200                     # tutto da/verso la cassa
tcp.port == 9100                       # filtra per porta cassa
tcp.stream eq 0                        # solo lo stream zero
tcp.flags.syn == 1 and tcp.flags.ack == 0  # solo le aperture connessione
tcp.payload                            # solo i pacchetti con dati (no ACK puri)
```

## Appendice C — Estratto opcode rilevanti da `ecrcomrt.ini`

Per riferimento durante l'analisi pcap:

| Opcode | Nome | Significato |
|--------|------|-------------|
| 1 | PROG | Inizio programmazione |
| 2 | FINEPROG | Fine programmazione |
| 3 | LEGGI | Lettura file da cassa |
| 4 | IDLE | Riporta cassa in IDLE |
| 8 | CANC | Cancella (clear) |
| 9 | INP | Input generico |
| **10** | **VEND** | **Vendita articolo/reparto** |
| **11** | **CHIU** | **Chiusura scontrino** |
| 12 | SCONTO | Sconto assoluto |
| 14 | VIS | Visualizza |
| 15 | CHIAVE | Imposta assetto cassa |
| 16/17 | PERCA/PERCB | Sconto/maggiorazione percentuale |
| 18 | SUBT | Subtotale |
| 22 | NOFIS | Scontrino non fiscale |
| 23 | FATTURA | Inizia fattura |
| 24 | NOTACRED | Nota credito |
| 25 | TAVOLO | Gestione tavolo |
| 26 | REPORT | Report |
| **27** | **AZZGIO** | **Azzeramento giornaliero (chiusura fiscale)** |
| 49 | GETP | Get proprietà |
| 48 | SETP | Set proprietà |
| 53 | POPER | Programma operatori |
| 61 | TOTG | Totali giornalieri (output) |
| 62 | TOTP | Totali periodici (output) |

Lista completa nel file `DitronRT/ECR/ecrcomrt.ini`.

---

*Documento generato per il progetto Carlo V — versione 1.0 — 2026-05-25*
