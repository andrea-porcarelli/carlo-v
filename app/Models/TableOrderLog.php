<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class TableOrderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_order_id',
        'order_item_id',
        'user_id',
        'action',
        'entity_type',
        'data_before',
        'data_after',
        'changes',
        'notes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'data_before' => 'array',
        'data_after' => 'array',
        'changes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relazione con TableOrder
     */
    public function tableOrder(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class);
    }

    /**
     * Relazione con OrderItem
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Relazione con User (operatore)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope per filtrare per azione
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope per filtrare per utente
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope per filtrare per ordine tavolo
     */
    public function scopeByTableOrder($query, int $tableOrderId)
    {
        return $query->where('table_order_id', $tableOrderId);
    }

    /**
     * Scope per filtrare per periodo
     */
    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Restituisce una descrizione leggibile dell'azione
     */
    public function getActionDescription(): string
    {
        $descriptions = [
            'create_order' => 'Creato nuovo ordine',
            'update_order' => 'Modificato ordine',
            'delete_order' => 'Eliminato ordine',
            'add_item' => 'Aggiunto prodotto',
            'update_item' => 'Modificato prodotto',
            'remove_item' => 'Rimosso prodotto',
            'change_status' => 'Cambiato stato',
            'update_covers' => 'Modificato numero coperti',
            'close_order' => 'Chiuso ordine',
            'reopen_order' => 'Riaperto ordine',
        ];

        return $descriptions[$this->action] ?? $this->action;
    }

    /**
     * Formatta le modifiche in modo leggibile
     */
    public function getFormattedChanges(): array
    {
        Log::info($this->id . ': ' . __LINE__ . ' count: ' . count($this->changes));
        if (!$this->changes) {
            return [];
        }
        Log::info(__LINE__);

        // Campi da mostrare all'utente (escludiamo ID tecnici e nomi duplicati)
        $relevantFields = ['quantity', 'unit_price', 'subtotal', 'notes', 'status', 'covers', 'extras', 'removals'];

        Log::info(__LINE__);
        $formatted = [];
        Log::info(__LINE__);
        foreach ($this->changes as $field => $change) {
            Log::info("Change [$field]");
            // Salta i campi non rilevanti per l'utente
            if (!in_array($field, $relevantFields)) {
                continue;
            }

            $oldValue = $change['old'] ?? null;
            $newValue = $change['new'] ?? null;

            // Salta se entrambi i valori sono null o uguali
            if ($oldValue === $newValue) {
                continue;
            }

            // Formatta valori numerici come prezzi
            if (in_array($field, ['price', 'unit_price', 'subtotal', 'total_amount'])) {
                if (is_numeric($oldValue)) {
                    $oldValue = '€' . number_format($oldValue, 2, ',', '.');
                }
                if (is_numeric($newValue)) {
                    $newValue = '€' . number_format($newValue, 2, ',', '.');
                }
            }

            // Formatta array in modo leggibile
            if (is_array($oldValue)) {
                $oldValue = !empty($oldValue) ? implode(', ', array_map(function($k, $v) {
                    return is_numeric($k) ? $v : "$k: €" . number_format($v, 2, ',', '.');
                }, array_keys($oldValue), $oldValue)) : '-';
            }
            if (is_array($newValue)) {
                $newValue = !empty($newValue) ? implode(', ', array_map(function($k, $v) {
                    return is_numeric($k) ? $v : "$k: €" . number_format($v, 2, ',', '.');
                }, array_keys($newValue), $newValue)) : '-';
            }

            // Se è null, mostra un trattino
            if (is_null($oldValue)) {
                $oldValue = '-';
            }
            if (is_null($newValue)) {
                $newValue = '-';
            }

            $formatted[] = [
                'field' => $this->translateField($field),
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $formatted;
    }

    /**
     * Traduce i nomi dei campi
     */
    private function translateField(string $field): string
    {
        $translations = [
            'status' => 'Stato',
            'covers' => 'Coperti',
            'quantity' => 'Quantità',
            'notes' => 'Note',
            'price' => 'Prezzo',
            'unit_price' => 'Prezzo unitario',
            'subtotal' => 'Subtotale',
            'dish_id' => 'Piatto',
            'dish_name' => 'Nome piatto',
            'table_number' => 'Numero tavolo',
            'table_order_id' => 'ID Ordine',
            'restaurant_table_id' => 'ID Tavolo',
            'total_items' => 'Totale prodotti',
            'total_amount' => 'Importo totale',
            'extras' => 'Supplementi',
            'removals' => 'Rimozioni',
            'added_by' => 'Aggiunto da',
            'added_by_name' => 'Nome operatore',
            'id' => 'ID',
        ];

        return $translations[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    public function getActionBadgeClass($action) {
        $classes = [
            'create_order' => 'success',
            'add_item' => 'success',
            'update_order' => 'info',
            'update_item' => 'info',
            'remove_item' => 'warning',
            'delete_order' => 'danger',
            'close_order' => 'secondary',
            'change_status' => 'primary',
            'update_covers' => 'info',
            'reopen_order' => 'primary'
        ];
        return $classes[$action] ?? 'secondary';
    }

    public function getActionIcon($action) {
        $icons = [
            'create_order' => 'plus-circle',
            'add_item' => 'shopping-cart',
            'update_order' => 'edit',
            'update_item' => 'pencil',
            'remove_item' => 'minus-circle',
            'delete_order' => 'trash',
            'close_order' => 'lock',
            'change_status' => 'exchange',
            'update_covers' => 'users',
            'reopen_order' => 'unlock'
        ];
        return $icons[$action] ?? 'circle';
    }
}
