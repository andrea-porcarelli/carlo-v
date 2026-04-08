<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }

        .header { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .party { width: 48%; }
        .party-label { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #888; margin-bottom: 4px; letter-spacing: 1px; }
        .party-name { font-size: 13px; font-weight: bold; color: #111; margin-bottom: 2px; }
        .party-detail { color: #555; line-height: 1.5; }

        .invoice-meta { background: #f5f5f5; border-left: 4px solid #2563eb; padding: 10px 14px; margin-bottom: 24px; display: flex; gap: 40px; }
        .meta-item { }
        .meta-label { font-size: 8px; text-transform: uppercase; color: #888; letter-spacing: 1px; }
        .meta-value { font-size: 13px; font-weight: bold; color: #111; margin-top: 2px; }

        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.lines thead tr { background: #2563eb; color: #fff; }
        table.lines thead th { padding: 7px 8px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.lines thead th.right { text-align: right; }
        table.lines tbody tr:nth-child(even) { background: #f9f9f9; }
        table.lines tbody td { padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        table.lines tbody td.right { text-align: right; }
        table.lines tbody td.center { text-align: center; }

        .totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 20px; }
        table.totals { width: 280px; border-collapse: collapse; }
        table.totals td { padding: 5px 10px; font-size: 10px; }
        table.totals td:last-child { text-align: right; font-weight: bold; }
        table.totals tr.grand-total td { font-size: 13px; font-weight: bold; border-top: 2px solid #2563eb; padding-top: 8px; color: #2563eb; }

        .payment { margin-top: 16px; font-size: 9px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
        .payment strong { color: #333; }

        .footer { margin-top: 30px; font-size: 8px; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
    </style>
</head>
<body>

    {{-- Intestazione: fornitore vs cliente --}}
    <div class="header">
        <div class="party">
            <div class="party-label">Cedente / Prestatore</div>
            <div class="party-name">{{ $cedente['nome'] }}</div>
            <div class="party-detail">
                P.IVA: {{ $cedente['piva'] }}<br>
                {{ $cedente['indirizzo'] }}<br>
                {{ $cedente['cap'] }} {{ $cedente['comune'] }} ({{ $cedente['provincia'] }})
            </div>
        </div>
        <div class="party" style="text-align:right;">
            <div class="party-label">Cessionario / Committente</div>
            <div class="party-name">{{ $committente['nome'] }}</div>
            <div class="party-detail">
                P.IVA: {{ $committente['piva'] }}<br>
                {{ $committente['indirizzo'] }}<br>
                {{ $committente['cap'] }} {{ $committente['comune'] }} ({{ $committente['provincia'] }})
            </div>
        </div>
    </div>

    {{-- Dati documento --}}
    <div class="invoice-meta">
        <div class="meta-item">
            <div class="meta-label">Numero Fattura</div>
            <div class="meta-value">{{ $documento['numero'] }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Data</div>
            <div class="meta-value">{{ \Carbon\Carbon::parse($documento['data'])->format('d/m/Y') }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Tipo</div>
            <div class="meta-value">{{ $documento['tipo'] }}</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Valuta</div>
            <div class="meta-value">{{ $documento['divisa'] }}</div>
        </div>
    </div>

    {{-- Righe prodotto --}}
    <table class="lines">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:42%">Descrizione</th>
                <th class="right" style="width:10%">Qtà</th>
                <th style="width:6%">U.M.</th>
                <th class="right" style="width:12%">Prezzo Unit.</th>
                <th class="right" style="width:13%">Totale</th>
                <th class="right" style="width:8%">IVA %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($linee as $linea)
            <tr>
                <td class="center">{{ $linea['numero'] }}</td>
                <td>{{ $linea['descrizione'] }}</td>
                <td class="right">{{ number_format($linea['quantita'], 2, ',', '.') }}</td>
                <td>{{ $linea['unita'] }}</td>
                <td class="right">€ {{ number_format($linea['prezzo_unitario'], 4, ',', '.') }}</td>
                <td class="right">€ {{ number_format($linea['prezzo_totale'], 2, ',', '.') }}</td>
                <td class="right">{{ number_format($linea['iva'], 0) }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Riepilogo IVA + Totali --}}
    <div class="totals-wrap">
        <table class="totals">
            @foreach($riepilogo as $r)
            <tr>
                <td>Imponibile {{ number_format($r['aliquota'], 0) }}%</td>
                <td>€ {{ number_format($r['imponibile'], 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>IVA {{ number_format($r['aliquota'], 0) }}%</td>
                <td>€ {{ number_format($r['imposta'], 2, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="grand-total">
                <td>Totale Documento</td>
                <td>€ {{ number_format($documento['totale'], 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    {{-- Pagamento --}}
    @if($pagamento)
    <div class="payment">
        <strong>Pagamento:</strong>
        {{ $pagamento['modalita'] }}
        @if($pagamento['scadenza'])
            — Scadenza: {{ \Carbon\Carbon::parse($pagamento['scadenza'])->format('d/m/Y') }}
        @endif
        @if($pagamento['importo'])
            — Importo: € {{ number_format($pagamento['importo'], 2, ',', '.') }}
        @endif
    </div>
    @endif

    <div class="footer">
        Documento generato da sistema gestionale &mdash; Fattura elettronica {{ $invoice->filename }}
    </div>

</body>
</html>
