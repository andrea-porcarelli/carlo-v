<?php

namespace App\Interfaces;

use App\Models\OrderItem;
use App\Models\Printer;
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

    /**
     * Stampa "MARCIA TAVOLO" su tutte le stampanti coinvolte dall'ordine
     *
     * @param TableOrder $tableOrder L'ordine del tavolo
     * @param int $operatorId ID dell'operatore
     * @return bool True se la stampa è andata a buon fine, false altrimenti
     */
    public function printMarciaTavolo(TableOrder $tableOrder, int $operatorId): bool;

    /**
     * Stampa il PreConto sulla stampante di default
     *
     * @param TableOrder $tableOrder L'ordine del tavolo
     * @param int $operatorId ID dell'operatore
     * @param int|null $splitCount Numero di persone per dividere il conto (opzionale)
     * @return bool True se la stampa è andata a buon fine, false altrimenti
     */
    public function printPreconto(TableOrder $tableOrder, int $operatorId, ?int $splitCount = null): bool;

    /**
     * Reprint a logged print job
     *
     * @param int $printLogId ID del log di stampa
     * @return bool True se la ristampa è andata a buon fine, false altrimenti
     */
    public function reprint(int $printLogId): bool;

    /**
     * Set the current operator ID for logging
     *
     * @param int $operatorId
     * @return self
     */
    public function setOperatorId(int $operatorId): self;

    /**
     * Stampa lo storico operazioni su una stampante POS
     *
     * @param TableOrder $tableOrder L'ordine del tavolo
     * @param Printer $printer Stampante su cui stampare
     * @param Collection $logs Collection di TableOrderLog
     * @param int|null $operatorId ID dell'operatore
     * @return bool True se la stampa è andata a buon fine, false altrimenti
     */
    public function printHistory(TableOrder $tableOrder, Printer $printer, Collection $logs, ?int $operatorId = null): bool;

    /**
     * Stampa log filtrati su una stampante POS
     *
     * @param Printer $printer Stampante su cui stampare
     * @param Collection $logs Collection di TableOrderLog
     * @param array $filters Filtri applicati (date_from, date_to, user_id, table_number)
     * @param int|null $operatorId ID dell'operatore
     * @return bool True se la stampa è andata a buon fine, false altrimenti
     */
    public function printFilteredLogs(Printer $printer, Collection $logs, array $filters, ?int $operatorId = null): bool;
}
