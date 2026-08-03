<div class="dish-cost-breakdown">
    @php
        $total       = (float) ($breakdown['total_cost'] ?? 0);
        $coverage    = (float) ($breakdown['coverage'] ?? 0);
        $materials   = $breakdown['materials'] ?? [];
        $count       = count($materials);
        $known       = count(array_filter($materials, fn($m) => $m['has_cost']));
        $missing     = $count - $known;
        $fcPercent   = ($sellingPrice && $sellingPrice > 0) ? ($total / $sellingPrice * 100) : null;

        $fmtMoney = fn($v) => '€ ' . number_format((float) $v, 2, ',', '.');
        $fmtMoney4 = fn($v) => '€ ' . number_format((float) $v, 4, ',', '.');
        $fmtQty = function ($v) {
            $v = (float) $v;
            if (fmod($v, 1.0) == 0.0) return number_format($v, 0, ',', '.');
            return rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
        };

        // Colore del food-cost % (soglie standard ristorazione)
        $fcColor = '#1ab394';
        if ($fcPercent !== null) {
            if ($fcPercent > 40)      $fcColor = '#ed5565';
            elseif ($fcPercent > 30)  $fcColor = '#f8ac59';
            else                       $fcColor = '#1ab394';
        }
    @endphp

    <div class="panel panel-default" style="border-color: #e7eaec;">
        <div class="panel-heading" style="display:flex; align-items:center; justify-content:space-between; padding:10px 15px;">
            <h4 style="margin:0; font-size:14px;">
                <i class="fa fa-calculator" style="color:#1c84c6; margin-right:6px;"></i>
                Costo stimato piatto
            </h4>
            @if($count > 0)
                @if($coverage >= 1)
                    <span class="label label-primary" style="font-size:10px;" title="Costo noto per tutti gli ingredienti">
                        <i class="fa fa-check"></i> Copertura completa
                    </span>
                @elseif($coverage > 0)
                    <span class="label label-warning" style="font-size:10px;" title="Manca il prezzo per alcuni ingredienti">
                        {{ $known }}/{{ $count }} ingredienti
                    </span>
                @else
                    <span class="label label-danger" style="font-size:10px;" title="Nessun prezzo disponibile">
                        Nessun prezzo
                    </span>
                @endif
            @endif
        </div>

        <div class="panel-body" style="padding:0;">

            {{-- Riepilogo grande --}}
            <div style="padding:16px 15px; background:#f8fafc; border-bottom:1px solid #eef0f2;">
                <div style="display:flex; align-items:baseline; justify-content:space-between;">
                    <div>
                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#8a9199;">
                            Costo materia prima
                        </div>
                        <div style="font-size:26px; font-weight:700; color:#2c3e50; line-height:1.1; margin-top:2px;">
                            {{ $fmtMoney($total) }}
                        </div>
                    </div>
                    @if($fcPercent !== null)
                        <div style="text-align:right;">
                            <div style="font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#8a9199;">
                                Food cost
                            </div>
                            <div style="font-size:22px; font-weight:700; color:{{ $fcColor }}; line-height:1.1; margin-top:2px;">
                                {{ number_format($fcPercent, 1, ',', '.') }}%
                            </div>
                        </div>
                    @endif
                </div>

                @if($sellingPrice && $sellingPrice > 0)
                    <div style="margin-top:8px; font-size:12px; color:#8a9199;">
                        Prezzo di vendita: <strong style="color:#333;">{{ $fmtMoney($sellingPrice) }}</strong>
                        · Margine: <strong style="color:{{ ($sellingPrice - $total) >= 0 ? '#1ab394' : '#ed5565' }};">{{ $fmtMoney($sellingPrice - $total) }}</strong>
                    </div>
                @endif
            </div>

            {{-- Dettaglio calcolo --}}
            @if($count === 0)
                <div style="padding:20px 15px; text-align:center; color:#aaa; font-size:12px;">
                    <i class="fa fa-info-circle"></i>
                    Aggiungi degli ingredienti per vedere la stima
                </div>
            @else
                <div style="padding:8px 0;">
                    <div style="padding:6px 15px 8px; font-size:10px; text-transform:uppercase; letter-spacing:.5px; color:#8a9199;">
                        Dettaglio calcolo
                    </div>
                    @foreach($materials as $m)
                        <div style="padding:8px 15px; display:flex; align-items:center; gap:10px; {{ !$loop->last ? 'border-bottom:1px solid #f5f5f5;' : '' }}">
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:600; font-size:12.5px; color:#2c3e50; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                                     title="{{ $m['name'] }}">
                                    {{ $m['name'] }}
                                </div>
                                <div style="font-size:11px; color:#8a9199; margin-top:1px;">
                                    @if($m['has_cost'])
                                        {{ $fmtQty($m['display_qty']) }} {{ $m['display_unit'] }}
                                        &times; {{ $fmtMoney4($m['avg_cost']) }}/{{ $m['unit'] }}
                                    @else
                                        {{ $fmtQty($m['display_qty']) }} {{ $m['display_unit'] }}
                                        <span class="text-danger">
                                            <i class="fa fa-exclamation-triangle"></i> nessun prezzo storico
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div style="flex-shrink:0; text-align:right;">
                                @if($m['has_cost'])
                                    <div style="font-weight:700; font-size:13px; color:#2c3e50;">
                                        {{ $fmtMoney($m['contribution']) }}
                                    </div>
                                @else
                                    <div style="font-weight:600; font-size:12px; color:#ed5565;">
                                        &mdash;
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @if($missing > 0)
                        <div style="padding:10px 15px; background:#fff8e1; border-top:1px solid #ffe082; font-size:11.5px; color:#8a6d3b;">
                            <i class="fa fa-exclamation-triangle"></i>
                            Stima parziale: {{ $missing }} ingredient{{ $missing === 1 ? 'e non ha' : 'i non hanno' }} un prezzo di carico registrato.
                        </div>
                    @endif
                </div>
            @endif

            <div style="padding:8px 15px; background:#fafafa; border-top:1px solid #eef0f2; font-size:10.5px; color:#8a9199; line-height:1.5;">
                <i class="fa fa-info-circle" style="margin-right:3px;"></i>
                Prezzo medio ponderato dei carichi (fatture fornitori + carichi manuali) per unità base del materiale, moltiplicato per la quantità della ricetta.
            </div>
        </div>
    </div>
</div>
