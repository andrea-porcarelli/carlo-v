<?php

namespace App\Services;

use App\Interfaces\PrinterServiceInterface;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\Setting;
use App\Models\TableOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer as EscposPrinter;

class PrinterService implements PrinterServiceInterface
{
    protected PrintLogService $printLogService;
    protected ?int $currentOperatorId = null;

    public function __construct(PrintLogService $printLogService)
    {
        $this->printLogService = $printLogService;
    }

    /**
     * Set the current operator ID for logging
     */
    public function setOperatorId(int $operatorId): self
    {
        $this->currentOperatorId = $operatorId;
        return $this;
    }
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

                // Log the failed print (always)
                $this->printLogService->logPrint(
                    $tableOrder,
                    $printerObj,
                    $this->currentOperatorId,
                    'order',
                    $operation,
                    [
                        'items' => collect($items)->map(fn($item) => [
                            'dish_name' => $item->dish->label ?? 'N/D',
                            'quantity' => $item->quantity,
                            'notes' => $item->notes,
                            'extras' => $item->extras,
                            'removals' => $item->removals,
                            'segue' => $item->segue ?? false,
                        ])->toArray(),
                        'operator_name' => $this->currentOperatorId ? (User::find($this->currentOperatorId)?->name ?? 'N/D') : 'N/D',
                    ],
                    false,
                    'Stampante non raggiungibile: ' . $printerIp
                );

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

            // Log the print (always)
            $this->printLogService->logPrint(
                $tableOrder,
                $printerObj,
                $this->currentOperatorId,
                'order',
                $operation,
                [
                    'items' => collect($items)->map(fn($item) => [
                        'dish_name' => $item->dish->label ?? 'N/D',
                        'quantity' => $item->quantity,
                        'notes' => $item->notes,
                        'extras' => $item->extras,
                        'removals' => $item->removals,
                        'segue' => $item->segue ?? false,
                    ])->toArray(),
                    'operator_name' => $this->currentOperatorId ? (User::find($this->currentOperatorId)?->name ?? 'N/D') : 'N/D',
                ],
                true
            );

            return true;

        } catch (\Exception $e) {
            Log::error('Errore durante la stampa su dispositivo', [
                'table_order_id' => $tableOrder->id,
                'printer_ip' => $printerObj->ip ?? 'N/D',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Log the failed print (always)
            $this->printLogService->logPrint(
                $tableOrder,
                $printerObj,
                $this->currentOperatorId,
                'order',
                $operation,
                [
                    'items' => collect($items)->map(fn($item) => [
                        'dish_name' => $item->dish->label ?? 'N/D',
                        'quantity' => $item->quantity,
                        'notes' => $item->notes,
                        'extras' => $item->extras,
                        'removals' => $item->removals,
                        'segue' => $item->segue ?? false,
                    ])->toArray(),
                    'operator_name' => $this->currentOperatorId ? (User::find($this->currentOperatorId)?->name ?? 'N/D') : 'N/D',
                ],
                false,
                $e->getMessage()
            );

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
        // Se l'articolo ha il flag "segue", stampa l'intestazione SEGUE
        if ($item->segue) {
            $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text("*** SEGUE ***\n");
            $printer->setEmphasis(false);
            $printer->setJustification(EscposPrinter::JUSTIFY_LEFT);
        }

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

    /**
     * Stampa "MARCIA TAVOLO" su tutte le stampanti coinvolte dall'ordine
     *
     * @param TableOrder $tableOrder L'ordine del tavolo
     * @param int $operatorId ID dell'operatore
     * @return bool True se la stampa è andata a buon fine, false altrimenti
     */
    public function printMarciaTavolo(TableOrder $tableOrder, int $operatorId): bool
    {
        try {
            // Carica le relazioni necessarie
            $tableOrder->load(['items.dish.category.printer', 'restaurantTable']);

            // Se non ci sono articoli, non fare nulla
            if ($tableOrder->items->isEmpty()) {
                return true;
            }

            // Raggruppa gli articoli per stampante
            $itemsByPrinter = $this->groupItemsByPrinter($tableOrder->items);

            $allSuccess = true;

            // Per ogni stampante, stampa la marcia
            foreach ($itemsByPrinter as $printerData) {
                $success = $this->printMarciaToDevice($tableOrder, $printerData['printer'], $operatorId);
                if (!$success) {
                    $allSuccess = false;
                }
            }

            return $allSuccess;

        } catch (\Exception $e) {
            Log::error('Errore durante la stampa marcia tavolo', [
                'table_order_id' => $tableOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Stampa la marcia tavolo su un dispositivo specifico
     *
     * @param TableOrder $tableOrder
     * @param Printer $printerObj
     * @param int $operatorId
     * @return bool
     */
    protected function printMarciaToDevice(TableOrder $tableOrder, Printer $printerObj, int $operatorId): bool
    {
        try {
            $printerIp = $printerObj->ip;

            // Verifica se la stampante è raggiungibile
            if (!$this->isPrinterReachable($printerIp)) {
                Log::warning("Stampante non raggiungibile per marcia", ['ip' => $printerIp]);

                // Log the failed print (always)
                $operator = User::find($operatorId);
                $this->printLogService->logPrint(
                    $tableOrder,
                    $printerObj,
                    $operatorId,
                    'marcia',
                    null,
                    ['operator_name' => $operator?->name ?? 'N/D'],
                    false,
                    'Stampante non raggiungibile: ' . $printerIp
                );

                return false;
            }

            // Connessione alla stampante
            $connector = new NetworkPrintConnector($printerIp, 9100, 5);
            $printer = new EscposPrinter($connector);

            // Inizializza la stampante
            $printer->initialize();

            // Label della stampante
            $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text("*** " . strtoupper($printerObj->label) . " ***\n");
            $printer->setEmphasis(false);
            $printer->feed(1);

            // MARCIA TAVOLO in grande
            $printer->setTextSize(3, 3);
            $printer->setEmphasis(true);
            $printer->text("MARCIA\n");
            $printer->text("TAVOLO\n");
            $printer->setTextSize(1, 1);
            $printer->feed(1);

            // Numero del tavolo
            $covers = $tableOrder->covers ?? 'N/D';
            $coversText = $covers == 0 ? 'BEVANDE' : $covers;
            $printer->setTextSize(2, 2);
            $tableNumber = $tableOrder->restaurantTable->table_number ?? 'N/D';
            $printer->text("TAVOLO $tableNumber\n");
            $printer->text("PAX $coversText\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            $printer->feed(1);

            // Data e ora e operatore
            $operator = \App\Models\User::find($operatorId);
            $operatorName = $operator->name ?? 'N/D';
            $printer->text(now()->format('d/m/Y H:i:s') . "\n");
            $printer->text("Operatore: " . $operatorName . "\n");

            $printer->feed(2);

            // Taglia la carta
            $printer->cut();

            // Chiudi la connessione
            $printer->close();

            Log::info("Marcia tavolo stampata con successo", [
                'table_order_id' => $tableOrder->id,
                'table_number' => $tableNumber,
                'printer_ip' => $printerIp,
                'printer_label' => $printerObj->label
            ]);

            // Log the print
            $this->printLogService->logPrint(
                $tableOrder,
                $printerObj,
                $operatorId,
                'marcia',
                null,
                ['operator_name' => $operatorName],
                true
            );

            return true;

        } catch (\Exception $e) {
            Log::error('Errore durante la stampa marcia su dispositivo', [
                'table_order_id' => $tableOrder->id,
                'printer_ip' => $printerObj->ip ?? 'N/D',
                'error' => $e->getMessage()
            ]);

            // Log the failed print
            $operator = User::find($operatorId);
            $this->printLogService->logPrint(
                $tableOrder,
                $printerObj,
                $operatorId,
                'marcia',
                null,
                ['operator_name' => $operator?->name ?? 'N/D'],
                false,
                $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Stampa il PreConto sulla stampante di default
     *
     * @param TableOrder $tableOrder L'ordine del tavolo
     * @param int $operatorId ID dell'operatore
     * @param int|null $splitCount Numero di persone per dividere il conto (opzionale)
     * @return bool True se la stampa è andata a buon fine, false altrimenti
     */
    public function printPreconto(TableOrder $tableOrder, int $operatorId, ?int $splitCount = null): bool
    {
        try {
            // Get preconto printer from settings
            $printer = Setting::getPrecontoPrinter();

            if (!$printer) {
                Log::error('Stampante PreConto non configurata');
                return false;
            }

            if (!$printer->is_active || empty($printer->ip)) {
                Log::error('Stampante PreConto non attiva o senza IP', ['printer_id' => $printer->id]);
                return false;
            }

            // Carica le relazioni necessarie
            $tableOrder->load(['items.dish', 'restaurantTable']);

            return $this->printPrecontoToDevice($tableOrder, $printer, $operatorId, $splitCount);

        } catch (\Exception $e) {
            Log::error('Errore durante la stampa preconto', [
                'table_order_id' => $tableOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Stampa il PreConto su un dispositivo specifico
     *
     * @param TableOrder $tableOrder
     * @param Printer $printerObj
     * @param int $operatorId
     * @param int|null $splitCount
     * @return bool
     */
    protected function printPrecontoToDevice(TableOrder $tableOrder, Printer $printerObj, int $operatorId, ?int $splitCount = null): bool
    {
        try {
            $printerIp = $printerObj->ip;

            // Verifica se la stampante è raggiungibile
            if (!$this->isPrinterReachable($printerIp)) {
                Log::warning("Stampante PreConto non raggiungibile", ['ip' => $printerIp]);

                // Log the failed print (always)
                $operator = User::find($operatorId);
                $this->printLogService->logPrint(
                    $tableOrder,
                    $printerObj,
                    $operatorId,
                    'preconto',
                    null,
                    [
                        'split_count' => $splitCount,
                        'operator_name' => $operator?->name ?? 'N/D',
                    ],
                    false,
                    'Stampante non raggiungibile: ' . $printerIp
                );

                return false;
            }

            // Connessione alla stampante
            $connector = new NetworkPrintConnector($printerIp, 9100, 5);
            $printer = new EscposPrinter($connector);

            // Inizializza la stampante
            $printer->initialize();

            // Intestazione
            $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->setTextSize(2, 2);


            // Numero del tavolo
            $covers = $tableOrder->covers ?? 0;
            $coversText = $covers == 0 ? 'BEVANDE' : $covers . ' coperti';
            $printer->setEmphasis(true);
            $printer->setTextSize(2, 1);
            $tableNumber = $tableOrder->restaurantTable->table_number ?? 'N/D';
            $printer->text("TAVOLO $tableNumber\n");
            $printer->setTextSize(1, 1);
            $printer->text("$coversText\n");
            $printer->setEmphasis(false);
            $printer->feed(1);

            // Data e ora e operatore
            $operator = User::find($operatorId);
            $operatorName = $operator->name ?? 'N/D';
            $printer->text(now()->format('d/m/Y H:i:s') . "\n");
            $printer->text("Operatore: " . $operatorName . "\n");
            $printer->feed(1);

            // Linea separatrice
            $printer->text(str_repeat('-', 48) . "\n");


            $printer->setEmphasis(true);
            // Coperto (se applicabile)
            if ($tableOrder->hasCoverCharge()) {
                $coverChargeTotal = number_format($tableOrder->getCoverChargeAmount(), 2, ',', '.');
                $coverChargePerPerson = number_format($tableOrder->getCoverChargePerPerson(), 2, ',', '.');
                $printer->text(str_pad("Coperto ($covers x $coverChargePerPerson)", 38) . str_pad($coverChargeTotal, 10, ' ', STR_PAD_LEFT) . "\n");
            }
            // Stampa gli articoli
            $printer->setJustification(EscposPrinter::JUSTIFY_LEFT);
            foreach ($tableOrder->items as $item) {
                $dishName = $item->dish->label ?? 'N/D';
                $quantity = $item->quantity;
                $subtotal = number_format($item->subtotal, 2, ',', '.');

                // Nome e prezzo
                $line = str_pad("$quantity x $dishName", 38) . str_pad("$subtotal", 10, ' ', STR_PAD_LEFT);
                $printer->text($line . "\n");
                $printer->setEmphasis(false);

                // Note
                if (!empty($item->notes)) {
                    $printer->text("  Note: " . $item->notes . "\n");
                }

                // Extras
                if (!empty($item->extras) && is_array($item->extras)) {
                    foreach ($item->extras as $extra => $price) {
                        $extraPrice = number_format($price, 2, ',', '.');
                        $printer->text("  + $extra (+$extraPrice)\n");
                    }
                }

                // Rimozioni
                if (!empty($item->removals) && is_array($item->removals)) {
                    foreach ($item->removals as $removal) {
                        $printer->text("  - $removal\n");
                    }
                }
            }

            // Linea separatrice
            $printer->text(str_repeat('-', 48) . "\n");

            // Totale
            $printer->setJustification(EscposPrinter::JUSTIFY_RIGHT);
            $printer->setEmphasis(true);
            $printer->setTextSize(2, 1);
            $totalAmount = number_format($tableOrder->total_amount, 2, ',', '.');
            $printer->text("TOTALE: EUR $totalAmount\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            $printer->feed(1);

            // Se richiesta la suddivisione
            if ($splitCount && $splitCount > 1) {
                $perPerson = $tableOrder->total_amount / $splitCount;
                $perPersonFormatted = number_format($perPerson, 2, ',', '.');
                $printer->text(str_repeat('-', 48) . "\n");
                $printer->setEmphasis(true);
                $printer->setTextSize(1, 1);
                $printer->text("DIVISO PER $splitCount PERSONE\n");
                $printer->setTextSize(1, 1);
                $printer->text("EUR $perPersonFormatted a persona\n");
                $printer->setEmphasis(false);
                $printer->text(str_repeat('=', 48) . "\n");
            }

            $printer->feed(1);

            // Nota finale
            $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $printer->text("Misuraca S.R.L. \n");
            $printer->text("*** DOCUMENTO NON FISCALE ***\n");

            $printer->feed(2);

            // Taglia la carta
            $printer->cut();

            // Chiudi la connessione
            $printer->close();

            Log::info("PreConto stampato con successo", [
                'table_order_id' => $tableOrder->id,
                'table_number' => $tableNumber,
                'printer_ip' => $printerIp,
                'split_count' => $splitCount
            ]);

            // Log the print
            $this->printLogService->logPrint(
                $tableOrder,
                $printerObj,
                $operatorId,
                'preconto',
                null,
                [
                    'split_count' => $splitCount,
                    'operator_name' => $operatorName,
                ],
                true
            );

            return true;

        } catch (\Exception $e) {
            Log::error('Errore durante la stampa preconto su dispositivo', [
                'table_order_id' => $tableOrder->id,
                'printer_ip' => $printerObj->ip ?? 'N/D',
                'error' => $e->getMessage()
            ]);

            // Log the failed print
            $operator = User::find($operatorId);
            $this->printLogService->logPrint(
                $tableOrder,
                $printerObj,
                $operatorId,
                'preconto',
                null,
                [
                    'split_count' => $splitCount,
                    'operator_name' => $operator?->name ?? 'N/D',
                ],
                false,
                $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Stampa una comunicazione su una stampante specifica
     *
     * @param Printer $printer La stampante su cui stampare
     * @param string $message Il messaggio da stampare
     * @param int $operatorId ID dell'operatore
     * @param TableOrder|null $tableOrder Ordine del tavolo (opzionale)
     * @return bool True se la stampa è andata a buon fine, false altrimenti
     */
    public function printComunica(Printer $printer, string $message, int $operatorId, ?TableOrder $tableOrder = null): bool
    {
        try {
            $printerIp = $printer->ip;

            // Verifica se la stampante è raggiungibile
            if (!$this->isPrinterReachable($printerIp)) {
                Log::warning("Stampante non raggiungibile per comunicazione", ['ip' => $printerIp]);

                // Log the failed print
                $operator = User::find($operatorId);
                $this->printLogService->logPrint(
                    $tableOrder,
                    $printer,
                    $operatorId,
                    'comunica',
                    null,
                    [
                        'message' => $message,
                        'operator_name' => $operator?->name ?? 'N/D',
                    ],
                    false,
                    'Stampante non raggiungibile: ' . $printerIp
                );

                return false;
            }

            // Connessione alla stampante
            $connector = new NetworkPrintConnector($printerIp, 9100, 5);
            $escposPrinter = new EscposPrinter($connector);

            // Inizializza la stampante
            $escposPrinter->initialize();

            // Intestazione
            $escposPrinter->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $escposPrinter->setEmphasis(true);
            $escposPrinter->setTextSize(2, 2);
            $escposPrinter->text("COMUNICAZIONE\n");
            $escposPrinter->setTextSize(1, 1);
            $escposPrinter->setEmphasis(false);
            $escposPrinter->feed(1);

            // Se c'è un tavolo associato, mostralo
            if ($tableOrder) {
                $tableOrder->load('restaurantTable');
                $tableNumber = $tableOrder->restaurantTable->table_number ?? 'N/D';
                $escposPrinter->setEmphasis(true);
                $escposPrinter->setTextSize(2, 1);
                $escposPrinter->text("TAVOLO $tableNumber\n");
                $escposPrinter->setTextSize(1, 1);
                $escposPrinter->setEmphasis(false);
                $escposPrinter->feed(1);
            }

            // Data e ora e operatore
            $operator = User::find($operatorId);
            $operatorName = $operator->name ?? 'N/D';
            $escposPrinter->text(now()->format('d/m/Y H:i:s') . "\n");
            $escposPrinter->text("Da: " . $operatorName . "\n");
            $escposPrinter->feed(1);

            // Linea separatrice
            $escposPrinter->text(str_repeat('-', 48) . "\n");
            $escposPrinter->feed(1);

            // Messaggio
            $escposPrinter->setJustification(EscposPrinter::JUSTIFY_LEFT);
            $escposPrinter->setEmphasis(true);
            $escposPrinter->setTextSize(2, 1);

            // Dividi il messaggio in righe per la larghezza della stampante
            $wrappedMessage = wordwrap($message, 24, "\n", true);
            $escposPrinter->text($wrappedMessage . "\n");

            $escposPrinter->setTextSize(1, 1);
            $escposPrinter->setEmphasis(false);
            $escposPrinter->feed(1);

            // Linea separatrice finale
            $escposPrinter->text(str_repeat('-', 48) . "\n");

            $escposPrinter->feed(3);

            // Taglia la carta
            $escposPrinter->cut();

            // Chiudi la connessione
            $escposPrinter->close();

            Log::info("Comunicazione stampata con successo", [
                'printer_ip' => $printerIp,
                'printer_label' => $printer->label,
                'message' => $message,
                'table_order_id' => $tableOrder?->id
            ]);

            // Log the print
            $this->printLogService->logPrint(
                $tableOrder,
                $printer,
                $operatorId,
                'comunica',
                null,
                [
                    'message' => $message,
                    'operator_name' => $operatorName,
                ],
                true
            );

            return true;

        } catch (\Exception $e) {
            Log::error('Errore durante la stampa comunicazione', [
                'printer_ip' => $printer->ip ?? 'N/D',
                'error' => $e->getMessage()
            ]);

            // Log the failed print
            $operator = User::find($operatorId);
            $this->printLogService->logPrint(
                $tableOrder,
                $printer,
                $operatorId,
                'comunica',
                null,
                [
                    'message' => $message,
                    'operator_name' => $operator?->name ?? 'N/D',
                ],
                false,
                $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Reprint a logged print job (used from backoffice)
     */
    public function reprint(int $printLogId): bool
    {
        $printLog = \App\Models\PrintLog::with(['tableOrder.restaurantTable', 'tableOrder.items.dish', 'printer'])->find($printLogId);

        if (!$printLog || !$printLog->printer || !$printLog->tableOrder) {
            Log::error('PrintLog non trovato o incompleto', ['id' => $printLogId]);
            return false;
        }

        $tableOrder = $printLog->tableOrder;
        $printer = $printLog->printer;

        // Set operator for logging
        $this->currentOperatorId = $printLog->user_id;

        return match ($printLog->print_type) {
            'order' => $this->printToDevice($tableOrder, $printer, $tableOrder->items->toArray(), $printLog->operation),
            'marcia' => $this->printMarciaToDevice($tableOrder, $printer, $printLog->user_id),
            'preconto' => $this->printPrecontoToDevice($tableOrder, $printer, $printLog->user_id, $printLog->print_data['split_count'] ?? null),
            default => false,
        };
    }

    /**
     * Stampa lo storico operazioni su una stampante POS 80mm
     *
     * @param TableOrder $tableOrder L'ordine del tavolo
     * @param Printer $printerObj Stampante su cui stampare
     * @param Collection $logs Collection di TableOrderLog
     * @param int|null $operatorId ID dell'operatore
     * @return bool True se la stampa è andata a buon fine, false altrimenti
     */
    public function printHistory(TableOrder $tableOrder, Printer $printerObj, \Illuminate\Support\Collection $logs, ?int $operatorId = null): bool
    {
        try {
            $printerIp = $printerObj->ip;

            // Verifica se la stampante è raggiungibile
            if (!$this->isPrinterReachable($printerIp)) {
                Log::warning("Stampante non raggiungibile per storico", ['ip' => $printerIp]);
                return false;
            }

            // Connessione alla stampante
            $connector = new NetworkPrintConnector($printerIp, 9100, 5);
            $printer = new EscposPrinter($connector);

            // Inizializza la stampante
            $printer->initialize();

            // Dati ordine
            $tableNumber = $tableOrder->restaurantTable->table_number ?? 'N/D';

            // --- INTESTAZIONE ---
            $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->setTextSize(2, 2);
            $printer->text("STORICO\n");
            $printer->text("TAVOLO $tableNumber\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            $printer->feed(1);

            // Data stampa
            $printer->text(now()->format('d/m/Y H:i:s') . "\n");
            $printer->text("Operazioni: " . $logs->count() . "\n");
            $printer->feed(1);

            // Linea separatrice
            $printer->text(str_repeat('=', 48) . "\n");
            $printer->feed(1);

            // --- ELENCO OPERAZIONI ---
            $printer->setJustification(EscposPrinter::JUSTIFY_LEFT);

            foreach ($logs as $log) {
                // Data/ora
                $printer->setEmphasis(true);
                $printer->text($log->created_at->format('d/m H:i:s'));
                $printer->setEmphasis(false);

                // Operatore
                $operatorName = $log->user?->name ?? 'Sistema';
                $printer->text(" - " . $operatorName . "\n");

                // Azione con icona testuale
                $actionLabel = $this->getActionTextLabel($log->action);
                $printer->text("  " . $actionLabel . "\n");

                // Dettagli prodotto (se presente)
                if ($log->orderItem && $log->orderItem->dish) {
                    $dishName = $log->orderItem->dish->label ?? $log->orderItem->dish->name ?? 'N/D';
                    $printer->text("  >> " . $dishName);

                    // Quantità se disponibile
                    if ($log->data_after && isset($log->data_after['quantity'])) {
                        $printer->text(" x" . $log->data_after['quantity']);
                    }

                    // Prezzo modificato
                    if ($log->data_after && isset($log->data_after['price_modified']) && $log->data_after['price_modified']) {
                        $unitPrice = $log->data_after['unit_price'] ?? 0;
                        $dishPrice = $log->data_after['dish_price'] ?? 0;
                        $printer->text("\n     PREZZO MOD: " . number_format($unitPrice, 2) . " (era " . number_format($dishPrice, 2) . ")");
                    }

                    $printer->text("\n");
                }

                // Note aggiuntive (se presenti)
                if ($log->notes && !str_contains($log->notes, 'Aggiunto') && !str_contains($log->notes, 'Rimosso') && !str_contains($log->notes, 'Modificat')) {
                    $printer->text("  " . substr($log->notes, 0, 45) . "\n");
                }

                // Linea separatrice sottile
                $printer->text(str_repeat('-', 48) . "\n");
            }

            $printer->feed(1);

            // --- RIEPILOGO ---
            $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $printer->text(str_repeat('=', 48) . "\n");
            $printer->setEmphasis(true);
            $printer->text("FINE STORICO\n");
            $printer->setEmphasis(false);

            $printer->feed(3);

            // Taglia la carta
            $printer->cut();

            // Chiudi la connessione
            $printer->close();

            Log::info("Storico stampato con successo", [
                'table_order_id' => $tableOrder->id,
                'table_number' => $tableNumber,
                'printer_ip' => $printerIp,
                'logs_count' => $logs->count()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Errore durante la stampa storico', [
                'table_order_id' => $tableOrder->id,
                'printer_ip' => $printerObj->ip ?? 'N/D',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Ottieni etichetta testuale per l'azione
     */
    private function getActionTextLabel(string $action): string
    {
        return match($action) {
            'create_order' => '[+] ORDINE CREATO',
            'update_order' => '[~] ORDINE MODIFICATO',
            'delete_order' => '[-] ORDINE ELIMINATO',
            'close_order' => '[X] ORDINE CHIUSO',
            'reopen_order' => '[O] ORDINE RIAPERTO',
            'add_item' => '[+] PRODOTTO AGGIUNTO',
            'update_item' => '[~] PRODOTTO MODIFICATO',
            'remove_item' => '[-] PRODOTTO RIMOSSO',
            'update_item_quantity' => '[~] QUANTITA MODIFICATA',
            'update_covers' => '[~] COPERTI MODIFICATI',
            'change_status' => '[~] STATO CAMBIATO',
            'print_marcia' => '[P] MARCIA STAMPATA',
            'print_preconto' => '[P] PRECONTO STAMPATO',
            'add_item_notes' => '[~] NOTE AGGIUNTE',
            'add_item_extras' => '[~] EXTRA AGGIUNTI',
            default => '[?] ' . strtoupper(str_replace('_', ' ', $action)),
        };
    }
}
