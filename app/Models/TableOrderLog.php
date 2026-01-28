<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class TableOrderLog extends Model
{
    use HasFactory;

    // Costanti per categorie
    const CATEGORY_ORDER = 'order';    // Gestione ordine/tavolo
    const CATEGORY_ITEM = 'item';      // Gestione piatti
    const CATEGORY_COVERS = 'covers';  // Gestione coperti
    const CATEGORY_PRINT = 'print';    // Stampe

    // Costanti per azioni esistenti
    const ACTION_CREATE_ORDER = 'create_order';
    const ACTION_UPDATE_ORDER = 'update_order';
    const ACTION_DELETE_ORDER = 'delete_order';
    const ACTION_CLOSE_ORDER = 'close_order';
    const ACTION_REOPEN_ORDER = 'reopen_order';
    const ACTION_CHANGE_STATUS = 'change_status';
    const ACTION_ADD_ITEM = 'add_item';
    const ACTION_UPDATE_ITEM = 'update_item';
    const ACTION_REMOVE_ITEM = 'remove_item';
    const ACTION_UPDATE_COVERS = 'update_covers';

    // Nuove azioni granulari
    const ACTION_ADD_ITEM_NOTES = 'add_item_notes';
    const ACTION_ADD_ITEM_EXTRAS = 'add_item_extras';
    const ACTION_UPDATE_ITEM_QUANTITY = 'update_item_quantity';
    const ACTION_PRINT_MARCIA = 'print_marcia';
    const ACTION_PRINT_PRECONTO = 'print_preconto';

    // Mapping azione -> categoria
    const ACTION_CATEGORY_MAP = [
        // Categoria 'order' - Gestione ordine/tavolo
        self::ACTION_CREATE_ORDER => self::CATEGORY_ORDER,
        self::ACTION_UPDATE_ORDER => self::CATEGORY_ORDER,
        self::ACTION_DELETE_ORDER => self::CATEGORY_ORDER,
        self::ACTION_CLOSE_ORDER => self::CATEGORY_ORDER,
        self::ACTION_REOPEN_ORDER => self::CATEGORY_ORDER,
        self::ACTION_CHANGE_STATUS => self::CATEGORY_ORDER,
        // Categoria 'item' - Gestione piatti
        self::ACTION_ADD_ITEM => self::CATEGORY_ITEM,
        self::ACTION_UPDATE_ITEM => self::CATEGORY_ITEM,
        self::ACTION_REMOVE_ITEM => self::CATEGORY_ITEM,
        self::ACTION_ADD_ITEM_NOTES => self::CATEGORY_ITEM,
        self::ACTION_ADD_ITEM_EXTRAS => self::CATEGORY_ITEM,
        self::ACTION_UPDATE_ITEM_QUANTITY => self::CATEGORY_ITEM,
        // Categoria 'covers' - Gestione coperti
        self::ACTION_UPDATE_COVERS => self::CATEGORY_COVERS,
        // Categoria 'print' - Stampe
        self::ACTION_PRINT_MARCIA => self::CATEGORY_PRINT,
        self::ACTION_PRINT_PRECONTO => self::CATEGORY_PRINT,
    ];

    protected $fillable = [
        'table_order_id',
        'order_item_id',
        'user_id',
        'action',
        'category',
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
     * Scope per filtrare per categoria
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
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
            'add_item_notes' => 'Aggiunte note prodotto',
            'add_item_extras' => 'Aggiunti extra prodotto',
            'update_item_quantity' => 'Modificata quantità',
            'print_marcia' => 'Stampata marcia',
            'print_preconto' => 'Stampato preconto',
        ];

        return $descriptions[$this->action] ?? $this->action;
    }

    /**
     * Restituisce la descrizione leggibile della categoria
     */
    public function getCategoryDescription(): string
    {
        $descriptions = [
            self::CATEGORY_ORDER => 'Gestione Ordine',
            self::CATEGORY_ITEM => 'Gestione Piatti',
            self::CATEGORY_COVERS => 'Gestione Coperti',
            self::CATEGORY_PRINT => 'Stampe',
        ];

        return $descriptions[$this->category] ?? $this->category ?? 'N/D';
    }

    /**
     * Restituisce la classe CSS per il badge della categoria
     */
    public function getCategoryBadgeClass(): string
    {
        $classes = [
            self::CATEGORY_ORDER => 'primary',
            self::CATEGORY_ITEM => 'success',
            self::CATEGORY_COVERS => 'info',
            self::CATEGORY_PRINT => 'warning',
        ];

        return $classes[$this->category] ?? 'secondary';
    }

    /**
     * Ottiene la categoria per una specifica azione
     */
    public static function getCategoryForAction(string $action): ?string
    {
        return self::ACTION_CATEGORY_MAP[$action] ?? null;
    }

    /**
     * Restituisce le categorie disponibili con le relative descrizioni
     */
    public static function getAvailableCategories(): array
    {
        return [
            self::CATEGORY_ORDER => 'Gestione Ordine',
            self::CATEGORY_ITEM => 'Gestione Piatti',
            self::CATEGORY_COVERS => 'Gestione Coperti',
            self::CATEGORY_PRINT => 'Stampe',
        ];
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
            'reopen_order' => 'primary',
            'add_item_notes' => 'info',
            'add_item_extras' => 'info',
            'update_item_quantity' => 'info',
            'print_marcia' => 'warning',
            'print_preconto' => 'warning',
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
            'reopen_order' => 'unlock',
            'add_item_notes' => 'comment',
            'add_item_extras' => 'plus-square',
            'update_item_quantity' => 'sort-numeric-up',
            'print_marcia' => 'print',
            'print_preconto' => 'file-invoice',
        ];
        return $icons[$action] ?? 'circle';
    }
}
