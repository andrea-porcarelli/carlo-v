# DitronAgent

Mini servizio HTTP che fa da ponte tra Carlo V (Laravel) e WinEcrCom (driver Ditron RT) sul PC del ristorante.

## Architettura

```
Carlo V (Laravel)  ──HTTP POST /emit-receipt──▶  DitronAgent  ──file scontrinoNN.txt──▶  WinEcrCom Spooler  ──TCP──▶  Cassa Ditron RT
                                                       ▲                                              │
                                                       └────────── leggi scontrinoNN.err ◀────────────┘
```

## Modalità

`DitronAgent:Mode` in `appsettings.json`:
- `NonFiscal` (default sviluppo) — emette comandi `nofis apri/riga/chiudi`. Non emette scontrino fiscale, non comunica con AdE. Usare in dev/staging.
- `Fiscal` — emette `prmsg/vend/chius`. Emette scontrino fiscale reale che va all'AdE. Usare solo in produzione, dopo validazione.

## Endpoint

- `GET /health` — stato agent + counter + folder
- `POST /close-day` — chiusura giornaliera fiscale (Z-report). Corpo JSON:
  ```json
  { "idempotency_key": "close_day:2026-07-11", "tipo": 2 }
  ```
  `tipo` opzionale (1=lungo, 2=breve, 3=medio; default 2). In `Fiscal` emette `azzgio tipo=N`; in `NonFiscal` emette una sequenza `nofis` di simulazione. Risposta: `{ "ok": true, "receipt_number": 201, "elapsed_ms": 1842, "raw_command": "...", "raw_err": "", "mode": "Fiscal" }`.
- `POST /read-x` — Lettura X giornaliera (X-Report, non fiscale, non azzera i contatori). Corpo JSON:
  ```json
  { "idempotency_key": "read_x:20260714_183045_abc123" }
  ```
  In `Fiscal` emette `report num=2 modo=0` (opcode 26, modo X = non azzera contatori); in `NonFiscal` una simulazione `nofis`. Risposta simile a `/close-day`. **Nota:** `azzgio` (opcode 27) è sempre una Z fiscale con azzeramento — non usarlo per la lettura X, anche con `tipo=1`.
- `POST /emit-receipt` — corpo JSON:
  ```json
  {
    "idempotency_key": "table_order:1234",
    "table_number": 52,
    "covers": 2,
    "cover_charge_unit_price": 2.00,
    "items": [
      {"description": "CAPONATA", "unit_price": 12.00, "quantity": 1},
      {"description": "SPRITZ", "unit_price": 7.00, "quantity": 2}
    ],
    "tender": 5,
    "reparto": 1
  }
  ```
  Risposta: `{ "ok": true, "receipt_number": 201, "elapsed_ms": 1842, "raw_command": "...", "raw_err": "" }`

Se `DitronAgent:AuthToken` è impostato, ogni richiesta richiede `Authorization: Bearer <token>`.

## Build (cross-compile da Linux con Docker)

```bash
cd ditron/agent
docker compose run --rm build
# binario in ./artifact/DitronAgent.exe
```

## Run in dev (su Linux, modalità simulata)

```bash
cd ditron/agent
docker compose up dev
# l'agent ascolta su localhost:9090, scrive in /tmp/ditron-dev/
```

Test:
```bash
curl http://localhost:9090/health
curl -X POST http://localhost:9090/emit-receipt \
  -H 'Content-Type: application/json' \
  -d '{"idempotency_key":"test:1","table_number":42,"covers":2,"cover_charge_unit_price":2.00,"items":[{"description":"PIZZA MARGHERITA","unit_price":8.50,"quantity":1}]}'
```

(in modalità NonFiscal su Linux il `.err` non sarà mai prodotto da nessuno → l'agent andrà in timeout. È atteso: il polling `.err` è la responsabilità di WinEcrCom Spooler, che gira solo su Windows. Per smoke-test della logica usare unit test, oppure simulare il `.err` con un altro container che lo crea vuoto.)

## Deploy su PC Windows ristorante

1. Copia `artifact/DitronAgent.exe` su `C:\Program Files\DitronAgent\`
2. Copia `appsettings.json` accanto e adatta i path (devono essere quelli reali Windows)
3. Registra come servizio:
   ```cmd
   sc create DitronAgent binPath= "C:\Program Files\DitronAgent\DitronAgent.exe"
   sc start DitronAgent
   ```
4. Verifica: `curl http://localhost:9090/health`

## Counter range

`CounterStart=200` per default. Razionale: RistoQuick durante la coesistenza usa `scontrino01..99.txt`, quindi Carlo V parte da 200 per non collidere. Una volta dismesso RistoQuick si può ripartire da 1.

## Sicurezza

- Espone solo in LAN ristorante (verifica firewall Windows blocchi 9090 da WAN).
- Imposta `AuthToken` non vuoto in produzione: il client Carlo V deve inviarlo come Bearer.
- Lo SMB share opzionale (V1.5) andrà su \\PC\dropbox con ACL solo lettura per Everyone e scrittura solo per l'utente di servizio Carlo V.
