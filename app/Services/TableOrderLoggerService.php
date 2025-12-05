<?php

namespace App\Services;

use App\Models\TableOrderLog;
use App\Models\TableOrder;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TableOrderLoggerService
{
    /**
     * Log generico per operazioni su ordini tavolo
     */
    public function log(
        string $action,
        string $entityType,
        ?Model $entity = null,
        ?array $dataBefore = null,
        ?array $dataAfter = null,
        ?string $notes = null,
        ?int $tableOrderId = null,
        ?int $orderItemId = null,
        ?int $userId = null
    ): TableOrderLog {
        $changes = $this->calculateChanges($dataBefore, $dataAfter);

        return TableOrderLog::create([
            'table_order_id' => $tableOrderId ?? ($entity instanceof TableOrder ? $entity->id : null),
            'order_item_id' => $orderItemId ?? ($entity instanceof OrderItem ? $entity->id : null),
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'data_before' => $dataBefore,
            'data_after' => $dataAfter,
            'changes' => $changes,
            'notes' => $notes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log per creazione ordine
     */
    public function logCreateOrder(TableOrder $order, int $operatorId = 0): TableOrderLog
    {
        return $this->log(
            action: 'create_order',
            entityType: 'table_order',
            entity: $order,
            dataAfter: $this->getOrderData($order),
            notes: "Creato ordine per tavolo #{$order->restaurantTable->table_number}",
            userId: $operatorId,
        );
    }

    /**
     * Log per aggiornamento ordine
     */
    public function logUpdateOrder(TableOrder $order, array $dataBefore, int $operatorId = 0): TableOrderLog
    {
        return $this->log(
            action: 'update_order',
            entityType: 'table_order',
            entity: $order,
            dataBefore: $dataBefore,
            dataAfter: $this->getOrderData($order),
            notes: "Modificato ordine #{$order->id}",
            userId: $operatorId,
        );
    }

    /**
     * Log per eliminazione ordine
     */
    public function logDeleteOrder(TableOrder $order, int $operatorId = 0): TableOrderLog
    {
        return $this->log(
            action: 'delete_order',
            entityType: 'table_order',
            dataBefore: $this->getOrderData($order),
            tableOrderId: $order->id,
            notes: "Eliminato ordine #{$order->id}",
            userId: $operatorId,
        );
    }

    /**
     * Log per aggiunta item
     */
    public function logAddItem(OrderItem $item, int $operatorId = 0): TableOrderLog
    {
        $dishName = $item->dish->label ?? $item->dish->name ?? 'Prodotto';
        return $this->log(
            action: 'add_item',
            entityType: 'order_item',
            entity: $item,
            dataAfter: $this->getItemData($item),
            tableOrderId: $item->table_order_id,
            notes: "Aggiunto {$dishName} (x{$item->quantity})",
            userId: $operatorId,
        );
    }

    /**
     * Log per aggiornamento item
     */
    public function logUpdateItem(OrderItem $item, array $dataBefore, int $operatorId = 0): TableOrderLog
    {
        $dishName = $item->dish->label ?? $item->dish->name ?? 'Prodotto';
        return $this->log(
            action: 'update_item',
            entityType: 'order_item',
            entity: $item,
            dataBefore: $dataBefore,
            dataAfter: $this->getItemData($item),
            tableOrderId: $item->table_order_id,
            notes: "Modificato {$dishName}",
            userId: $operatorId
        );
    }

    /**
     * Log per rimozione item
     */
    public function logRemoveItem(OrderItem $item, int $operatorId = 0): TableOrderLog
    {
        $dishName = $item->dish->label ?? $item->dish->name ?? 'Prodotto';
        return $this->log(
            action: 'remove_item',
            entityType: 'order_item',
            dataBefore: $this->getItemData($item),
            tableOrderId: $item->table_order_id,
            orderItemId: $item->id,
            notes: "Rimosso {$dishName} (x{$item->quantity})",
            userId: $operatorId
        );
    }

    /**
     * Log per cambio stato
     */
    public function logChangeStatus(TableOrder $order, string $oldStatus, string $newStatus, int $operatorId = 0): TableOrderLog
    {
        return $this->log(
            action: 'change_status',
            entityType: 'table_order',
            entity: $order,
            dataBefore: ['status' => $oldStatus],
            dataAfter: ['status' => $newStatus],
            notes: "Cambiato stato da '{$oldStatus}' a '{$newStatus}'",
            userId: $operatorId,
        );
    }

    /**
     * Log per aggiornamento coperti
     */
    public function logUpdateCovers(TableOrder $order, int $oldCovers, int $newCovers, int $operatorId = 0): TableOrderLog
    {
        return $this->log(
            action: 'update_covers',
            entityType: 'table_order',
            entity: $order,
            dataBefore: ['covers' => $oldCovers],
            dataAfter: ['covers' => $newCovers],
            notes: "Modificato numero coperti da {$oldCovers} a {$newCovers}",
            userId: $operatorId,
        );
    }

    /**
     * Log per chiusura ordine
     */
    public function logCloseOrder(TableOrder $order, int $operatorId = 0): TableOrderLog
    {
        return $this->log(
            action: 'close_order',
            entityType: 'table_order',
            entity: $order,
            dataBefore: $this->getOrderData($order),
            dataAfter: array_merge($this->getOrderData($order), ['status' => 'closed']),
            notes: "Chiuso ordine #{$order->id}",
            userId: $operatorId,
        );
    }

    /**
     * Log per riapertura ordine
     */
    public function logReopenOrder(TableOrder $order, int $operatorId = 0): TableOrderLog
    {
        return $this->log(
            action: 'reopen_order',
            entityType: 'table_order',
            entity: $order,
            dataBefore: $this->getOrderData($order),
            dataAfter: array_merge($this->getOrderData($order), ['status' => 'active']),
            notes: "Riaperto ordine #{$order->id}",
            userId: $operatorId,
        );
    }

    /**
     * Ottiene i dati dell'ordine in formato array
     */
    private function getOrderData(TableOrder $order): array
    {
        return [
            'id' => $order->id,
            'restaurant_table_id' => $order->restaurant_table_id,
            'table_number' => $order->restaurantTable->table_number ?? null,
            'status' => $order->status,
            'covers' => $order->covers,
            'total_items' => $order->items()->count() ?? 0,
            'total_amount' => $order->items()->get()->sum(fn($item) =>  $item->price * $item->quantity) ?? 0,
            'created_at' => $order->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Ottiene i dati dell'item in formato array
     */
    private function getItemData(OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'table_order_id' => $item->table_order_id,
            'dish_id' => $item->dish_id,
            'dish_name' => $item->dish->label ?? $item->dish->name ?? null,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'subtotal' => $item->subtotal,
            'notes' => $item->notes,
            'extras' => $item->extras,
            'removals' => $item->removals,
            'added_by' => $item->added_by,
            'added_by_name' => $item->addedBy->name ?? null,
        ];
    }

    /**
     * Calcola le differenze tra prima e dopo
     */
    private function calculateChanges(?array $dataBefore, ?array $dataAfter): ?array
    {
        if (!$dataBefore || !$dataAfter) {
            return null;
        }

        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($dataBefore), array_keys($dataAfter)));

        foreach ($allKeys as $key) {
            $oldValue = $dataBefore[$key] ?? null;
            $newValue = $dataAfter[$key] ?? null;

            // Confronto gestendo sia scalari che array
            $hasChanged = false;
            if (is_array($oldValue) || is_array($newValue)) {
                // Per array, usa json_encode per il confronto
                $hasChanged = json_encode($oldValue) !== json_encode($newValue);
            } else {
                // Per scalari, converti a stringa
                $hasChanged = (string)$oldValue !== (string)$newValue;
            }

            if ($hasChanged) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return empty($changes) ? null : $changes;
    }

    /**
     * Recupera i log per un ordine specifico
     */
    public function getLogsForOrder(int $tableOrderId, int $limit = 50)
    {
        return TableOrderLog::where('table_order_id', $tableOrderId)
            ->with(['user:id,name', 'orderItem.dish:id,label'])
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Recupera i log per un operatore
     */
    public function getLogsForUser(int $userId, int $limit = 50)
    {
        return TableOrderLog::where('user_id', $userId)
            ->with(['tableOrder.restaurantTable', 'orderItem.dish:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Recupera statistiche operatore
     */
    public function getUserStats(int $userId, $startDate = null, $endDate = null): array
    {
        $query = TableOrderLog::where('user_id', $userId);

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $logs = $query->get();

        return [
            'total_actions' => $logs->count(),
            'orders_created' => $logs->where('action', 'create_order')->count(),
            'items_added' => $logs->where('action', 'add_item')->count(),
            'items_modified' => $logs->where('action', 'update_item')->count(),
            'items_removed' => $logs->where('action', 'remove_item')->count(),
            'actions_by_type' => $logs->groupBy('action')->map->count(),
        ];
    }
}
