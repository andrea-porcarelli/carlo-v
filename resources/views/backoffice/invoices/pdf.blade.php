<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 9.5px;
        color: #1a1a2e;
        background: #fff;
        padding: 36px 42px;
        line-height: 1.45;
    }

    /* ── TOP BANNER ─────────────────────────────────────────── */
    .top-banner {
        background: #1a2b4a;
        color: #fff;
        padding: 16px 22px 14px;
        margin-bottom: 28px;
    }
    .top-banner table { width: 100%; border-collapse: collapse; }
    .top-banner .banner-title {
        font-size: 22px;
        font-weight: bold;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: #fff;
    }
    .top-banner .banner-subtitle {
        font-size: 8.5px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #8aafd4;
        margin-top: 3px;
    }
    .top-banner .banner-docnum {
        font-size: 11px;
        font-weight: bold;
        color: #fff;
        text-align: right;
    }
    .top-banner .banner-date {
        font-size: 9px;
        color: #8aafd4;
        text-align: right;
        margin-top: 4px;
    }

    /* ── PARTIES ─────────────────────────────────────────────── */
    .parties-table { width: 100%; border-collapse: collapse; margin-bottom: 26px; }
    .party-cell { width: 50%; vertical-align: top; padding: 0; }
    .party-cell.right { padding-left: 20px; }

    .party-box {
        border: 1px solid #d4dce8;
        padding: 14px 16px;
        background: #fff;
    }
    .party-box.cedente {
        border-left: 4px solid #1a2b4a;
    }
    .party-box.committente {
        border-left: 4px solid #3a7bd5;
    }
    .party-role {
        font-size: 7.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: #7a8fa6;
        margin-bottom: 5px;
    }
    .party-name {
        font-size: 12px;
        font-weight: bold;
        color: #1a2b4a;
        margin-bottom: 5px;
    }
    .party-detail {
        font-size: 9px;
        color: #4a5568;
        line-height: 1.6;
    }
    .party-detail .label {
        color: #9aa5b1;
        font-size: 8px;
    }

    /* ── DOC META BAR ────────────────────────────────────────── */
    .meta-bar {
        background: #f0f4fb;
        border: 1px solid #d4dce8;
        border-top: 3px solid #3a7bd5;
        margin-bottom: 26px;
        padding: 0;
    }
    .meta-bar table { width: 100%; border-collapse: collapse; }
    .meta-cell {
        padding: 11px 18px;
        border-right: 1px solid #d4dce8;
        vertical-align: top;
    }
    .meta-cell:last-child { border-right: none; }
    .meta-label {
        font-size: 7.5px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #7a8fa6;
        margin-bottom: 3px;
    }
    .meta-value {
        font-size: 12px;
        font-weight: bold;
        color: #1a2b4a;
    }

    /* ── LINES TABLE ─────────────────────────────────────────── */
    .section-title {
        font-size: 7.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: #7a8fa6;
        margin-bottom: 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid #e2e8f0;
    }

    table.lines {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 24px;
        font-size: 9px;
    }
    table.lines thead tr {
        background: #1a2b4a;
        color: #fff;
    }
    table.lines thead th {
        padding: 8px 10px;
        text-align: left;
        font-size: 7.5px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        font-weight: bold;
    }
    table.lines thead th.right { text-align: right; }
    table.lines thead th.center { text-align: center; }

    table.lines tbody tr:nth-child(even) { background: #f7f9fc; }
    table.lines tbody tr:nth-child(odd)  { background: #fff; }
    table.lines tbody td {
        padding: 8px 10px;
        border-bottom: 1px solid #e8edf3;
        vertical-align: top;
        color: #2d3748;
    }
    table.lines tbody td.right  { text-align: right; }
    table.lines tbody td.center { text-align: center; color: #7a8fa6; }
    table.lines tbody td.desc   { font-weight: 500; color: #1a2b4a; }
    table.lines tfoot td {
        padding: 9px 10px;
        background: #edf2f7;
        font-weight: bold;
        font-size: 9px;
        border-top: 2px solid #c8d4e3;
    }
    table.lines tfoot td.right { text-align: right; }

    /* ── TOTALS + PAYMENT ────────────────────────────────────── */
    .bottom-section { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .payment-cell { vertical-align: top; width: 55%; padding-right: 20px; }
    .totals-cell  { vertical-align: top; width: 45%; }

    .payment-box {
        border: 1px solid #d4dce8;
        padding: 13px 16px;
        background: #fafbfd;
    }
    .payment-box .section-title { margin-bottom: 9px; }
    .payment-row { margin-bottom: 5px; font-size: 9px; color: #4a5568; }
    .payment-row .pl { color: #9aa5b1; font-size: 8px; display: block; margin-bottom: 1px; }
    .payment-row .pv { font-weight: bold; color: #1a2b4a; }

    table.totals {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
    }
    table.totals td {
        padding: 7px 12px;
        border-bottom: 1px solid #e8edf3;
        color: #4a5568;
    }
    table.totals td:last-child { text-align: right; font-weight: bold; color: #2d3748; }
    table.totals tr.subtotal td { background: #f7f9fc; }
    table.totals tr.iva td { background: #fff; }
    table.totals tr.grand-total {
        background: #1a2b4a;
    }
    table.totals tr.grand-total td {
        font-size: 11.5px;
        font-weight: bold;
        color: #fff;
        border-bottom: none;
        padding: 10px 12px;
    }
    table.totals tr.grand-total td:last-child { color: #7dd3fc; }

    /* ── FOOTER ──────────────────────────────────────────────── */
    .footer-bar {
        margin-top: 34px;
        padding-top: 10px;
        border-top: 1px solid #e2e8f0;
    }
    .footer-bar table { width: 100%; border-collapse: collapse; }
    .footer-note {
        font-size: 7.5px;
        color: #a0aab4;
        line-height: 1.5;
    }
    .footer-stamp {
        font-size: 7.5px;
        color: #a0aab4;
        text-align: right;
    }
    .footer-stamp strong { color: #7a8fa6; }
</style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════
     TOP BANNER
═══════════════════════════════════════════════════════════ --}}
<div class="top-banner">
    <table>
        <tr>
            <td style="vertical-align:middle;">
                <div class="banner-title">Fattura</div>
                <div class="banner-subtitle">Fattura Elettronica &mdash; {{ $documento['tipo'] ?? 'TD01' }}</div>
            </td>
            <td style="vertical-align:middle; text-align:right;">
                <div class="banner-docnum">N° {{ $documento['numero'] }}</div>
                <div class="banner-date">del {{ \Carbon\Carbon::parse($documento['data'])->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════════
     CEDENTE / COMMITTENTE
═══════════════════════════════════════════════════════════ --}}
<table class="parties-table">
    <tr>
        <td class="party-cell">
            <div class="party-box cedente">
                <div class="party-role">Cedente / Prestatore</div>
                <div class="party-name">{{ $cedente['nome'] }}</div>
                <div class="party-detail">
                    @if($cedente['piva'])
                        <span class="label">P.IVA &nbsp;</span>{{ $cedente['piva'] }}<br>
                    @endif
                    @if($cedente['indirizzo'])
                        {{ $cedente['indirizzo'] }}<br>
                        {{ $cedente['cap'] }} {{ $cedente['comune'] }}
                        @if($cedente['provincia']) ({{ $cedente['provincia'] }}) @endif
                    @endif
                </div>
            </div>
        </td>
        <td class="party-cell right">
            <div class="party-box committente">
                <div class="party-role">Cessionario / Committente</div>
                <div class="party-name">{{ $committente['nome'] ?: '—' }}</div>
                <div class="party-detail">
                    @if($committente['piva'])
                        <span class="label">P.IVA &nbsp;</span>{{ $committente['piva'] }}<br>
                    @endif
                    @if($committente['indirizzo'])
                        {{ $committente['indirizzo'] }}<br>
                        {{ $committente['cap'] }} {{ $committente['comune'] }}
                        @if($committente['provincia']) ({{ $committente['provincia'] }}) @endif
                    @endif

                    {{-- Dati aggiuntivi dal model se disponibili --}}
                    @if(isset($invoice) && $invoice instanceof \App\Models\TableOrderInvoice && $invoice->customer)
                        @php $c = $invoice->customer; @endphp
                        @if($c->fiscal_code)
                            <br><span class="label">C.F. &nbsp;</span>{{ $c->fiscal_code }}
                        @endif
                        @if($c->codice_destinatario)
                            <br><span class="label">SDI &nbsp;</span>{{ $c->codice_destinatario }}
                        @endif
                        @if($c->pec_destinatario)
                            <br><span class="label">PEC &nbsp;</span>{{ $c->pec_destinatario }}
                        @endif
                    @endif
                </div>
            </div>
        </td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════════
     META BAR (numero, data, tipo, valuta)
═══════════════════════════════════════════════════════════ --}}
<div class="meta-bar">
    <table>
        <tr>
            <td class="meta-cell" style="width:28%">
                <div class="meta-label">Numero Fattura</div>
                <div class="meta-value">{{ $documento['numero'] }}</div>
            </td>
            <td class="meta-cell" style="width:22%">
                <div class="meta-label">Data Emissione</div>
                <div class="meta-value">{{ \Carbon\Carbon::parse($documento['data'])->format('d/m/Y') }}</div>
            </td>
            <td class="meta-cell" style="width:22%">
                <div class="meta-label">Tipo Documento</div>
                <div class="meta-value">{{ $documento['tipo'] ?? 'TD01' }}</div>
            </td>
            <td class="meta-cell" style="width:14%">
                <div class="meta-label">Valuta</div>
                <div class="meta-value">{{ $documento['divisa'] ?? 'EUR' }}</div>
            </td>
            <td class="meta-cell" style="width:14%">
                <div class="meta-label">Importo Totale</div>
                <div class="meta-value" style="color:#3a7bd5;">€ {{ number_format($documento['totale'], 2, ',', '.') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════════
     RIGHE DOCUMENTO
═══════════════════════════════════════════════════════════ --}}
<div class="section-title">Dettaglio voci</div>
<table class="lines">
    <thead>
        <tr>
            <th class="center" style="width:5%">#</th>
            <th style="width:44%">Descrizione</th>
            <th class="right" style="width:9%">Qtà</th>
            <th style="width:5%">U.M.</th>
            <th class="right" style="width:14%">Prezzo Unitario</th>
            <th class="right" style="width:14%">Totale Riga</th>
            <th class="right" style="width:9%">IVA %</th>
        </tr>
    </thead>
    <tbody>
        @foreach($linee as $linea)
        <tr>
            <td class="center">{{ $linea['numero'] }}</td>
            <td class="desc">{{ $linea['descrizione'] }}</td>
            <td class="right">{{ number_format($linea['quantita'], 2, ',', '.') }}</td>
            <td style="color:#7a8fa6;">{{ $linea['unita'] ?: '—' }}</td>
            <td class="right">€&nbsp;{{ number_format($linea['prezzo_unitario'], 4, ',', '.') }}</td>
            <td class="right" style="font-weight:600;">€&nbsp;{{ number_format($linea['prezzo_totale'], 2, ',', '.') }}</td>
            <td class="right">{{ number_format($linea['iva'], 0) }}%</td>
        </tr>
        @endforeach
    </tbody>
    @if(count($linee) > 1)
    <tfoot>
        <tr>
            <td colspan="5" class="right" style="font-size:8px; color:#7a8fa6; font-weight:normal;">Subtotale imponibile</td>
            <td class="right">€&nbsp;{{ number_format(collect($linee)->sum('prezzo_totale'), 2, ',', '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ═══════════════════════════════════════════════════════════
     PAGAMENTO + RIEPILOGO IVA / TOTALI
═══════════════════════════════════════════════════════════ --}}
<table class="bottom-section">
    <tr>
        {{-- Pagamento --}}
        <td class="payment-cell">
            @if($pagamento)
            <div class="payment-box">
                <div class="section-title">Modalità di pagamento</div>
                @php
                    $modMap = [
                        'MP01' => 'Contanti',
                        'MP02' => 'Assegno',
                        'MP04' => 'Bonifico',
                        'MP05' => 'Bonifico Bancario',
                        'MP07' => 'Bollettino Bancario',
                        'MP08' => 'Carta di Pagamento',
                        'MP12' => 'Riba',
                    ];
                    $modLabel = $modMap[$pagamento['modalita']] ?? $pagamento['modalita'];
                @endphp
                <div class="payment-row">
                    <span class="pl">Metodo</span>
                    <span class="pv">{{ $modLabel }}</span>
                </div>
                @if($pagamento['scadenza'])
                <div class="payment-row">
                    <span class="pl">Scadenza</span>
                    <span class="pv">{{ \Carbon\Carbon::parse($pagamento['scadenza'])->format('d/m/Y') }}</span>
                </div>
                @endif
                @if($pagamento['importo'])
                <div class="payment-row">
                    <span class="pl">Importo da pagare</span>
                    <span class="pv">€ {{ number_format($pagamento['importo'], 2, ',', '.') }}</span>
                </div>
                @endif
            </div>
            @else
            <div style="height:10px;"></div>
            @endif
        </td>

        {{-- Riepilogo IVA + Totale --}}
        <td class="totals-cell">
            <table class="totals">
                @foreach($riepilogo as $r)
                <tr class="subtotal">
                    <td>Imponibile {{ number_format($r['aliquota'], 0) }}%</td>
                    <td>€&nbsp;{{ number_format($r['imponibile'], 2, ',', '.') }}</td>
                </tr>
                <tr class="iva">
                    <td>IVA {{ number_format($r['aliquota'], 0) }}%</td>
                    <td>€&nbsp;{{ number_format($r['imposta'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
                @if(empty($riepilogo))
                {{-- Fallback senza DatiRiepilogo (es. privati) --}}
                @php
                    $totImponibile = collect($linee)->sum('prezzo_totale');
                    $aliquota = count($linee) > 0 ? $linee[0]['iva'] : 0;
                    $totIva = $aliquota > 0 ? round($totImponibile - ($totImponibile / (1 + $aliquota / 100)), 2) : 0;
                    $imponibile = round($totImponibile - $totIva, 2);
                @endphp
                <tr class="subtotal">
                    <td>Imponibile</td>
                    <td>€&nbsp;{{ number_format($imponibile, 2, ',', '.') }}</td>
                </tr>
                @if($aliquota > 0)
                <tr class="iva">
                    <td>IVA {{ number_format($aliquota, 0) }}%</td>
                    <td>€&nbsp;{{ number_format($totIva, 2, ',', '.') }}</td>
                </tr>
                @endif
                @endif
                <tr class="grand-total">
                    <td>Totale Documento</td>
                    <td>€&nbsp;{{ number_format($documento['totale'], 2, ',', '.') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════════════ --}}
<div class="footer-bar">
    <table>
        <tr>
            <td class="footer-note">
                Documento generato automaticamente dal sistema gestionale.<br>
                Fattura elettronica in formato FatturaPA &mdash; non costituisce documento fiscale cartaceo.
            </td>
            <td class="footer-stamp">
                <strong>Rif. file:</strong>
                {{ isset($invoice->invoice_name) ? $invoice->invoice_name . '.xml' : (isset($invoice->filename) ? $invoice->filename : '—') }}<br>
                Generato il {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>
</div>

</body>
</html>
