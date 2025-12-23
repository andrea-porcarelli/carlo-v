<?php

namespace App\Services;

use App\Interfaces\PrinterServiceInterface;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\TableOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer as EscposPrinter;

class PrinterService implements PrinterServiceInterface
{
    /**
     * Stampa gli articoli su una stampante POS
     *
     * @param TableOrder $tableOrder L'ordine del tavolo
     * @param Collection|array $items Array di OrderItem da stampare
     * @param string|null $operation Tipo di operazione: 'add', 'update', 'remove'
     * @return bool True se la stampa è andata a buon fine, false altrimenti
     */
    public function printOrderItems(TableOrder $tableOrder, Collection|array $items, ?string $operation = 'add'): bool
    {
        try {
            // Converti in Collection se necessario
            $items = $items instanceof Collection ? $items : collect($items);

            // Se non ci sono articoli, non fare nulla
            if ($items->isEmpty()) {
                return true;
            }

            // Carica le relazioni necessarie
            $tableOrder->load('restaurantTable');

            // Raggruppa gli articoli per stampante
            $itemsByPrinter = $this->groupItemsByPrinter($items);

            $allSuccess = true;

            // Per ogni stampante, stampa gli articoli corrispondenti
            foreach ($itemsByPrinter as $printerData) {
                $success = $this->printToDevice($tableOrder, $printerData['printer'], $printerData['items'], $operation);
                if (!$success) {
                    $allSuccess = false;
                }
            }

            return $allSuccess;

        } catch (\Exception $e) {
            Log::error('Errore durante la stampa degli articoli', [
                'table_order_id' => $tableOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Stampa su un dispositivo specifico
     *
     * @param TableOrder $tableOrder
     * @param Printer $printerObj
     * @param array $items
     * @param string|null $operation
     * @return bool
     */
    protected function printToDevice(TableOrder $tableOrder, Printer $printerObj, array $items, ?string $operation = 'add'): bool
    {
        try {
            $printerIp = $printerObj->ip;

            // Verifica se la stampante è raggiungibile
            if (!$this->isPrinterReachable($printerIp)) {
                Log::warning("Stampante non raggiungibile", ['ip' => $printerIp]);
                return false;
            }

            // Connessione alla stampante (porta standard 9100 per stampanti di rete)
            $connector = new NetworkPrintConnector($printerIp, 9100, 5); // 5 secondi di timeout
            $printer = new EscposPrinter($connector);

            // Inizializza la stampante
            $printer->initialize();

            // Label della stampante
            $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text("*** " . strtoupper($printerObj->label) . " ***\n");
            $printer->setEmphasis(false);
            $printer->feed(1);

            $covers = $tableOrder->covers ?? $tableOrder->restaurantTable->capacity ?? 'N/D';
            // Numero del tavolo (centrato)
            $printer->setEmphasis(true);
            $printer->setTextSize(2, 2);
            $tableNumber = $tableOrder->restaurantTable->table_number ?? 'N/D';
            $printer->text("TAVOLO $tableNumber | PAX $covers\n\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            $printer->setJustification(EscposPrinter::JUSTIFY_LEFT);
            $operatorName = 'N/D';
            if (count($items) > 0 && isset($items[0]->addedBy)) {
                $operatorName = $items[0]->addedBy->name ?? 'N/D';
            }

            // Data e ora
            $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text(now()->format('d/m/Y H:i:s')  . " " . $operatorName . "\n");
            $printer->setEmphasis(false);
            $printer->setJustification(EscposPrinter::JUSTIFY_LEFT);


            $printer->feed(1);

            // Tipo di operazione
            $operationText = $this->getOperationText($operation);
            if ($operationText) {
                $printer->setEmphasis(true);
                $printer->text("$operationText\n");
                $printer->setEmphasis(false);
                $printer->feed(1);
            }

            // Linea separatrice
            $printer->text(str_repeat('-', 48) . "\n");

            // Stampa gli articoli
            foreach ($items as $item) {
                $this->printItem($printer, $item);
            }

            // Linea separatrice finale
            $printer->text(str_repeat('-', 48) . "\n");
            $printer->feed(1);

            // Stampa articoli delle altre stampanti in piccolo
            $this->printOtherPrintersItems($printer, $printerObj, $tableOrder);

            $printer->feed(2);

            // Taglia la carta (se supportato)
            $printer->cut();

            // Chiudi la connessione
            $printer->close();

            Log::info("Stampa completata con successo", [
                'table_order_id' => $tableOrder->id,
                'table_number' => $tableNumber,
                'printer_ip' => $printerIp,
                'printer_label' => $printerObj->label,
                'items_count' => count($items)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Errore durante la stampa su dispositivo', [
                'table_order_id' => $tableOrder->id,
                'printer_ip' => $printerObj->ip ?? 'N/D',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Stampa un singolo articolo
     *
     * @param EscposPrinter $printer
     * @param OrderItem $item
     * @return void
     */
    protected function printItem(EscposPrinter $printer, OrderItem $item): void
    {
        // Quantità e nome piatto
        $printer->setEmphasis(true);
        $printer->setTextSize(2, 1);
        $quantity = str_pad($item->quantity, 3, ' ', STR_PAD_RIGHT);
        $dishName = $item->dish->label ?? 'N/D';
        $printer->text("$quantity $dishName\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);

        // Note
        if (!empty($item->notes)) {
            $printer->text("  Note: " . $item->notes . "\n");
        }

        // Aggiunte (extras)
        if (!empty($item->extras) && is_array($item->extras)) {
            foreach ($item->extras as $extra => $price) {
                $printer->text("  + $extra");
                if ($price > 0) {
                    $printer->text(" (€" . number_format($price, 2, ',', '.') . ")");
                }
                $printer->text("\n");
            }
        }

        // Rimozioni
        if (!empty($item->removals) && is_array($item->removals)) {
            foreach ($item->removals as $removal) {
                $printer->text("  - $removal\n");
            }
        }

        $printer->feed(1);
    }

    /**
     * Stampa gli articoli delle altre stampanti in formato ridotto
     *
     * @param EscposPrinter $printer
     * @param Printer $currentPrinter
     * @param TableOrder $tableOrder
     * @return void
     */
    protected function printOtherPrintersItems(EscposPrinter $printer, Printer $currentPrinter, TableOrder $tableOrder): void
    {
        // Recupera TUTTI gli articoli del tavolo
        $allItems = $tableOrder->items()->with('dish.category.printer')->get();

        Log::info('printOtherPrintersItems chiamato', [
            'current_printer_id' => $currentPrinter->id,
            'table_order_id' => $tableOrder->id,
            'total_items' => $allItems->count()
        ]);

        // Raggruppa tutti gli articoli del tavolo per stampante
        $allItemsByPrinter = $this->groupItemsByPrinter($allItems);

        // Filtra gli articoli delle altre stampanti (esclusa quella corrente)
        $otherPrinters = array_filter($allItemsByPrinter, function($printerData) use ($currentPrinter) {
            return $printerData['printer']->id !== $currentPrinter->id;
        });

        Log::info('Altre stampanti filtrate', [
            'other_printers_count' => count($otherPrinters)
        ]);

        // Se non ci sono altre stampanti, esci
        if (empty($otherPrinters)) {
            return;
        }

        // Intestazione sezione altre stampanti
        $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
        $printer->setTextSize(1, 1);
        $printer->text("--- Altre Preparazioni ---\n");
        $printer->setJustification(EscposPrinter::JUSTIFY_LEFT);
        $printer->feed(1);

        // Per ogni altra stampante
        foreach ($otherPrinters as $printerData) {
            $otherPrinter = $printerData['printer'];
            $otherItems = $printerData['items'];

            // Nome della stampante
            $printer->setEmphasis(true);
            $printer->text(strtoupper($otherPrinter->label) . ":\n");
            $printer->setEmphasis(false);

            // Articoli in formato ridotto
            foreach ($otherItems as $item) {
                $dishName = $item->dish->label ?? 'N/D';
                $quantity = $item->quantity;
                $printer->text("  $quantity x $dishName\n");

                // Note solo se presenti
                if (!empty($item->notes)) {
                    $printer->text("    N: " . $item->notes . "\n");
                }
            }

            $printer->feed(1);
        }
    }

    /**
     * Ottieni il testo dell'operazione
     *
     * @param string|null $operation
     * @return string|null
     */
    protected function getOperationText(?string $operation): ?string
    {
        return match($operation) {
            'add' => '*** ORDINE ***',
            'update' => '*** MODIFICA ***',
            'remove' => '*** ANNULLAMENTO ***',
            default => null,
        };
    }

    /**
     * Verifica se una stampante è raggiungibile
     *
     * @param string $printerIp Indirizzo IP della stampante
     * @return bool True se la stampante è raggiungibile
     */
    public function isPrinterReachable(string $printerIp): bool
    {
        // Prova a connettersi alla stampante con un timeout breve
        $fp = @fsockopen($printerIp, 9100, $errno, $errstr, 2);

        if ($fp) {
            fclose($fp);
            return true;
        }

        return false;
    }

    /**
     * Raggruppa gli articoli per stampante in base alla categoria
     *
     * @param Collection|array $items Array di OrderItem
     * @return array Array di array con struttura ['printer' => Printer, 'items' => [OrderItem, ...]]
     */
    public function groupItemsByPrinter(Collection|array $items): array
    {
        $items = $items instanceof Collection ? $items : collect($items);
        $grouped = [];

        foreach ($items as $item) {
            // Carica le relazioni necessarie se non già caricate
            if (!$item->relationLoaded('dish')) {
                $item->load('dish.category.printer', 'addedBy');
            } elseif (!$item->dish->relationLoaded('category')) {
                $item->dish->load('category.printer');
                $item->load('addedBy');
            } elseif (!$item->dish->category->relationLoaded('printer')) {
                $item->dish->category->load('printer');
                $item->load('addedBy');
            } else {
                $item->load('addedBy');
            }

            // Ottieni la stampante dalla categoria del piatto
            $printer = $item->dish->category->printer ?? null;

            if ($printer && $printer->is_active && !empty($printer->ip)) {
                $printerId = $printer->id;

                if (!isset($grouped[$printerId])) {
                    $grouped[$printerId] = [
                        'printer' => $printer,
                        'items' => []
                    ];
                }

                $grouped[$printerId]['items'][] = $item;
            } else {
                Log::warning('Articolo senza stampante configurata', [
                    'item_id' => $item->id,
                    'dish_id' => $item->dish_id,
                    'category_id' => $item->dish->category_id ?? null
                ]);
            }
        }

        return array_values($grouped);
    }
}
