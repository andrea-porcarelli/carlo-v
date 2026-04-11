@php
    $smartUnit = function(float $baseQty, string $baseUnit): array {
        if ($baseUnit === 'kg') {
            if ($baseQty > 0 && $baseQty < 1) {
                return ['qty' => round($baseQty * 1000, 4), 'unit' => 'g'];
            }
            return ['qty' => $baseQty, 'unit' => 'kg'];
        }
        if ($baseUnit === 'cl') {
            if ($baseQty > 0 && $baseQty < 1) {
                return ['qty' => round($baseQty * 10, 4), 'unit' => 'ml'];
            }
            if ($baseQty >= 100) {
                return ['qty' => round($baseQty / 100, 4), 'unit' => 'l'];
            }
            return ['qty' => $baseQty, 'unit' => 'cl'];
        }
        return ['qty' => $baseQty, 'unit' => $baseUnit];
    };
@endphp
@if($dish->materials->isEmpty())
    <span class="text-muted" style="font-size: 12px; font-style: italic;">Nessun ingrediente</span>
@else
    <ul style="list-style: none; padding: 0; margin: 0;">
        @foreach($dish->materials as $ingredient)
            @php
                $baseQty    = (float) $ingredient->pivot->quantity;
                $baseUnit   = $ingredient->stock_type;
                $display    = $smartUnit($baseQty, $baseUnit);
                $dispQty    = $display['qty'];
                $dispUnit   = $display['unit'];
                $currentStock = $materialStocks[$ingredient->id]['current'] ?? null;
                $qtyLabel   = (fmod($dispQty, 1.0) == 0.0)
                    ? number_format($dispQty, 0, '.', '')
                    : rtrim(rtrim(number_format($dispQty, 4, '.', ''), '0'), '.');
                $outOfStock   = $currentStock !== null && $qtyLabel <= 0;
            @endphp
            <li style="display: flex; align-items: center; gap: 5px; padding: 2px 0; {{ !$loop->last ? 'border-bottom: 1px solid #f5f5f5;' : '' }}">

                @if($outOfStock)
                    <span title="Stock esaurito" style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;background:#ed5565;border-radius:50%;flex-shrink:0;">
                        <i class="fa fa-exclamation" style="font-size:9px;color:#fff;"></i>
                    </span>
                @else
                    <span style="display:inline-block;width:6px;height:6px;background:#1ab394;border-radius:50%;flex-shrink:0;margin-left:5px;"></span>
                @endif

                <span style="flex-shrink:0;min-width:54px;text-align:right;">
                    <b style="font-size:12px;color:{{ $outOfStock ? '#ed5565' : '#333' }};">{{ $qtyLabel }}</b>
                    <span style="font-size:11px;color:#999;">{{ $dispUnit }}</span>
                </span>

                <span style="font-size:12px;color:{{ $outOfStock ? '#ed5565' : '#555' }};{{ $outOfStock ? 'font-weight:600;' : '' }}white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px;"
                      title="{{ $ingredient->label }}{{ $outOfStock ? ' — stock esaurito' : '' }}">
                    {{ $ingredient->label }}
                </span>

                @if($outOfStock)
                    <span class="label label-danger" style="font-size:9px;padding:1px 4px;flex-shrink:0;">stock 0</span>
                @endif

            </li>
        @endforeach
    </ul>
@endif
