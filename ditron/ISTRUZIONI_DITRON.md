## Avvio DitronAgent.exe da PowerShell

Tutti i comandi vanno eseguiti in **Windows PowerShell aperto come Amministratore**
(Start → digita "PowerShell" → tasto destro → *Esegui come amministratore*).

---

### A — Avvio manuale (debug / test)

Usalo quando vuoi vedere i log a schermo e tenere l'agent attaccato a una finestra.
Il processo muore quando chiudi PowerShell.

```powershell
cd 'C:\Program Files\DitronAgent'
.\DitronAgent.exe
```

Verifica rapida da un'altra finestra PowerShell:

```powershell
Invoke-RestMethod http://localhost:9090/health
```

Per fermarlo: `Ctrl+C` nella finestra dell'agent.

> Se hai già avviato il `.exe` "come amministratore" con doppio clic, **chiudilo prima** di
> proseguire alla sezione B — Windows non lascia girare due istanze sulla stessa porta.

---

### B — Promozione a servizio Windows (produzione)

Così l'agent parte da solo al boot, si riavvia se crasha e non dipende dalla sessione utente.

#### B.1 — Crea il servizio

```powershell
New-Service `
    -Name        'DitronAgent' `
    -BinaryPathName '"C:\Program Files\DitronAgent\DitronAgent.exe"' `
    -DisplayName 'Ditron Agent (Carlo V)' `
    -Description 'Ponte HTTP tra Carlo V e WinEcrCom per cassa Ditron RT.' `
    -StartupType Automatic
```

Le doppie virgolette annidate `'"..."'` servono perché il path contiene spazi.

#### B.2 — Avvia il servizio

```powershell
Start-Service -Name DitronAgent
Get-Service  -Name DitronAgent     # deve mostrare Status: Running
```

#### B.3 — Verifica HTTP

```powershell
Invoke-RestMethod http://localhost:9090/health
```

Se torna il JSON di health l'agent è up. Gira come `LocalSystem` (default di
`New-Service`), quindi ha pieno accesso a `C:\ProgramData\DitronAgent\counter.txt`.

#### B.4 — Comandi di gestione

```powershell
Stop-Service   -Name DitronAgent          # ferma
Restart-Service -Name DitronAgent         # riavvia
Get-Service    -Name DitronAgent          # stato
Remove-Service -Name DitronAgent          # disinstalla (Windows 10+/Server 2019+)
# In alternativa: sc.exe delete DitronAgent
```

#### B.5 — Auto-restart in caso di crash (opzionale ma consigliato)

`New-Service` non espone le recovery actions. Vanno settate con `sc.exe`:

```powershell
sc.exe failure DitronAgent reset= 86400 actions= restart/5000/restart/5000/restart/10000
```

Significa: dopo qualunque crash riavvia dopo 5 s, poi ancora 5 s, poi 10 s; reset
del contatore fallimenti dopo 24 h.

---

### Note operative

- I log dell'agent finiscono nel **Visualizzatore eventi** → *Registri di Windows
  → Applicazione*, filtro per origine `DitronAgent`. Meno comodo della console
  ma è quello che vuoi in produzione.
- Per il debug live torna alla sezione A (avvio manuale).
- Il servizio si avvia automaticamente al boot (`-StartupType Automatic`).
- Firewall: assicurati che la porta `9090` sia raggiungibile dal container Docker
  di Carlo V. Se serve, regola di inbound:

  ```powershell
  New-NetFirewallRule -DisplayName 'DitronAgent HTTP' -Direction Inbound `
      -Protocol TCP -LocalPort 9090 -Action Allow
  ```
