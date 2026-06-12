# Ricognizione rapida — Da fare in ristorante

Versione sintetica. Tempo previsto: **1-2 ore**. Tutto in **sola lettura**, niente modifiche.

---

## Cosa portarsi
- [ ] Chiavetta USB (almeno 32 GB)
- [ ] Smartphone (foto)
- [ ] Sulla chiavetta, già scaricati:
  - [ ] **Wireshark** (installer offline) — https://www.wireshark.org/download.html
  - [ ] **Process Explorer** (`procexp.exe`) — https://learn.microsoft.com/sysinternals/downloads/process-explorer

---

## 1. Foto del setup (5 minuti)
- [ ] PC del gestionale: davanti, dietro, tutte le porte coi cavi
- [ ] Cassa Ditron: etichetta posteriore (modello + matricola)
- [ ] Dove va il cavo Ethernet della cassa (switch? router? PC?)
- [ ] Uno scontrino emesso oggi

---

## 2. Identificare la cassa (5 minuti)
Scrivere su un foglio:
- [ ] **Modello cassa Ditron** (es. F5510, Ditronetwork, Glossy ITK)
- [ ] **Numero di matricola** (etichetta posteriore)

---

## 3. Identificare l'eseguibile del gestionale (10 minuti)

Sul PC del gestionale, con il gestionale **aperto e in uso normale**:

- [ ] Lanciare **Process Explorer** dalla chiavetta (tasto destro → "Esegui come amministratore")
- [ ] Trovare nell'elenco il processo del gestionale (chiedere al cassiere se non si riconosce)
- [ ] Tasto destro → **Properties**
- [ ] Tab **Image**:
  - [ ] Foto/screenshot
  - [ ] Annotare il **Path** completo dell'eseguibile (es. `C:\Cassa\Gestionale.exe`)
- [ ] Tab **TCP/IP**:
  - [ ] Foto/screenshot
  - [ ] Annotare **IP e porta della cassa** (la connessione `ESTABLISHED` con un IP locale tipo 192.168.x.x)

---

## 4. Conferma da prompt comandi (5 minuti)

Aprire **Prompt dei comandi** (Win + R → `cmd`):

```cmd
mkdir C:\Users\Public\ricognizione
ipconfig /all > C:\Users\Public\ricognizione\ipconfig.txt
netstat -ano > C:\Users\Public\ricognizione\netstat.txt
systeminfo > C:\Users\Public\ricognizione\systeminfo.txt
```

- [ ] A fine emissione di uno scontrino reale del servizio, rilanciare:
  ```cmd
  netstat -ano > C:\Users\Public\ricognizione\netstat_dopo_scontrino.txt
  ```
- [ ] Copiare l'intera cartella `C:\Users\Public\ricognizione` sulla chiavetta

---

## 5. Copia della cartella del gestionale (15-30 minuti)

Dalla cartella raccolta al punto 3 (`Path` dell'eseguibile):
- [ ] Aprire la **cartella padre** in Esplora Risorse
- [ ] Copiarla **tutta** sulla chiavetta in `\backup_gestionale\`
- [ ] A copia conclusa: verificare che le dimensioni coincidano

---

## 6. Sniff del traffico — 3 scontrini reali (15 minuti)

> Si registra il **traffico spontaneo** durante il servizio normale. Niente scontrini "finti".

- [ ] Installare **Wireshark** (Next-Next, accettare Npcap quando richiesto)
- [ ] Avviare Wireshark **come amministratore**
- [ ] Capture → Options → scegliere l'interfaccia **Ethernet**
- [ ] Nel campo **Capture filter** in basso: `host <IP_CASSA>` (l'IP raccolto al punto 3)
- [ ] **Start**
- [ ] Lasciare girare **per almeno 3 scontrini reali** del servizio (10-15 minuti vanno bene)
- [ ] **Stop**
- [ ] File → Save As → `cattura_servizio.pcapng` sulla chiavetta

---

## 7. 4 domande al cassiere / titolare (10 minuti)

Annotare le risposte su un foglio:

1. Chi ha sviluppato questo gestionale? C'è un contatto?
2. Esiste un manuale, anche cartaceo o PDF?
3. C'è una chiavetta USB (dongle) o licenza che deve restare collegata?
4. Chi e quando fa la **chiusura giornaliera** (azzeramento "Z")?

---

## Cosa porto via sulla chiavetta

- [ ] `\foto\` — foto del setup
- [ ] `\backup_gestionale\` — copia integrale cartella gestionale
- [ ] `\ricognizione\` — output di ipconfig, netstat, systeminfo
- [ ] `\cattura_servizio.pcapng` — traffico sniffato
- [ ] Foglio con: modello cassa, matricola, IP+porta cassa, path eseguibile, risposte alle 4 domande

---

## Cosa NON fare
- ❌ Non chiudere/riavviare il gestionale
- ❌ Non spegnere la cassa
- ❌ Non emettere scontrini di prova (gli scontrini vanno all'AdE)
- ❌ Non modificare nessun file, nessuna configurazione
- ❌ Non installare aggiornamenti Windows / antivirus

## Se qualcosa va storto
**Non si tocca, si chiama indietro.**



Path: C:\RistoQuick\RistoQuick.exe
Command line: "C:\RistoQuick\RistoQuick.exe" 
Current directory: C:\Windows\SysWOW64\
Version: 1.0.0.0
Comppany: RistoQuick
