<?php

namespace App\Interfaces;

use App\Models\OrderItem;
use App\Models\TableOrder;
use Illuminate\Support\Collection;

interface PrinterServiceInterface
{
    /**
     * Stampa gli articoli su una stampante POS
     *
     * @param TableOrder $tableOrder L'ordine del tavolo
     * @param Collection|array $items Array di OrderItem da stampare
     * @param string|null $operation Tipo di operazione: 'add', 'update', 'remove'
     * @return bool True se la stampa è andata a buon fine, false altrimenti
     */
    public function printOrderItems(TableOrder $tableOrder, Collection|array $items, ?string $operation = 'add'): bool;

    /**
     * Verifica se una stampante è raggiungibile
     *
     * @param string $printerIp Indirizzo IP della stampante
     * @return bool True se la stampante è raggiungibile
     */
    public function isPrinterReachable(string $printerIp): bool;

    /**
     * Raggruppa gli articoli per stampante in base alla categoria
     *
     * @param Collection|array $items Array di OrderItem
     * @return array Array di array con struttura ['printer' => Printer, 'items' => [OrderItem, ...]]
     */
    public function groupItemsByPrinter(Collection|array $items): array;
}
