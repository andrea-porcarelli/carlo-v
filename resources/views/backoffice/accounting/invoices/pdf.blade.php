<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 36px 42px 44px 42px; }
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 9.5px;
        color: #1f2937;
        background: #fff;
        line-height: 1.5;
    }

    .serif { font-family: "DejaVu Serif", "Times New Roman", serif; }

    /* ── HEADER ──────────────────────────────────────────────── */
    .doc-header {
        margin-bottom: 26px;
        padding-bottom: 14px;
        border-bottom: 2px solid #0b1d3d;
    }
    .doc-header table { width: 100%; border-collapse: collapse; }
    .doc-header td { vertical-align: top; }

    .brand-eyebrow {
        font-size: 7.5px;
        letter-spacing: 2.4px;
        text-transform: uppercase;
        color: #8a96a8;
        margin-bottom: 4px;
    }
    .brand-title {
        font-size: 28px;
        font-weight: bold;
        letter-spacing: 4px;
        text-transform: uppercase;
        color: #0b1d3d;
        line-height: 1;
    }
    .brand-subtitle {
        font-size: 8px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #8a96a8;
        margin-top: 6px;
    }

    .doc-ident { text-align: right; }
    .doc-ident .num-label {
        font-size: 7.5px;
        letter-spacing: 1.8px;
        text-transform: uppercase;
        color: #8a96a8;
    }
    .doc-ident .num-value {
        font-size: 16px;
        font-weight: bold;
        color: #0b1d3d;
        margin-top: 2px;
        letter-spacing: 0.5px;
    }
    .doc-ident .num-date {
        font-size: 9px;
        color: #4a5568;
        margin-top: 6px;
    }
    .doc-ident .num-date strong { color: #0b1d3d; }

    /* ── PARTIES ─────────────────────────────────────────────── */
    .parties { width: 100%; border-collapse: collapse; margin-bottom: 26px; }
    .parties td {
        width: 50%;
        vertical-align: top;
        padding: 0;
    }
    .parties td.right-cell { padding-left: 22px; }

    .party-eyebrow {
        font-size: 7.5px;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #8a96a8;
        padding-bottom: 5px;
        border-bottom: 1px solid #cdd5e0;
        margin-bottom: 9px;
    }
    .party-name {
        font-size: 12.5px;
        font-weight: bold;
        color: #0b1d3d;
        margin-bottom: 6px;
    }
    .party-line {
        font-size: 9px;
        color: #2d3748;
        margin-bottom: 1px;
    }
    .party-meta {
        font-size: 8.5px;
        color: #4a5568;
        margin-top: 6px;
    }
    .party-meta .k {
        display: inline-block;
        min-width: 32px;
        color: #8a96a8;
        font-size: 7.5px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    /* ── DOC META ROW ────────────────────────────────────────── */
    .doc-meta { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
    .doc-meta td {
        padding: 9px 14px 9px 0;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }
    .doc-meta .k {
        display: block;
        font-size: 7px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #8a96a8;
        margin-bottom: 2px;
    }
    .doc-meta .v {
        font-size: 10px;
        font-weight: bold;
        color: #0b1d3d;
    }

    /* ── LINES TABLE ─────────────────────────────────────────── */
    .section-eyebrow {
        font-size: 7.5px;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #8a96a8;
        margin-bottom: 8px;
    }

    table.lines {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 22px;
        font-size: 9px;
    }
    table.lines thead th {
        padding: 9px 8px 8px;
        text-align: left;
        font-size: 7.5px;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: bold;
        color: #0b1d3d;
        border-top: 2px solid #0b1d3d;
        border-bottom: 1px solid #0b1d3d;
        background: #fafbfd;
    }
    table.lines thead th.right  { text-align: right; }
    table.lines thead th.center { text-align: center; }

    table.lines tbody td {
        padding: 9px 8px;
        border-bottom: 1px solid #eaeef4;
        vertical-align: top;
        color: #2d3748;
    }
    table.lines tbody td.right  { text-align: right; white-space: nowrap; }
    table.lines tbody td.center { text-align: center; color: #8a96a8; font-size: 8.5px; }
    table.lines tbody td.desc   { color: #0b1d3d; font-weight: 600; }
    table.lines tbody td.um     { color: #8a96a8; font-size: 8.5px; text-align: center; }
    table.lines tbody td.iva    { text-align: right; color: #4a5568; white-space: nowrap; }
    table.lines tbody td.num    { font-variant-numeric: tabular-nums; }

    table.lines tfoot td {
        padding: 9px 8px;
        font-size: 8.5px;
        color: #4a5568;
        border-top: 1px solid #cdd5e0;
    }
    table.lines tfoot td.right  { text-align: right; white-space: nowrap; }
    table.lines tfoot td.label  {
        text-align: right;
        font-size: 7.5px;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: #8a96a8;
    }

    /* ── BOTTOM (PAGAMENTO + TOTALI) ─────────────────────────── */
    .bottom { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .bottom td.pay { width: 54%; vertical-align: top; padding-right: 24px; }
    .bottom td.tot { width: 46%; vertical-align: top; }

    .pay-block { font-size: 9px; }
    .pay-block .row { margin-bottom: 7px; }
    .pay-block .k {
        display: block;
        font-size: 7px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #8a96a8;
        margin-bottom: 1px;
    }
    .pay-block .v { font-weight: bold; color: #0b1d3d; font-size: 10px; }

    table.totals {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
    }
    table.totals td { padding: 8px 12px; color: #4a5568; }
    table.totals td.k {
        text-align: left;
        font-size: 8.5px;
        color: #4a5568;
    }
    table.totals td.v {
        text-align: right;
        font-weight: bold;
        color: #1f2937;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    table.totals tr.sub td      { border-bottom: 1px solid #eaeef4; }
    table.totals tr.iva-row td  { border-bottom: 1px solid #eaeef4; background: #fafbfd; }
    table.totals tr.grand td {
        font-size: 13px;
        font-weight: bold;
        color: #fff;
        background: #0b1d3d;
        padding: 12px 14px;
        letter-spacing: 0.4px;
    }
    table.totals tr.grand td.k {
        font-size: 9px;
        letter-spacing: 1.6px;
        text-transform: uppercase;
        color: #aab8d0;
        font-weight: bold;
    }

    /* ── FOOTER ──────────────────────────────────────────────── */
    .footer {
        margin-top: 32px;
        padding-top: 12px;
        border-top: 1px solid #e2e8f0;
        font-size: 7.5px;
        color: #8a96a8;
    }
    .footer table { width: 100%; border-collapse: collapse; }
    .footer td { vertical-align: top; }
    .footer .right { text-align: right; }
    .footer strong { color: #4a5568; }
</style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════════════ --}}
<div class="doc-header">
    <table>
        <tr>
            <td style="width:60%;">
                <div class="brand-eyebrow">Documento fiscale</div>
                <div class="brand-title serif">Fattura</div>
                <div class="brand-subtitle">Fattura elettronica &mdash; {{ $documento['tipo'] ?? 'TD01' }}</div>
            </td>
            <td class="doc-ident" style="width:40%;">
                <div class="num-label">Numero</div>
                <div class="num-value">{{ $documento['numero'] }}</div>
                <div class="num-date">
                    Emessa il <strong>{{ \Carbon\Carbon::parse($documento['data'])->format('d/m/Y') }}</strong>
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════════
     CEDENTE / COMMITTENTE
═══════════════════════════════════════════════════════════ --}}
<table class="parties">
    <tr>
        <td>
            <div class="party-eyebrow">Cedente / Prestatore</div>
            <div class="party-name">{{ $cedente['nome'] }}</div>
            @if($cedente['indirizzo'])
                <div class="party-line">
                    {{ $cedente['indirizzo'] }}<br>
                    {{ $cedente['cap'] }} {{ $cedente['comune'] }}@if($cedente['provincia']) ({{ $cedente['provincia'] }})@endif
                </div>
            @endif
            @if($cedente['piva'])
                <div class="party-meta"><span class="k">P.IVA</span> {{ $cedente['piva'] }}</div>
            @endif
        </td>
        <td class="right-cell">
            <div class="party-eyebrow">Cessionario / Committente</div>
            <div class="party-name">{{ $committente['nome'] ?: '—' }}</div>
            @if($committente['indirizzo'])
                <div class="party-line">
                    {{ $committente['indirizzo'] }}<br>
                    {{ $committente['cap'] }} {{ $committente['comune'] }}@if($committente['provincia']) ({{ $committente['provincia'] }})@endif
                </div>
            @endif
            @if($committente['piva'])
                <div class="party-meta"><span class="k">P.IVA</span> {{ $committente['piva'] }}</div>
            @endif

            {{-- Extra dal model se disponibili --}}
            @if(isset($invoice) && $invoice instanceof \App\Models\TableOrderInvoice && $invoice->customer)
                @php $c = $invoice->customer; @endphp
                @if($c->fiscal_code)
                    <div class="party-meta"><span class="k">C.F.</span> {{ $c->fiscal_code }}</div>
                @endif
                @if($c->codice_destinatario)
                    <div class="party-meta"><span class="k">SDI</span> {{ $c->codice_destinatario }}</div>
                @endif
                @if($c->pec_destinatario)
                    <div class="party-meta"><span class="k">PEC</span> {{ $c->pec_destinatario }}</div>
                @endif
            @endif
        </td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════════
     META DOCUMENTO
═══════════════════════════════════════════════════════════ --}}
<table class="doc-meta">
    <tr>
        <td style="width:25%">
            <span class="k">Tipo documento</span>
            <span class="v">{{ $documento['tipo'] ?? 'TD01' }}</span>
        </td>
        <td style="width:25%">
            <span class="k">Data emissione</span>
            <span class="v">{{ \Carbon\Carbon::parse($documento['data'])->format('d/m/Y') }}</span>
        </td>
        <td style="width:25%">
            <span class="k">Valuta</span>
            <span class="v">{{ $documento['divisa'] ?? 'EUR' }}</span>
        </td>
        <td style="width:25%; text-align:right;">
            <span class="k">Totale documento</span>
            <span class="v" style="font-size:13px;">€ {{ number_format($documento['totale'], 2, ',', '.') }}</span>
        </td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════════
     RIGHE DOCUMENTO
═══════════════════════════════════════════════════════════ --}}
<div class="section-eyebrow">Dettaglio voci</div>
<table class="lines">
    <thead>
        <tr>
            <th class="center" style="width:4%">#</th>
            <th style="width:38%">Descrizione</th>
            <th class="right"  style="width:8%">Qtà</th>
            <th class="center" style="width:6%">U.M.</th>
            <th class="right"  style="width:16%">Prezzo unit.</th>
            <th class="right"  style="width:18%">Totale</th>
            <th class="right"  style="width:10%">IVA</th>
        </tr>
    </thead>
    <tbody>
        @foreach($linee as $linea)
        <tr>
            <td class="center">{{ $linea['numero'] }}</td>
            <td class="desc">{{ $linea['descrizione'] }}</td>
            <td class="right num">{{ number_format($linea['quantita'], 2, ',', '.') }}</td>
            <td class="um">{{ $linea['unita'] ?: '—' }}</td>
            <td class="right num">€&nbsp;{{ number_format($linea['prezzo_unitario'], 2, ',', '.') }}</td>
            <td class="right num" style="font-weight:600; color:#0b1d3d;">€&nbsp;{{ number_format($linea['prezzo_totale'], 2, ',', '.') }}</td>
            <td class="iva num">{{ number_format($linea['iva'], 0) }}%</td>
        </tr>
        @endforeach
    </tbody>
    @if(count($linee) > 1)
    <tfoot>
        <tr>
            <td colspan="5" class="label">Subtotale imponibile</td>
            <td class="right num">€&nbsp;{{ number_format(collect($linee)->sum('prezzo_totale'), 2, ',', '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ═══════════════════════════════════════════════════════════
     PAGAMENTO + RIEPILOGO IVA / TOTALI
═══════════════════════════════════════════════════════════ --}}
<table class="bottom">
    <tr>
        <td class="pay">
            @if($pagamento)
            <div class="section-eyebrow">Modalità di pagamento</div>
            <div class="pay-block">
                @php
                    $modMap = [
                        'MP01' => 'Contanti',
                        'MP02' => 'Assegno',
                        'MP04' => 'Bonifico',
                        'MP05' => 'Bonifico bancario',
                        'MP07' => 'Bollettino bancario',
                        'MP08' => 'Carta di pagamento',
                        'MP12' => 'Riba',
                    ];
                    $modLabel = $modMap[$pagamento['modalita']] ?? $pagamento['modalita'];
                @endphp
                <div class="row">
                    <span class="k">Metodo</span>
                    <span class="v">{{ $modLabel }}</span>
                </div>
                @if($pagamento['scadenza'])
                <div class="row">
                    <span class="k">Scadenza</span>
                    <span class="v">{{ \Carbon\Carbon::parse($pagamento['scadenza'])->format('d/m/Y') }}</span>
                </div>
                @endif
                @if($pagamento['importo'])
                <div class="row">
                    <span class="k">Importo da pagare</span>
                    <span class="v">€ {{ number_format($pagamento['importo'], 2, ',', '.') }}</span>
                </div>
                @endif
            </div>
            @endif
        </td>

        <td class="tot">
            <table class="totals">
                @if(!empty($riepilogo))
                    @foreach($riepilogo as $r)
                    <tr class="sub">
                        <td class="k">Imponibile {{ number_format($r['aliquota'], 0) }}%</td>
                        <td class="v">€&nbsp;{{ number_format($r['imponibile'], 2, ',', '.') }}</td>
                    </tr>
                    <tr class="iva-row">
                        <td class="k">IVA {{ number_format($r['aliquota'], 0) }}%</td>
                        <td class="v">€&nbsp;{{ number_format($r['imposta'], 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                @else
                    @php
                        $totImponibile = collect($linee)->sum('prezzo_totale');
                        $aliquota = count($linee) > 0 ? $linee[0]['iva'] : 0;
                        $totIva = $aliquota > 0 ? round($totImponibile - ($totImponibile / (1 + $aliquota / 100)), 2) : 0;
                        $imponibile = round($totImponibile - $totIva, 2);
                    @endphp
                    <tr class="sub">
                        <td class="k">Imponibile</td>
                        <td class="v">€&nbsp;{{ number_format($imponibile, 2, ',', '.') }}</td>
                    </tr>
                    @if($aliquota > 0)
                    <tr class="iva-row">
                        <td class="k">IVA {{ number_format($aliquota, 0) }}%</td>
                        <td class="v">€&nbsp;{{ number_format($totIva, 2, ',', '.') }}</td>
                    </tr>
                    @endif
                @endif
                <tr class="grand">
                    <td class="k">Totale documento</td>
                    <td class="v">€&nbsp;{{ number_format($documento['totale'], 2, ',', '.') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════════════ --}}
<div class="footer">
    <table>
        <tr>
            <td>
                Documento generato automaticamente dal sistema gestionale.<br>
                Fattura elettronica in formato FatturaPA &mdash; non costituisce documento fiscale cartaceo.
            </td>
            <td class="right">
                <strong>Rif. file</strong>
                {{ isset($invoice->invoice_name) ? $invoice->invoice_name . '.xml' : (isset($invoice->filename) ? $invoice->filename : '—') }}<br>
                Generato il {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>
</div>

</body>
</html>
