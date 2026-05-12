<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 10px;
        color: #2d3748;
        background: #fff;
        line-height: 1.55;
    }

    .page {
        padding: 40px 48px 50px 48px;
    }

    /* ── BANNER ──────────────────────────────────────────────── */
    .banner {
        background: #0b2545;
        color: #fff;
        padding: 26px 32px;
        margin-bottom: 32px;
        border-bottom: 4px solid #c9a449;
    }
    .banner table { width: 100%; border-collapse: collapse; }
    .banner td { vertical-align: middle; }

    .banner-eyebrow {
        font-size: 8px;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: #c9a449;
        margin-bottom: 8px;
    }
    .banner-title {
        font-size: 30px;
        font-weight: bold;
        letter-spacing: 6px;
        text-transform: uppercase;
        color: #fff;
        line-height: 1;
    }
    .banner-company {
        font-size: 9px;
        letter-spacing: 0.8px;
        color: #aab9d4;
        margin-top: 10px;
    }

    .banner-num-label {
        font-size: 8px;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: #c9a449;
        text-align: right;
    }
    .banner-num {
        font-size: 18px;
        font-weight: bold;
        color: #fff;
        text-align: right;
        margin-top: 4px;
        letter-spacing: 1px;
    }
    .banner-date {
        font-size: 9px;
        color: #aab9d4;
        text-align: right;
        margin-top: 10px;
    }

    /* ── SECTION TITLE ───────────────────────────────────────── */
    .section-title {
        font-size: 8px;
        font-weight: bold;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: #c9a449;
        margin-bottom: 12px;
    }
    .section-title .dash {
        display: inline-block;
        width: 20px;
        height: 2px;
        background: #c9a449;
        vertical-align: middle;
        margin-right: 8px;
    }

    /* ── PARTIES ─────────────────────────────────────────────── */
    .parties { width: 100%; border-collapse: collapse; margin-bottom: 32px; }
    .parties td {
        width: 50%;
        vertical-align: top;
        padding: 0;
    }
    .parties td.right-cell { padding-left: 24px; }

    .party-card {
        background: #f7f9fc;
        border-left: 3px solid #0b2545;
        padding: 18px 20px 18px 22px;
    }
    .party-card.committente {
        border-left-color: #c9a449;
    }
    .party-role {
        font-size: 7.5px;
        font-weight: bold;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #8a96a8;
        margin-bottom: 8px;
    }
    .party-name {
        font-size: 13px;
        font-weight: bold;
        color: #0b2545;
        margin-bottom: 10px;
        line-height: 1.3;
    }
    .party-detail {
        font-size: 9.5px;
        color: #4a5568;
        line-height: 1.7;
    }
    .party-detail .row { margin-bottom: 2px; }
    .party-detail .k {
        display: inline-block;
        min-width: 42px;
        color: #8a96a8;
        font-size: 8px;
        letter-spacing: 1px;
        text-transform: uppercase;
        font-weight: bold;
    }

    /* ── META BAR ────────────────────────────────────────────── */
    .meta-bar {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-top: 3px solid #0b2545;
        margin-bottom: 32px;
    }
    .meta-bar table { width: 100%; border-collapse: collapse; }
    .meta-cell {
        padding: 14px 20px;
        border-right: 1px solid #e2e8f0;
        vertical-align: top;
    }
    .meta-cell:last-child { border-right: none; }
    .meta-label {
        font-size: 7.5px;
        letter-spacing: 1.8px;
        text-transform: uppercase;
        color: #8a96a8;
        margin-bottom: 4px;
    }
    .meta-value {
        font-size: 12px;
        font-weight: bold;
        color: #0b2545;
        letter-spacing: 0.3px;
    }
    .meta-value.accent { color: #c9a449; }

    /* ── LINES TABLE ─────────────────────────────────────────── */
    table.lines {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 28px;
        font-size: 9.5px;
    }
    table.lines thead tr { background: #0b2545; }
    table.lines thead th {
        padding: 12px 12px;
        text-align: left;
        font-size: 7.5px;
        text-transform: uppercase;
        letter-spacing: 1.4px;
        font-weight: bold;
        color: #fff;
    }
    table.lines thead th.right  { text-align: right; }
    table.lines thead th.center { text-align: center; }

    table.lines tbody tr:nth-child(even) td { background: #f7f9fc; }
    table.lines tbody tr:nth-child(odd) td  { background: #fff; }

    table.lines tbody td {
        padding: 11px 12px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
        color: #2d3748;
    }
    table.lines tbody td.right  { text-align: right; white-space: nowrap; }
    table.lines tbody td.center { text-align: center; color: #8a96a8; }
    table.lines tbody td.desc   { color: #0b2545; font-weight: 600; }
    table.lines tbody td.um     { text-align: center; color: #8a96a8; font-size: 9px; }

    table.lines tfoot td {
        padding: 12px 12px;
        font-size: 9px;
        color: #4a5568;
        background: #fafbfd;
        border-top: 2px solid #0b2545;
    }
    table.lines tfoot td.right  { text-align: right; white-space: nowrap; font-weight: bold; color: #0b2545; }
    table.lines tfoot td.label  {
        text-align: right;
        font-size: 7.5px;
        letter-spacing: 1.6px;
        text-transform: uppercase;
        color: #8a96a8;
    }

    /* ── NOTE ────────────────────────────────────────────────── */
    .notes-box {
        background: #fafbfd;
        border: 1px solid #e2e8f0;
        border-left: 3px solid #c9a449;
        padding: 16px 20px;
        margin-bottom: 28px;
        font-size: 9.5px;
        color: #2d3748;
        line-height: 1.6;
    }

    /* ── BOTTOM: PAGAMENTO + TOTALE ──────────────────────────── */
    .bottom { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .bottom td.pay { width: 52%; vertical-align: top; padding-right: 28px; }
    .bottom td.tot { width: 48%; vertical-align: top; }

    .pay-box {
        background: #f7f9fc;
        border-left: 3px solid #c9a449;
        padding: 18px 22px;
    }
    .pay-row {
        margin-bottom: 10px;
        font-size: 9.5px;
    }
    .pay-row:last-child { margin-bottom: 0; }
    .pay-k {
        display: block;
        font-size: 7.5px;
        letter-spacing: 1.6px;
        text-transform: uppercase;
        color: #8a96a8;
        margin-bottom: 2px;
        font-weight: bold;
    }
    .pay-v {
        font-weight: bold;
        color: #0b2545;
        font-size: 10.5px;
    }

    /* Totals card */
    .totals-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        padding: 16px 18px 0 18px;
    }
    table.totals {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
        margin-bottom: 0;
    }
    table.totals td {
        padding: 9px 4px;
        color: #4a5568;
        border-bottom: 1px solid #eaeef4;
    }
    table.totals td.k {
        font-size: 9px;
        color: #4a5568;
    }
    table.totals td.v {
        text-align: right;
        font-weight: bold;
        color: #2d3748;
        white-space: nowrap;
    }
    table.totals tr.iva-row td { background: #fafbfd; }

    .grand-total-card {
        background: #0b2545;
        color: #fff;
        padding: 18px 22px;
        margin: 0 -18px -16px -18px;
        border-top: 4px solid #c9a449;
    }
    .grand-total-card table { width: 100%; border-collapse: collapse; }
    .grand-total-card td { vertical-align: middle; }
    .grand-total-label {
        font-size: 8.5px;
        letter-spacing: 2.2px;
        text-transform: uppercase;
        color: #c9a449;
        font-weight: bold;
    }
    .grand-total-value {
        font-size: 18px;
        font-weight: bold;
        color: #fff;
        text-align: right;
        white-space: nowrap;
        letter-spacing: 0.4px;
    }

    /* ── FOOTER ──────────────────────────────────────────────── */
    .footer {
        margin-top: 40px;
        padding-top: 16px;
        border-top: 1px solid #e2e8f0;
        font-size: 7.5px;
        color: #8a96a8;
        line-height: 1.6;
    }
    .footer table { width: 100%; border-collapse: collapse; }
    .footer td { vertical-align: top; }
    .footer .right { text-align: right; }
    .footer strong { color: #4a5568; }
</style>
</head>
<body>

<div class="page">

{{-- ═══════════════════════════════════════════════════════════
     BANNER
═══════════════════════════════════════════════════════════ --}}
<div class="banner">
    <table>
        <tr>
            <td style="width:62%;">
                <div class="banner-eyebrow">Fattura elettronica &mdash; {{ $documento['tipo'] ?? 'TD01' }}</div>
                <div class="banner-title">Fattura</div>
                <div class="banner-company">{{ $cedente['nome'] }}</div>
            </td>
            <td style="width:38%;">
                <div class="banner-num-label">Numero</div>
                <div class="banner-num">{{ $documento['numero'] }}</div>
                <div class="banner-date">
                    Emessa il {{ \Carbon\Carbon::parse($documento['data'])->format('d/m/Y') }}
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════════
     CEDENTE / COMMITTENTE
═══════════════════════════════════════════════════════════ --}}
<div class="section-title"><span class="dash"></span>Parti</div>
<table class="parties">
    <tr>
        <td>
            <div class="party-card">
                <div class="party-role">Cedente / Prestatore</div>
                <div class="party-name">{{ $cedente['nome'] }}</div>
                <div class="party-detail">
                    @if($cedente['indirizzo'])
                        <div class="row">{{ $cedente['indirizzo'] }}</div>
                        <div class="row">
                            {{ $cedente['cap'] }} {{ $cedente['comune'] }}@if($cedente['provincia']) ({{ $cedente['provincia'] }})@endif
                        </div>
                    @endif
                    @if($cedente['piva'])
                        <div class="row"><span class="k">P.IVA</span> {{ $cedente['piva'] }}</div>
                    @endif
                </div>
            </div>
        </td>
        <td class="right-cell">
            <div class="party-card committente">
                <div class="party-role">Cessionario / Committente</div>
                <div class="party-name">{{ $committente['nome'] ?: '—' }}</div>
                <div class="party-detail">
                    @if($committente['indirizzo'])
                        <div class="row">{{ $committente['indirizzo'] }}</div>
                        <div class="row">
                            {{ $committente['cap'] }} {{ $committente['comune'] }}@if($committente['provincia']) ({{ $committente['provincia'] }})@endif
                        </div>
                    @endif
                    @if($committente['piva'])
                        <div class="row"><span class="k">P.IVA</span> {{ $committente['piva'] }}</div>
                    @endif

                    @if(isset($invoice) && $invoice instanceof \App\Models\TableOrderInvoice && $invoice->customer)
                        @php $c = $invoice->customer; @endphp
                        @if($c->fiscal_code)
                            <div class="row"><span class="k">C.F.</span> {{ $c->fiscal_code }}</div>
                        @endif
                        @if($c->codice_destinatario)
                            <div class="row"><span class="k">SDI</span> {{ $c->codice_destinatario }}</div>
                        @endif
                        @if($c->pec_destinatario)
                            <div class="row"><span class="k">PEC</span> {{ $c->pec_destinatario }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════════
     META BAR
═══════════════════════════════════════════════════════════ --}}
<div class="meta-bar">
    <table>
        <tr>
            <td class="meta-cell" style="width:25%">
                <div class="meta-label">Tipo documento</div>
                <div class="meta-value">{{ $documento['tipo'] ?? 'TD01' }}</div>
            </td>
            <td class="meta-cell" style="width:25%">
                <div class="meta-label">Data emissione</div>
                <div class="meta-value">{{ \Carbon\Carbon::parse($documento['data'])->format('d/m/Y') }}</div>
            </td>
            <td class="meta-cell" style="width:25%">
                <div class="meta-label">Valuta</div>
                <div class="meta-value">{{ $documento['divisa'] ?? 'EUR' }}</div>
            </td>
            <td class="meta-cell" style="width:25%; text-align:right;">
                <div class="meta-label">Totale documento</div>
                <div class="meta-value accent" style="font-size:14px;">€ {{ number_format($documento['totale'], 2, ',', '.') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════════
     RIGHE DOCUMENTO
═══════════════════════════════════════════════════════════ --}}
<div class="section-title"><span class="dash"></span>Dettaglio voci</div>
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
            <td class="right">{{ number_format($linea['quantita'], 2, ',', '.') }}</td>
            <td class="um">{{ $linea['unita'] ?: '—' }}</td>
            <td class="right">€&nbsp;{{ number_format($linea['prezzo_unitario'], 2, ',', '.') }}</td>
            <td class="right" style="font-weight:bold; color:#0b2545;">€&nbsp;{{ number_format($linea['prezzo_totale'], 2, ',', '.') }}</td>
            <td class="right">{{ number_format($linea['iva'], 0) }}%</td>
        </tr>
        @endforeach
    </tbody>
    @if(count($linee) > 1)
    <tfoot>
        <tr>
            <td colspan="5" class="label">Subtotale imponibile</td>
            <td class="right">€&nbsp;{{ number_format(collect($linee)->sum('prezzo_totale'), 2, ',', '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ═══════════════════════════════════════════════════════════
     NOTE
═══════════════════════════════════════════════════════════ --}}
@if(!empty($invoice->description))
<div class="section-title"><span class="dash"></span>Note</div>
<div class="notes-box">
    {!! nl2br(e($invoice->description)) !!}
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════
     PAGAMENTO + RIEPILOGO IVA / TOTALI
═══════════════════════════════════════════════════════════ --}}
<table class="bottom">
    <tr>
        <td class="pay">
            @if($pagamento)
            <div class="section-title"><span class="dash"></span>Pagamento</div>
            <div class="pay-box">
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
                <div class="pay-row">
                    <span class="pay-k">Metodo</span>
                    <span class="pay-v">{{ $modLabel }}</span>
                </div>
                @if($pagamento['scadenza'])
                <div class="pay-row">
                    <span class="pay-k">Scadenza</span>
                    <span class="pay-v">{{ \Carbon\Carbon::parse($pagamento['scadenza'])->format('d/m/Y') }}</span>
                </div>
                @endif
                @if($pagamento['importo'])
                <div class="pay-row">
                    <span class="pay-k">Importo da pagare</span>
                    <span class="pay-v">€ {{ number_format($pagamento['importo'], 2, ',', '.') }}</span>
                </div>
                @endif
            </div>
            @endif
        </td>

        <td class="tot">
            <div class="section-title"><span class="dash"></span>Riepilogo</div>
            <div class="totals-card">
                <table class="totals">
                    @if(!empty($riepilogo))
                        @foreach($riepilogo as $r)
                        <tr>
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
                        <tr>
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
                </table>

                <div class="grand-total-card">
                    <table>
                        <tr>
                            <td>
                                <span class="grand-total-label">Totale documento</span>
                            </td>
                            <td class="grand-total-value">
                                €&nbsp;{{ number_format($documento['totale'], 2, ',', '.') }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
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

</div>

</body>
</html>
