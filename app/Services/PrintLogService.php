<?php

namespace App\Services;

use App\Models\PrintLog;
use App\Models\Printer;
use App\Models\TableOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PrintLogService
{
    /**
     * Log a print operation
     */
    public function logPrint(
        TableOrder $tableOrder,
        Printer $printer,
        ?int $userId,
        string $printType,
        ?string $operation = null,
        ?array $printData = null,
        bool $success = true,
        ?string $errorMessage = null
    ): PrintLog {
        $printContent = $this->generatePrintContent($tableOrder, $printer, $printType, $operation, $printData);

        return PrintLog::create([
            'table_order_id' => $tableOrder->id,
            'printer_id' => $printer->id,
            'user_id' => $userId,
            'print_type' => $printType,
            'operation' => $operation,
            'print_content' => $printContent,
            'print_data' => $printData,
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Generate HTML content for print preview
     */
    protected function generatePrintContent(
        TableOrder $tableOrder,
        Printer $printer,
        string $printType,
        ?string $operation = null,
        ?array $printData = null
    ): string {
        $tableOrder->load(['items.dish', 'restaurantTable', 'waiter']);

        return match ($printType) {
            'order' => $this->generateOrderContent($tableOrder, $printer, $operation, $printData),
            'marcia' => $this->generateMarciaContent($tableOrder, $printer, $printData),
            'preconto' => $this->generatePrecontoContent($tableOrder, $printer, $printData),
            default => '',
        };
    }

    /**
     * Generate order print content
     */
    protected function generateOrderContent(TableOrder $tableOrder, Printer $printer, ?string $operation, ?array $printData): string
    {
        $items = $printData['items'] ?? $tableOrder->items;
        $operatorName = $printData['operator_name'] ?? 'N/D';
        $tableNumber = $tableOrder->restaurantTable->table_number ?? 'N/D';
        $covers = $tableOrder->covers ?? 0;
        $coversText = $covers == 0 ? 'BEVANDE' : $covers;

        $operationText = match ($operation) {
            'add' => '*** ORDINE ***',
            'update' => '*** MODIFICA ***',
            'remove' => '*** ANNULLAMENTO ***',
            default => '',
        };

        $html = '<div class="print-receipt">';
        $html .= '<div class="print-header">';
        $html .= '<div class="printer-label">*** ' . strtoupper($printer->label) . ' ***</div>';
        $html .= '<div class="table-info">TAVOLO ' . $tableNumber . ' | PAX ' . $coversText . '</div>';
        $html .= '<div class="datetime">' . now()->format('d/m/Y H:i:s') . ' ' . $operatorName . '</div>';
        if ($operationText) {
            $html .= '<div class="operation">' . $operationText . '</div>';
        }
        $html .= '</div>';

        $html .= '<div class="print-separator">------------------------------------------------</div>';

        $html .= '<div class="print-items">';
        foreach ($items as $item) {
            $dishName = is_array($item) ? ($item['dish_name'] ?? 'N/D') : ($item->dish->label ?? 'N/D');
            $quantity = is_array($item) ? ($item['quantity'] ?? 1) : $item->quantity;
            $notes = is_array($item) ? ($item['notes'] ?? null) : $item->notes;
            $extras = is_array($item) ? ($item['extras'] ?? []) : $item->extras;
            $removals = is_array($item) ? ($item['removals'] ?? []) : $item->removals;
            $segue = is_array($item) ? ($item['segue'] ?? false) : ($item->segue ?? false);

            if ($segue) {
                $html .= '<div class="item-segue">*** SEGUE ***</div>';
            }

            $html .= '<div class="item">';
            $html .= '<div class="item-name"><strong>' . $quantity . '   ' . $dishName . '</strong></div>';

            if ($notes) {
                $html .= '<div class="item-notes">  Note: ' . $notes . '</div>';
            }

            if (!empty($extras) && is_array($extras)) {
                foreach ($extras as $extra => $price) {
                    $priceText = $price > 0 ? ' (€' . number_format($price, 2, ',', '.') . ')' : '';
                    $html .= '<div class="item-extra">  + ' . $extra . $priceText . '</div>';
                }
            }

            if (!empty($removals) && is_array($removals)) {
                foreach ($removals as $removal) {
                    $html .= '<div class="item-removal">  - ' . $removal . '</div>';
                }
            }

            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '<div class="print-separator">------------------------------------------------</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate marcia print content
     */
    protected function generateMarciaContent(TableOrder $tableOrder, Printer $printer, ?array $printData): string
    {
        $operatorName = $printData['operator_name'] ?? 'N/D';
        $tableNumber = $tableOrder->restaurantTable->table_number ?? 'N/D';
        $covers = $tableOrder->covers ?? 0;
        $coversText = $covers == 0 ? 'BEVANDE' : $covers;

        $html = '<div class="print-receipt print-marcia">';
        $html .= '<div class="print-header">';
        $html .= '<div class="printer-label">*** ' . strtoupper($printer->label) . ' ***</div>';
        $html .= '</div>';

        $html .= '<div class="marcia-title">MARCIA<br>TAVOLO</div>';

        $html .= '<div class="table-info-large">';
        $html .= '<div>TAVOLO ' . $tableNumber . '</div>';
        $html .= '<div>PAX ' . $coversText . '</div>';
        $html .= '</div>';

        $html .= '<div class="print-footer">';
        $html .= '<div>' . now()->format('d/m/Y H:i:s') . '</div>';
        $html .= '<div>Operatore: ' . $operatorName . '</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate preconto print content
     */
    protected function generatePrecontoContent(TableOrder $tableOrder, Printer $printer, ?array $printData): string
    {
        $operatorName = $printData['operator_name'] ?? 'N/D';
        $splitCount = $printData['split_count'] ?? null;
        $tableNumber = $tableOrder->restaurantTable->table_number ?? 'N/D';
        $covers = $tableOrder->covers ?? 0;
        $coversText = $covers == 0 ? 'BEVANDE' : $covers . ' coperti';

        $html = '<div class="print-receipt print-preconto">';
        $html .= '<div class="print-header">';
        $html .= '<div class="preconto-title">*** PRE-CONTO ***</div>';
        $html .= '<div class="table-info">TAVOLO ' . $tableNumber . '</div>';
        $html .= '<div class="covers-info">' . $coversText . '</div>';
        $html .= '<div class="datetime">' . now()->format('d/m/Y H:i:s') . '</div>';
        $html .= '<div class="operator">Operatore: ' . $operatorName . '</div>';
        $html .= '</div>';

        $html .= '<div class="print-separator">------------------------------------------------</div>';

        $html .= '<div class="print-items">';
        foreach ($tableOrder->items as $item) {
            $dishName = $item->dish->label ?? 'N/D';
            $quantity = $item->quantity;
            $subtotal = number_format($item->subtotal, 2, ',', '.');

            $html .= '<div class="item preconto-item">';
            $html .= '<span class="item-desc">' . $quantity . ' x ' . $dishName . '</span>';
            $html .= '<span class="item-price">' . $subtotal . '</span>';
            $html .= '</div>';

            if (!empty($item->notes)) {
                $html .= '<div class="item-notes">  Note: ' . $item->notes . '</div>';
            }

            if (!empty($item->extras) && is_array($item->extras)) {
                foreach ($item->extras as $extra => $price) {
                    $html .= '<div class="item-extra">  + ' . $extra . ' (+' . number_format($price, 2, ',', '.') . ')</div>';
                }
            }
        }

        // Coperto
        if ($tableOrder->hasCoverCharge()) {
            $coverChargeTotal = number_format($tableOrder->getCoverChargeAmount(), 2, ',', '.');
            $coverChargePerPerson = number_format($tableOrder->getCoverChargePerPerson(), 2, ',', '.');
            $html .= '<div class="item preconto-item">';
            $html .= '<span class="item-desc">Coperto (' . $covers . ' x ' . $coverChargePerPerson . ')</span>';
            $html .= '<span class="item-price">' . $coverChargeTotal . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>';

        $html .= '<div class="print-separator">------------------------------------------------</div>';

        // Totale
        $totalAmount = number_format($tableOrder->total_amount, 2, ',', '.');
        $html .= '<div class="print-total">';
        $html .= '<strong>TOTALE: EUR ' . $totalAmount . '</strong>';
        $html .= '</div>';

        // Suddivisione
        if ($splitCount && $splitCount > 1) {
            $perPerson = $tableOrder->total_amount / $splitCount;
            $perPersonFormatted = number_format($perPerson, 2, ',', '.');

            $html .= '<div class="print-split">';
            $html .= '<div class="split-separator">===========================================</div>';
            $html .= '<div class="split-title">DIVISO PER ' . $splitCount . ' PERSONE</div>';
            $html .= '<div class="split-amount">EUR ' . $perPersonFormatted . ' a persona</div>';
            $html .= '<div class="split-separator">===========================================</div>';
            $html .= '</div>';
        }

        $html .= '<div class="print-footer-note">*** NON FISCALE ***</div>';

        $html .= '</div>';

        return $html;
    }
}
