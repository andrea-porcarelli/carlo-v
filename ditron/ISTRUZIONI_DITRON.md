B.3 — Crea il servizio Windows

Sempre in cmd come amministratore, una riga sola. Attenzione agli spazi: binPath= "..." ha uno spazio dopo = obbligatorio (è una stranezza di sc):

sc create DitronAgent binPath= "\"C:\Program Files\DitronAgent\DitronAgent.exe\"" start= auto DisplayName= "Ditron Agent (Carlo V)"

Le doppie virgolette annidate (\"…\") servono perché il path contiene spazi.

Risposta attesa:                                                                                                                                                                                                                  
[SC] CreateService SUCCESS

B.4 — Imposta descrizione (cosmetica, utile in services.msc)

sc description DitronAgent "Ponte HTTP tra Carlo V e WinEcrCom per cassa Ditron RT."

B.5 — Avvia il servizio

sc start DitronAgent

Risposta attesa:
SERVICE_NAME: DitronAgent                                                                                                                                                                                                         
STATE              : 2  START_PENDING                                                                                                                                                                                   
... poi STATE: 4 RUNNING

B.6 — Verifica

curl http://localhost:9090/health

Se torna il JSON di prima, il servizio è up. Importante: ora gira come LocalSystem (default di sc create), che ha tutti i permessi — quindi niente più problema su C:\ProgramData\DitronAgent\counter.txt.

Note su log e gestione

- I log dell'agent (che prima vedevi nella console) ora vanno nel Visualizzatore eventi Windows → Registri di Windows → Applicazione, filtro per origine DitronAgent. Per il debug è meno comodo della console, ma in produzione è
  quello che vuoi.
- Per fermare/riavviare il servizio: sc stop DitronAgent, sc start DitronAgent.
- Per disinstallare: sc delete DitronAgent.
- Si avvia automaticamente al boot del PC (start= auto).

Conferma quando arrivi al passo B.6 con JSON OK, poi passiamo ad A.       
