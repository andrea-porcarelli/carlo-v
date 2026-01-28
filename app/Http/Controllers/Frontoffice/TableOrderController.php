<?php

namespace App\Http\Controllers\Frontoffice;

use App\Http\Controllers\Controller;
use App\Interfaces\PrinterServiceInterface;
use App\Models\Dish;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\RestaurantTable;
use App\Models\TableOrder;
use App\Services\TableOrderLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TableOrderController extends Controller
{
    protected TableOrderLoggerService $logger;
    protected PrinterServiceInterface $printerService;

    public function __construct(TableOrderLoggerService $logger, PrinterServiceInterface $printerService)
    {
        $this->logger = $logger;
        $this->printerService = $printerService;
    }

    /**
     * Verify operator token and return user ID
     */
    private function verifyOperatorToken(?string $token): ?int
    {
        if (!$token) {
            return null;
        }

        // Check if token exists in session or request header
        $headerToken = request()->header('X-Operator-Token');
        $tokenToVerify = $token ?? $headerToken;

        if (!$tokenToVerify) {
            return null;
        }

        $tokenData = session('operator_token_' . $tokenToVerify);

        if (!$tokenData || !isset($tokenData['user_id'])) {
            return null;
        }

        // Check if token is older than 1 hour
        if (time() - $tokenData['timestamp'] > 3600) {
            session()->forget('operator_token_' . $tokenToVerify);
            return null;
        }

        return $tokenData['user_id'];
    }

    /**
     * Get all tables with their current orders
     */
    public function getTables(): JsonResponse
    {
        try {
            $tables = RestaurantTable::with(['activeOrder.items.dish'])
                ->where('is_active', true)
                ->orderBy('table_number')
                ->get()
                ->map(function ($table) {
                    return [
                        'id' => $table->id,
                        'table_number' => $table->table_number,
                        'capacity' => $table->capacity,
                        'position_x' => $table->position_x,
                        'position_y' => $table->position_y,
                        'status' => $table->status,
                        'has_active_order' => $table->hasActiveOrder(),
                        'current_total' => $table->getCurrentTotal(),
                        'active_order' => $table->activeOrder ? [
                            'id' => $table->activeOrder->id,
                            'items_count' => $table->activeOrder->items->count(),
                            'total_amount' => $table->activeOrder->total_amount,
                            'opened_at' => $table->activeOrder->opened_at->toIso8601String(),
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $tables,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching tables: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nel caricamento dei tavoli',
            ], 500);
        }
    }

    /**
     * Get table details with current order
     */
    public function getTable(RestaurantTable $table): JsonResponse
    {
        try {
            $table->load(['activeOrder.items.dish']);

            $items = [];
            if ($table->activeOrder) {
                $items = $table->activeOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'dish_id' => $item->dish_id,
                        'dish_name' => $item->dish->label,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $item->subtotal,
                        'notes' => $item->notes,
                        'extras' => $item->extras,
                        'removals' => $item->removals,
                        'status' => $item->status,
                    ];
                });
            }

            $order = $table->activeOrder;

            return response()->json([
                'success' => true,
                'data' => [
                    'table' => [
                        'id' => $table->id,
                        'table_number' => $table->table_number,
                        'status' => $table->status,
                    ],
                    'order' => $order ? [
                        'id' => $order->id,
                        'covers' => $order->covers,
                        'items_subtotal' => $order->getItemsSubtotal(),
                        'cover_charge_per_person' => $order->getCoverChargePerPerson(),
                        'cover_charge_total' => $order->getCoverChargeAmount(),
                        'has_cover_charge' => $order->hasCoverCharge(),
                        'total_amount' => $order->total_amount,
                        'items' => $items,
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching table: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nel caricamento del tavolo',
            ], 500);
        }
    }

    /**
     * Add multiple items to table order
     */
    public function addMultipleItems(Request $request, RestaurantTable $table): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
            'items.*.extras' => 'nullable|array',
            'items.*.removals' => 'nullable|array',
            'items.*.segue' => 'nullable|boolean',
            'items.*.custom_price' => 'nullable|numeric|min:0',
            'operator_token' => 'required|string',
        ]);

        // Verify operator token
        $operatorId = $this->verifyOperatorToken($validated['operator_token']);
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            DB::beginTransaction();

            // Get or create active order for this table
            $order = $table->activeOrder;
            $orderCreated = false;
            if (!$order) {
                $order = TableOrder::create([
                    'restaurant_table_id' => $table->id,
                    'covers' => 1,
                    'status' => 'open',
                    'waiter_id' => $operatorId,
                ]);

                $table->update(['status' => 'occupied']);
                $this->logger->logCreateOrder($order, $operatorId);
                $orderCreated = true;
            }

            $addedItems = [];
            foreach ($validated['items'] as $itemData) {
                $dish = Dish::findOrFail($itemData['dish_id']);

                // Use custom price if provided, otherwise use dish price
                $unitPrice = isset($itemData['custom_price']) && $itemData['custom_price'] !== null
                    ? $itemData['custom_price']
                    : $dish->price;

                $item = OrderItem::create([
                    'table_order_id' => $order->id,
                    'dish_id' => $dish->id,
                    'added_by' => $operatorId,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $unitPrice,
                    'notes' => $itemData['notes'] ?? null,
                    'extras' => $itemData['extras'] ?? null,
                    'removals' => $itemData['removals'] ?? null,
                    'segue' => $itemData['segue'] ?? false,
                ]);

                $this->logger->logAddItem($item, $operatorId);

                // Log granulare per notes ed extras
                if (!empty($itemData['notes'])) {
                    $this->logger->logAddItemNotes($item, $itemData['notes'], $operatorId);
                }
                if (!empty($itemData['extras'])) {
                    $this->logger->logAddItemExtras($item, $itemData['extras'], $operatorId);
                }

                $addedItems[] = $item;
            }

            DB::commit();

            // Stampa gli articoli aggiunti sulla stampante POS
            try {
                // Reload items with relationships
                $itemIds = collect($addedItems)->pluck('id');
                $itemsWithRelations = OrderItem::with('dish.category.printer')
                    ->whereIn('id', $itemIds)
                    ->get();
                $this->printerService->setOperatorId($operatorId)->printOrderItems($order, $itemsWithRelations, 'add');
            } catch (\Exception $e) {
                Log::warning('Errore durante la stampa POS multipla', [
                    'table_order_id' => $order->id,
                    'items_count' => count($addedItems),
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => count($addedItems) . ' prodotti aggiunti con successo',
                'data' => [
                    'items_count' => count($addedItems),
                    'order' => [
                        'id' => $order->id,
                        'total_amount' => $order->fresh()->total_amount,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding multiple items to table: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiunta dei prodotti',
            ], 500);
        }
    }

    /**
     * Add item to table order
     */
    public function addItem(Request $request, RestaurantTable $table): JsonResponse
    {
        $validated = $request->validate([
            'dish_id' => 'required|exists:dishes,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'extras' => 'nullable|array',
            'removals' => 'nullable|array',
            'segue' => 'nullable|boolean',
            'custom_price' => 'nullable|numeric|min:0',
            'operator_token' => 'required|string',
        ]);

        // Verify operator token
        $operatorId = $this->verifyOperatorToken($validated['operator_token']);
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            DB::beginTransaction();

            // Get or create active order for this table
            $order = $table->activeOrder;
            $orderCreated = false;
            if (!$order) {
                // This should not happen as table should be opened with covers first
                // But keep as fallback
                $order = TableOrder::create([
                    'restaurant_table_id' => $table->id,
                    'covers' => 1, // Default to 1 if not properly opened
                    'status' => 'open',
                    'waiter_id' => $operatorId,
                ]);

                // Update table status to occupied
                $table->update(['status' => 'occupied']);

                // Log order creation
                $this->logger->logCreateOrder($order, $operatorId);
                $orderCreated = true;
            }

            // Get dish information
            $dish = Dish::findOrFail($validated['dish_id']);

            // Use custom price if provided, otherwise use dish price
            $unitPrice = isset($validated['custom_price']) && $validated['custom_price'] !== null
                ? $validated['custom_price']
                : $dish->price;

            // Create order item
            $item = OrderItem::create([
                'table_order_id' => $order->id,
                'dish_id' => $dish->id,
                'added_by' => $operatorId,
                'quantity' => $validated['quantity'],
                'unit_price' => $unitPrice,
                'notes' => $validated['notes'] ?? null,
                'extras' => $validated['extras'] ?? null,
                'removals' => $validated['removals'] ?? null,
                'segue' => $validated['segue'] ?? false,
            ]);

            // The subtotal and order total are automatically calculated by the model

            // Log item addition
            $this->logger->logAddItem($item, $operatorId);

            // Log granulare per notes ed extras
            if (!empty($validated['notes'])) {
                $this->logger->logAddItemNotes($item, $validated['notes'], $operatorId);
            }
            if (!empty($validated['extras'])) {
                $this->logger->logAddItemExtras($item, $validated['extras'], $operatorId);
            }

            DB::commit();

            // Stampa l'articolo aggiunto sulla stampante POS
            // Eseguita dopo il commit per non bloccare l'operazione in caso di errore di stampa
            try {
                $item->load('dish.category.printer');
                $this->printerService->setOperatorId($operatorId)->printOrderItems($order, collect([$item]), 'add');
            } catch (\Exception $e) {
                // Log l'errore ma non fallire la richiesta
                Log::warning('Errore durante la stampa POS', [
                    'item_id' => $item->id,
                    'table_order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Prodotto aggiunto con successo',
                'data' => [
                    'item' => [
                        'id' => $item->id,
                        'dish_name' => $dish->name,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->subtotal,
                    ],
                    'order' => [
                        'id' => $order->id,
                        'total_amount' => $order->fresh()->total_amount,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding item to table: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiunta del prodotto',
            ], 500);
        }
    }

    /**
     * Remove item from order
     */
    public function removeItem(OrderItem $item): JsonResponse
    {
        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            DB::beginTransaction();


            $order = $item->order;

            // Log item removal before deletion
            $this->logger->logRemoveItem($item, $operatorId);

            $item->delete();

            // Check if order has no more items, then delete it and free the table
            if ($order->items()->count() === 0) {
                $table = $order->restaurantTable;
                $table->update(['status' => 'free']);

                // Log order deletion
                $this->logger->logDeleteOrder($order, $operatorId);

                $order->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prodotto rimosso con successo',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error removing item: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nella rimozione del prodotto',
            ], 500);
        }
    }

    /**
     * Update item quantity
     */
    public function updateItemQuantity(Request $request, OrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            DB::beginTransaction();

            $order = $item->order;

            // Save data before modification for logging
            $dataBefore = [
                'id' => $item->id,
                'quantity' => (int) $item->quantity,
                'subtotal' => $item->subtotal,
            ];

            // Update quantity
            $item->quantity = (int)  $validated['quantity'];
            $item->save(); // This will recalculate subtotal automatically

            // Log item update con metodo specifico per quantità
            $this->logger->logUpdateItemQuantity($item, $dataBefore['quantity'], (int) $validated['quantity'], $operatorId);

            DB::commit();

            // Stampa la modifica sulla stampante POS
            // Eseguita dopo il commit per non bloccare l'operazione in caso di errore di stampa
            try {
                $item->load('dish.category.printer');
                $this->printerService->setOperatorId($operatorId)->printOrderItems($order, collect([$item]), 'update');
            } catch (\Exception $e) {
                // Log l'errore ma non fallire la richiesta
                Log::warning('Errore durante la stampa POS per modifica', [
                    'item_id' => $item->id,
                    'table_order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Quantità aggiornata con successo',
                'data' => [
                    'item' => [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->subtotal,
                    ],
                    'order' => [
                        'id' => $order->id,
                        'total_amount' => $order->fresh()->total_amount,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating item quantity: ' . $e->getMessage() . ' line ' . $e->getLine() . ' in file ' . $e->getFile() );
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento della quantità' . $e->getLine(),
            ], 500);
        }
    }

    /**
     * Clear all items from table order
     */
    public function clearTable(RestaurantTable $table): JsonResponse
    {
        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            DB::beginTransaction();

            $order = $table->activeOrder;
            if ($order) {
                // Log each item removal
                foreach ($order->items as $item) {
                    $this->logger->logRemoveItem($item, $operatorId);
                }

                // Log order deletion
                $this->logger->logDeleteOrder($order, $operatorId);

                $order->items()->delete();
                $order->delete();
            }

            $table->update(['status' => 'free']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tavolo svuotato con successo',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error clearing table: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nello svuotamento del tavolo',
            ], 500);
        }
    }

    /**
     * Pay and close table order
     */
    public function payTable(RestaurantTable $table): JsonResponse
    {
        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            DB::beginTransaction();


            $order = $table->activeOrder;
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nessun ordine attivo per questo tavolo',
                ], 404);
            }

            // Log order closing
            $this->logger->logCloseOrder($order, $operatorId);

            $order->close();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conto incassato con successo',
                'data' => [
                    'total_paid' => $order->total_amount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error paying table: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'incasso del conto',
            ], 500);
        }
    }

    /**
     * Send "Marcia Tavolo" command to all printers involved in the table order
     */
    public function marciaTable(RestaurantTable $table): JsonResponse
    {
        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            $order = $table->activeOrder;
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nessun ordine attivo per questo tavolo',
                ], 404);
            }

            // Load items with relationships
            $order->load(['items.dish.category.printer', 'restaurantTable']);

            // Print marcia to all involved printers
            $success = $this->printerService->printMarciaTavolo($order, $operatorId);

            // Log stampa marcia
            $this->logger->logPrintMarcia($order, $operatorId);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Marcia tavolo inviata con successo',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Errore nell\'invio della marcia tavolo',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error sending marcia tavolo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'invio della marcia tavolo',
            ], 500);
        }
    }

    /**
     * Print PreConto (preliminary bill) with optional split
     */
    public function precontoTable(Request $request, RestaurantTable $table): JsonResponse
    {
        $validated = $request->validate([
            'split_count' => 'nullable|integer|min:1',
        ]);

        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            $order = $table->activeOrder;
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nessun ordine attivo per questo tavolo',
                ], 404);
            }

            // Load items with relationships
            $order->load(['items.dish', 'restaurantTable']);

            $splitCount = $validated['split_count'] ?? null;

            // Print preconto
            $success = $this->printerService->printPreconto($order, $operatorId, $splitCount);

            // Log stampa preconto
            $this->logger->logPrintPreconto($order, $operatorId, $splitCount);

            if ($success) {
                $message = 'PreConto stampato con successo';
                if ($splitCount && $splitCount > 1) {
                    $message .= " (diviso per $splitCount persone)";
                }
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Errore nella stampa del PreConto. Verificare la configurazione della stampante.',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error printing preconto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nella stampa del PreConto',
            ], 500);
        }
    }

    /**
     * Create or update table
     */
    public function saveTable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|exists:restaurant_tables,id',
            'table_number' => 'required|integer|unique:restaurant_tables,table_number,' . ($request->id ?? 'NULL'),
            'capacity' => 'required|integer|min:1',
            'position_x' => 'nullable|numeric',
            'position_y' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            if ($request->id) {
                $table = RestaurantTable::findOrFail($request->id);
                $table->update($validated);
            } else {
                $table = RestaurantTable::create($validated);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tavolo salvato con successo',
                'data' => $table,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving table: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nel salvataggio del tavolo',
            ], 500);
        }
    }

    /**
     * Delete table
     */
    public function deleteTable(RestaurantTable $table): JsonResponse
    {
        try {
            if ($table->hasActiveOrder()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossibile eliminare un tavolo con ordini attivi',
                ], 400);
            }

            $table->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tavolo eliminato con successo',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting table: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'eliminazione del tavolo',
            ], 500);
        }
    }

    /**
     * Open a table with covers (without adding items yet)
     * covers = 0 means "drinks mode" (consumo bevande)
     */
    public function openTable(Request $request, RestaurantTable $table): JsonResponse
    {
        $validated = $request->validate([
            'covers' => 'required|integer|min:0',
            'operator_token' => 'required|string',
        ]);

        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken($validated['operator_token']);
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            DB::beginTransaction();

            // Check if table already has an active order
            if ($table->activeOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Il tavolo ha già un ordine attivo',
                ], 400);
            }

            // Create new order with covers
            $order = TableOrder::create([
                'restaurant_table_id' => $table->id,
                'covers' => $validated['covers'],
                'status' => 'open',
                'waiter_id' => $operatorId,
            ]);

            // Update total (includes cover charge if applicable)
            $order->updateTotal();

            // Update table status to occupied
            $table->update(['status' => 'occupied']);

            // Log order creation
            $this->logger->logCreateOrder($order, $operatorId);

            DB::commit();

            // Build success message based on covers (0 = drinks mode)
            $message = $validated['covers'] > 0
                ? 'Tavolo aperto con ' . $validated['covers'] . ' coperti'
                : 'Tavolo aperto in modalità Consumo Bevande';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'order_id' => $order->id,
                    'covers' => $order->covers,
                    'cover_charge_total' => $order->getCoverChargeAmount(),
                    'total_amount' => $order->total_amount,
                    'table_status' => 'occupied',
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error opening table: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'apertura del tavolo',
            ], 500);
        }
    }

    /**
     * Add multiple tables in batch
     */
    public function addTables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:50',
        ]);

        try {
            DB::beginTransaction();

            // Get the highest table number currently in use
            $lastTableNumber = RestaurantTable::max('table_number') ?? 0;
            $tablesToCreate = [];

            for ($i = 1; $i <= $validated['count']; $i++) {
                $tablesToCreate[] = [
                    'table_number' => $lastTableNumber + $i,
                    'capacity' => 4, // Default capacity
                    'status' => 'free',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            RestaurantTable::insert($tablesToCreate);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $validated['count'] . ' tavoli aggiunti con successo',
                'data' => [
                    'created_count' => $validated['count'],
                    'starting_number' => $lastTableNumber + 1,
                    'ending_number' => $lastTableNumber + $validated['count'],
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding tables: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiunta dei tavoli',
            ], 500);
        }
    }

    /**
     * Send a communication to a specific printer
     */
    public function comunica(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'printer_id' => 'required|exists:printers,id',
            'message' => 'required|string|max:500',
            'table_id' => 'nullable|exists:restaurant_tables,id',
        ]);

        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            $printer = Printer::findOrFail($validated['printer_id']);

            if (!$printer->is_active || empty($printer->ip)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stampante non attiva o non configurata',
                ], 400);
            }

            // Get table order if table_id is provided
            $tableOrder = null;
            if (!empty($validated['table_id'])) {
                $table = RestaurantTable::find($validated['table_id']);
                if ($table) {
                    $tableOrder = $table->activeOrder;
                }
            }

            // Print the communication
            $success = $this->printerService
                ->setOperatorId($operatorId)
                ->printComunica($printer, $validated['message'], $operatorId, $tableOrder);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Comunicazione inviata con successo a ' . $printer->label,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Errore nell\'invio della comunicazione. Verificare la stampante.',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error sending communication: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'invio della comunicazione',
            ], 500);
        }
    }

    /**
     * Get available printers for communication
     */
    public function getPrinters(): JsonResponse
    {
        try {
            $printers = Printer::where('is_active', true)
                ->orderBy('label')
                ->get(['id', 'label', 'ip']);

            return response()->json([
                'success' => true,
                'data' => $printers,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching printers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nel caricamento delle stampanti',
            ], 500);
        }
    }

    /**
     * Update item price
     */
    public function updateItemPrice(Request $request, OrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'unit_price' => 'required|numeric|min:0',
        ]);

        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        try {
            DB::beginTransaction();

            $dataBefore = [
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ];

            $item->unit_price = $validated['unit_price'];
            $item->subtotal = $item->unit_price * $item->quantity;

            // Add extras to subtotal if present
            if (!empty($item->extras)) {
                $extrasTotal = array_sum($item->extras);
                $item->subtotal += $extrasTotal * $item->quantity;
            }

            $item->save();

            // Update order total
            $order = $item->order;
            $order->total_amount = $order->items()->sum('subtotal');

            // Add cover charge if applicable
            if ($order->hasCoverCharge()) {
                $order->total_amount += $order->getCoverChargeAmount();
            }

            $order->save();

            // Log the price update
            $this->logger->logUpdateItem($item, $dataBefore, $operatorId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prezzo aggiornato',
                'data' => [
                    'item' => $item->fresh(['dish']),
                    'order_total' => $order->total_amount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating item price: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento del prezzo',
            ], 500);
        }
    }
}
