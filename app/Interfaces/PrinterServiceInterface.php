<?php

namespace App\Interfaces;

use App\Models\OrderItem;
use App\Models\PrecontoSplit;
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
    public function printPreconto(TableOrder $tableOrder, int $operatorId, ?int $splitCount = null, string $discountType = 'none', float $discountAmount = 0): bool;

    /**
     * Stampa un preconto parziale (solo i piatti del split selezionato)
     */
    public function printPartialPreconto(TableOrder $tableOrder, PrecontoSplit $split, int $operatorId): bool;

    /**
     * Reprint a logged print job
     *
     * @param int $printLogId ID del log di stampa
     * @return bool True se la ristampa è andata a buon fine, false altrimenti
     */
    public function reprint(int $printLogId): bool;

    /**
     * Stampa notifica spostamento tavolo su tutte le stampanti coinvolte
     *
     * @param TableOrder $sourceOrder L'ordine del tavolo sorgente
     * @param \App\Models\RestaurantTable $destinationTable Il tavolo di destinazione
     * @param int $operatorId ID dell'operatore
     * @return bool
     */
    public function printSpostamento(TableOrder $sourceOrder, \App\Models\RestaurantTable $destinationTable, int $operatorId): bool;

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

    /**
     * Send ESC/POS pulse to open cash drawer connected to the given printer.
     */

    public function pollCashDrawer(string $printer, string $operationId): array;

    public function cancelCashDrawer(string $printer, string $operationId, int $tipoAnnullamento = 2): array;

    /**
     * Stampa un cambio piatto: STORNO del vecchio piatto e AGGIUNTA del nuovo
     *
     * @param TableOrder $tableOrder L'ordine del tavolo
     * @param OrderItem $newItem L'item aggiornato (con il nuovo piatto caricato)
     * @param string $oldDishName Nome del vecchio piatto
     * @param Printer|null $oldPrinter Stampante del vecchio piatto (null se non trovata)
     * @return bool
     */
    public function printDishChange(TableOrder $tableOrder, OrderItem $newItem, string $oldDishName, ?Printer $oldPrinter): bool;

    /**
     * Stampa lo scontrino (non fiscale) del corrispettivo elettronico con progressivoSdi.
     */
    public function printCorrispettivoReceipt(\App\Models\TableOrderCorrispettivo $corrispettivo): bool;

    /**
     * Stampa lo scontrino "PRENOTAZIONE CORSO" generato dalla booking di Misuraca.
     *
     * @param  array<string, mixed>  $data Campi attesi: reference, class_title,
     *                                     slot_start, slot_end, pax, customer_name,
     *                                     email, phone, notes, total_cents, currency.
     */
    public function printCookingBooking(Printer $printer, array $data): bool;

    /**
     * Stampa lo scontrino "PRENOTAZIONE TAVOLO" generato dalla prenotazione
     * tavoli di Misuraca (ristorante, non cooking class).
     *
     * @param  array<string, mixed>  $data Campi attesi: reference, reservation_date,
     *                                     slot_time, adults, children, total_pax,
     *                                     customer_name, first_name, last_name,
     *                                     email, phone, special_requests, country_code.
     */
    public function printTableReservation(Printer $printer, array $data): bool;
}
