<?php

namespace App\Http\Controllers\Frontoffice;

use App\Http\Controllers\Controller;
use App\Interfaces\PrinterServiceInterface;
use App\Jobs\PrintComunicaJob;
use App\Jobs\PrintDishChangeJob;
use App\Jobs\PrintMarciaJob;
use App\Jobs\PrintOrderItemsJob;
use App\Jobs\PrintPrecontoJob;
use App\Models\CashDrawerLog;
use App\Models\Dish;
use App\Models\MenuOption;
use App\Models\OrderItem;
use App\Models\OrderItemMaterial;
use App\Models\PrecontoSplit;
use App\Models\Printer;
use App\Models\RestaurantTable;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\TableOrder;
use App\Models\TableOrderInvoice;
use App\Models\User;
use App\Services\MysondFatturaService;
use App\Services\TableOrderLoggerService;
use App\Services\VegaPosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        Log::info($tokenData);

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
    public function getMenuOptions(): JsonResponse
    {
        $extras = MenuOption::extras()->active()
            ->orderBy('sort_order')->orderBy('label')
            ->get(['id', 'label', 'price']);
        $removals = MenuOption::removals()->active()
            ->orderBy('sort_order')->orderBy('label')
            ->get(['id', 'label']);
        return response()->json(['success' => true, 'data' => compact('extras', 'removals')]);
    }

    /**
     * Return all active dishes grouped by category (for dish-change selector)
     */
    public function getDishes(): JsonResponse
    {
        $dishes = Dish::with('category')
            ->where('is_active', true)
            ->orderBy('label')
            ->get()
            ->map(fn($d) => [
                'id'            => $d->id,
                'name'          => $d->label,
                'price'         => (float) $d->price,
                'category_id'   => $d->category_id,
                'category_name' => $d->category->label ?? 'N/D',
            ]);

        return response()->json(['success' => true, 'data' => $dishes]);
    }

    public function getTables(): JsonResponse
    {
        try {
            $tables = RestaurantTable::with(['activeOrder.items.dish', 'activeOrder.precontoSplits'])
                ->where('is_active', true)
                ->orderBy('table_number')
                ->get()
                ->map(function ($table) {
                    $order = $table->activeOrder;
                    $currentTotal = $table->getCurrentTotal();
                    $remainingTotal = $currentTotal;
                    if ($order) {
                        $paidSplits = $order->precontoSplits->where('status', 'paid');
                        $paidSplitsTotal = round((float) $paidSplits->sum('total'), 2);
                        $paidCoversTotal = round($paidSplits->sum('covers') * $order->getCoverChargePerPerson(), 2);
                        $effectiveTotal = $order->hasDiscount() ? $order->getDiscountedTotal() : $currentTotal;
                        $remainingTotal = max(0, round($effectiveTotal - $paidSplitsTotal, 2));
                    }
                    return [
                        'id' => $table->id,
                        'table_number' => $table->table_number,
                        'capacity' => $table->capacity,
                        'position_x' => $table->position_x,
                        'position_y' => $table->position_y,
                        'status' => $table->status,
                        'is_banco' => (bool) $table->is_banco,
                        'has_active_order' => $table->hasActiveOrder(),
                        'current_total' => $currentTotal,
                        'remaining_total' => $remainingTotal,
                        'has_preconto' => $order?->preconto_requested_at !== null,
                        'active_order' => $order ? [
                            'id' => $order->id,
                            'items_count' => $order->items->count(),
                            'total_amount' => $order->total_amount,
                            'opened_at' => $order->opened_at->toIso8601String(),
                            'covers' => $order->covers,
                            'autoconsumo' => $order->autoconsumo,
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
            $table->load(['activeOrder.items.dish', 'activeOrder.items.autoconsumoUser']);

            $items = [];
            if ($table->activeOrder) {
                $items = $table->activeOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'dish_id' => $item->dish_id,
                        'dish_name' => $item->dish_id ? ($item->dish->label ?? 'N/D') : null,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $item->subtotal,
                        'notes' => $item->notes,
                        'extras' => $item->extras,
                        'removals' => $item->removals,
                        'status' => $item->status,
                        'segue' => (bool) $item->segue,
                        'sort_order' => $item->sort_order,
                        'autoconsumo_user_id' => $item->autoconsumo_user_id,
                        'autoconsumo_user_name' => $item->autoconsumoUser?->name,
                    ];
                });
            }

            $order = $table->activeOrder;

            // Aggregate paid preconto splits info
            $paidSplitsTotal = 0;
            $paidItemsMap = [];
            $pendingSplitsData = [];
            if ($order) {
                $allSplits = $order->precontoSplits()->orderBy('created_at')->get();
                $paidSplitsTotal = round((float) $allSplits->where('status', 'paid')->sum('total'), 2);
                $coverPerPerson  = $order->getCoverChargePerPerson();
                $paidCoversTotal = round($allSplits->where('status', 'paid')->sum('covers') * $coverPerPerson, 2);
                foreach ($allSplits->where('status', 'paid') as $split) {
                    foreach ($split->items ?? [] as $splitItem) {
                        $itemId = $splitItem['order_item_id'] ?? null;
                        $qty = (float) ($splitItem['quantity'] ?? $splitItem['qty'] ?? 1);
                        if ($itemId) {
                            $paidItemsMap[$itemId] = ($paidItemsMap[$itemId] ?? 0) + $qty;
                        }
                    }
                }
                foreach ($allSplits->where('status', 'pending') as $split) {
                    $pendingSplitsData[] = [
                        'id'    => $split->id,
                        'label' => $split->label,
                        'total' => (float) $split->total,
                    ];
                }
            }
            $paidItems = collect($paidItemsMap)
                ->map(fn($qty, $id) => ['order_item_id' => (int) $id, 'paid_qty' => $qty])
                ->values()
                ->all();

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
                        'autoconsumo' => (bool) $order->autoconsumo,
                        'has_preconto' => $order->preconto_requested_at !== null,
                        'items_subtotal' => $order->getItemsSubtotal(),
                        'cover_charge_per_person' => $order->getCoverChargePerPerson(),
                        'cover_charge_total' => $order->getCoverChargeAmount(),
                        'has_cover_charge' => $order->hasCoverCharge(),
                        'total_amount'      => $order->total_amount,
                        'discount_type'     => $order->discount_type,
                        'discount_amount'   => $order->discount_amount ? (float) $order->discount_amount : null,
                        'discount_value'    => $order->discount_value ? (float) $order->discount_value : null,
                        'discounted_total'  => $order->getDiscountedTotal(),
                        'paid_splits_total' => $paidSplitsTotal,
                        'paid_covers_total' => $paidCoversTotal ?? 0,
                        'pending_splits'    => $pendingSplitsData,
                        'paid_items'        => $paidItems,
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
            'items.*.dish_id' => 'nullable|integer|exists:dishes,id',
            'items.*.quantity' => 'nullable|integer|min:1',
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

            // If the order has a pending preconto, cancel all pending splits
            if ($order->preconto_requested_at !== null) {
                PrecontoSplit::where('table_order_id', $order->id)
                    ->where('status', 'pending')
                    ->delete();
                $order->update(['preconto_requested_at' => null]);
            }

            $addedItems = [];
            foreach ($validated['items'] as $itemData) {
                // Segue separator item (no dish)
                if (is_null($itemData['dish_id'] ?? null) && ($itemData['segue'] ?? false)) {
                    $item = OrderItem::create([
                        'table_order_id' => $order->id,
                        'dish_id'        => null,
                        'quantity'       => 1,
                        'unit_price'     => 0,
                        'segue'          => true,
                        'status'         => 'pending',
                    ]);
                    $addedItems[] = $item;
                    continue;
                }

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
                    'segue' => false,
                ]);

                // Save material snapshot at order time (normalized to base units)
                $dish->load('materials');
                foreach ($dish->materials as $material) {
                    $normalized = OrderItemMaterial::normalizeToBaseUnit(
                        $material->pivot->quantity,
                        $material->pivot->unit_type
                    );
                    OrderItemMaterial::create([
                        'order_item_id' => $item->id,
                        'material_id'   => $material->id,
                        'quantity'      => $normalized['quantity'],
                        'unit_type'     => $normalized['unit_type'],
                    ]);
                }

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

            $addedItemIds = collect($addedItems)->pluck('id')->toArray();
            $skipPrint = $request->input('skip_print', false);
            if (!$skipPrint && !empty($addedItemIds)) {
                PrintOrderItemsJob::dispatch($order->id, $addedItemIds, 'add', $operatorId);
            }

            return response()->json([
                'success' => true,
                'message' => count($addedItems) . ' prodotti aggiunti con successo',
                'data' => [
                    'items_count' => count($addedItems),
                    'item_ids' => $addedItemIds,
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
        ]);

        // Verify operator token
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));
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

            // If the order has a pending preconto, cancel all pending splits
            if ($order->preconto_requested_at !== null) {
                PrecontoSplit::where('table_order_id', $order->id)
                    ->where('status', 'pending')
                    ->delete();
                $order->update(['preconto_requested_at' => null]);
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

            // Save material snapshot at order time (normalized to base units)
            $dish->load('materials');
            foreach ($dish->materials as $material) {
                $normalized = OrderItemMaterial::normalizeToBaseUnit(
                    $material->pivot->quantity,
                    $material->pivot->unit_type
                );
                OrderItemMaterial::create([
                    'order_item_id' => $item->id,
                    'material_id'   => $material->id,
                    'quantity'      => $normalized['quantity'],
                    'unit_type'     => $normalized['unit_type'],
                ]);
            }

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

            $skipPrint = $request->input('skip_print', false);
            if (!$skipPrint) {
                PrintOrderItemsJob::dispatch($order->id, [$item->id], 'add', $operatorId);
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
            $order->load('restaurantTable');

            // Load relations needed for printing BEFORE deletion
            $item->load('dish.category.printer', 'addedBy');

            $reason = request()->input('reason');
            // Log item removal before deletion
            $this->logger->logRemoveItem($item, $operatorId, $reason);

            $item->status = 'cancelled';
            $item->saveQuietly();
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

            PrintOrderItemsJob::dispatch($order->id, [$item->id], 'remove', $operatorId);

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

            $skipPrint = $request->input('skip_print', false);
            if (!$skipPrint) {
                PrintOrderItemsJob::dispatch($order->id, [$item->id], 'update', $operatorId);
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
                $order->update(['preconto_requested_at' => null]);
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
     * Log that the cash drawer was unreachable during a cash payment
     */
    public function logCashDrawerFailed(Request $request, RestaurantTable $table): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $order = $table->activeOrder;
        if (!$order) {
            // Ordine già chiuso: recupera l'ultimo ordine del tavolo
            $order = \App\Models\TableOrder::where('restaurant_table_id', $table->id)
                ->orderByDesc('id')
                ->first();
        }
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Ordine non trovato'], 404);
        }

        $order->close('contanti');
        $this->logger->logCashDrawerFailed($order, $operatorId);

        return response()->json(['success' => true]);
    }

    /**
     * Pay and close table order
     */
    public function payTable(Request $request, RestaurantTable $table): JsonResponse
    {
        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        $paymentMethod = $request->input('payment_method', 'pos');
        $allowedMethods = ['pos', 'contanti', 'fattura', 'fattura_contanti', 'fattura_pos', 'bonifico', 'assegno', 'misto', 'chiusura_conto'];
        if (!in_array($paymentMethod, $allowedMethods)) {
            $paymentMethod = 'pos';
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

            $this->logger->logPayOrder($order, $paymentMethod, $operatorId);
            $this->logger->logCloseOrder($order, $operatorId);

            $order->update(['preconto_requested_at' => null]);
            $order->close($paymentMethod);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conto incassato con successo',
                'data' => [
                    'total_paid' => $order->total_amount,
                    'table_order_id' => $order->id,
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
     * Pay table with invoice(s) — supports partial invoice + remainder via POS/Contanti
     */
    public function payTableInvoice(Request $request, RestaurantTable $table): JsonResponse
    {
        $validated = $request->validate([
            'invoices'                              => 'required|array|min:1',
            'invoices.*.amount'                     => 'required|numeric|min:0.01',
            'invoices.*.description'                => 'nullable|string|max:255',
            'invoices.*.user_type'                  => 'nullable|string|in:private,company,public_company',
            'invoices.*.customer_name'              => 'nullable|string|max:255',
            'invoices.*.customer_fiscal_code'       => 'nullable|string|max:50',
            'invoices.*.customer_vat_number'        => 'nullable|string|max:50',
            'invoices.*.customer_address'           => 'nullable|string|max:255',
            'invoices.*.customer_zip_code'          => 'nullable|string|max:10',
            'invoices.*.customer_city'              => 'nullable|string|max:100',
            'invoices.*.customer_province'          => 'nullable|string|max:5',
            'invoices.*.customer_codice_destinatario' => 'nullable|string|max:7',
            'invoices.*.customer_pec_destinatario'  => 'nullable|string|max:255',
            'invoices.*.customer_id'                => 'nullable|integer|exists:customers,id',
            'invoices.*.save_customer'              => 'nullable|boolean',
            'remaining_amount'                      => 'required|numeric|min:0',
            'remaining_method'                      => 'nullable|string|in:pos,contanti,bonifico,assegno',
            'payment_method'                        => 'nullable|string',
        ]);

        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        $order = $table->activeOrder;
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Nessun ordine attivo per questo tavolo',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $mySondFature = app(MysondFatturaService::class);
            $ficResults   = [];

            foreach ($validated['invoices'] as $invoiceData) {
                $description = $invoiceData['description'] ?: 'Pasto completo';

                // 1. Resolve or create Customer
                if (!empty($invoiceData['customer_id'])) {
                    $customer = Customer::find($invoiceData['customer_id']);
                } elseif (!empty($invoiceData['save_customer'])) {
                    $customer = Customer::create([
                        'user_type'            => $invoiceData['user_type'] ?? 'private',
                        'full_name'            => $invoiceData['customer_name'] ?? '',
                        'fiscal_code'          => $invoiceData['customer_fiscal_code'] ?? null,
                        'vat_number'           => $invoiceData['customer_vat_number'] ?? null,
                        'address'              => $invoiceData['customer_address'] ?? null,
                        'zip_code'             => $invoiceData['customer_zip_code'] ?? null,
                        'city'                 => $invoiceData['customer_city'] ?? null,
                        'province'             => $invoiceData['customer_province'] ?? null,
                        'codice_destinatario'  => $invoiceData['customer_codice_destinatario'] ?? null,
                        'pec_destinatario'     => $invoiceData['customer_pec_destinatario'] ?? null,
                    ]);
                } else {
                    // Temporary in-memory customer (not persisted)
                    $customer = new Customer([
                        'user_type'            => $invoiceData['user_type'] ?? 'private',
                        'full_name'            => $invoiceData['customer_name'] ?? '',
                        'fiscal_code'          => $invoiceData['customer_fiscal_code'] ?? null,
                        'vat_number'           => $invoiceData['customer_vat_number'] ?? null,
                        'address'              => $invoiceData['customer_address'] ?? null,
                        'zip_code'             => $invoiceData['customer_zip_code'] ?? null,
                        'city'                 => $invoiceData['customer_city'] ?? null,
                        'province'             => $invoiceData['customer_province'] ?? null,
                        'codice_destinatario'  => $invoiceData['customer_codice_destinatario'] ?? null,
                        'pec_destinatario'     => $invoiceData['customer_pec_destinatario'] ?? null,
                    ]);
                }

                // 2. Generate invoice code and increment counter
                $counter     = (int) Setting::get('invoice_counter', 0) + 1;
                Setting::set('invoice_counter', $counter, 'integer');
                $year        = now()->format('Y');
                $invoiceCode = 'ORD-' . $year . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
                $invoiceName = 'ORD' . $year . str_pad($counter, 4, '0', STR_PAD_LEFT);

                // 3. Calculate tax
                $vatRate = (float) Setting::get('invoice_vat_rate', 10);
                $imponibile = round((float) $invoiceData['amount'] / (1 + $vatRate / 100), 2);
                $tax = round((float) $invoiceData['amount'] - $imponibile, 2);

                // 4. Create TableOrderInvoice record
                $tableOrderInvoice = TableOrderInvoice::create([
                    'table_order_id'   => $order->id,
                    'customer_id'      => $customer->id ?? null,
                    'invoice_code'     => $invoiceCode,
                    'invoice_name'     => $invoiceName,
                    'amount'           => $invoiceData['amount'],
                    'discount'         => 0,
                    'tax'              => $tax,
                    'description'      => $description,
                    'payment_method'   => $validated['payment_method'] ?? 'fattura',
                    'status'           => 'pending',
                ]);

                // Attach in-memory customer so InvoiceService can access $invoice->user
                $tableOrderInvoice->setRelation('customer', $customer);

                // 5. Generate XML and send via Mysond
                $result = $mySondFature->createInvoice($tableOrderInvoice);

                // 6. Update invoice record with result
                $updateData = [
                    'mysond_response' => is_array($result) ? json_encode($result) : (string) $result,
                ];
                if (($result['response'] ?? '') === 'success') {
                    $updateData['status']       = 'sent';
                    $updateData['sent_at']      = now();
                    $updateData['xml_path']     = $result['path'] ?? null;
                    $updateData['xml_content']  = $result['content'] ?? null;
                    $ficResults[] = $result;
                } else {
                    $updateData['status'] = 'error';
                }
                $tableOrderInvoice->update($updateData);

                // Log invoice creation including outcome
                $this->logger->logCreateInvoice($order, $invoiceData, $operatorId, $result);
            }

            $totalInvoiced = collect($validated['invoices'])->sum('amount');
            $remaining     = (float) $validated['remaining_amount'];

            // Determine overall payment method
            if ($remaining > 0.01) {
                $paymentMethod = 'misto';
            } else {
                $paymentMethod = $validated['payment_method'] ?? 'fattura';
            }

            $this->logger->logPayOrder($order, $paymentMethod, $operatorId);
            $this->logger->logCloseOrder($order, $operatorId);

            $order->close($paymentMethod);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conto incassato con successo',
                'data' => [
                    'total_paid'    => $order->total_amount,
                    'invoiced'      => $totalInvoiced,
                    'remaining'     => $remaining,
                    'invoice_count' => count($validated['invoices']),
                    'fic_sent'      => count($ficResults),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing invoice payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nel pagamento con fattura',
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

            // Invia stampa marcia alla coda printers
            PrintMarciaJob::dispatch($order->id, $operatorId);

            // Log stampa marcia
            $this->logger->logPrintMarcia($order, $operatorId);

            return response()->json([
                'success' => true,
                'message' => 'Marcia tavolo inviata con successo',
            ]);
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
            'split_count'     => 'nullable|integer|min:1',
            'type'            => 'nullable|string|in:full,split,items',
            'items'           => 'nullable|array',
            'items.*.order_item_id' => 'required_with:items|integer',
            'items.*.quantity'      => 'required_with:items|integer|min:1',
            'covers'          => 'nullable|integer|min:0',
            'label'           => 'nullable|string|max:100',
            'discount_type'   => 'nullable|string|in:none,value,percent',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        try {
            $order = $table->activeOrder;
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Nessun ordine attivo per questo tavolo'], 404);
            }

            $order->load(['items.dish', 'restaurantTable']);

            $type = $validated['type'] ?? 'full';

            if ($type === 'items' && !empty($validated['items'])) {
                // ── Partial preconto by selected items ───────────────────────────
                $itemIds = collect($validated['items'])->pluck('order_item_id');
                $orderItems = $order->items->whereIn('id', $itemIds->toArray())->keyBy('id');

                $splitItems = [];
                $splitTotal = 0;
                foreach ($validated['items'] as $sel) {
                    $item = $orderItems[$sel['order_item_id']] ?? null;
                    if (!$item) continue;
                    $qty = min($sel['quantity'], $item->quantity);
                    $unitPrice = (float) $item->unit_price;
                    $subtotal = round($unitPrice * $qty, 2);
                    $splitItems[] = [
                        'order_item_id' => $item->id,
                        'dish_name'     => $item->dish->label ?? $item->dish->name ?? 'N/D',
                        'quantity'      => $qty,
                        'unit_price'    => $unitPrice,
                        'subtotal'      => $subtotal,
                    ];
                    $splitTotal += $subtotal;
                }

                $covers = (int) ($validated['covers'] ?? 0);

                // Validate covers: cannot exceed remaining (total - already assigned in pending splits)
                $assignedCovers = $order->precontoSplits()->where('status', 'pending')->sum('covers');
                $remainingCovers = max(0, $order->covers - $assignedCovers);
                if ($covers > $remainingCovers) {
                    return response()->json([
                        'success' => false,
                        'message' => "Coperti non validi: massimo $remainingCovers disponibili",
                    ], 422);
                }

                $coverCharge = $covers > 0 ? ($order->getCoverChargePerPerson() * $covers) : 0;
                $splitTotal += $coverCharge;

                // Discount
                $discountType   = $validated['discount_type'] ?? 'none';
                $discountAmount = (float) ($validated['discount_amount'] ?? 0);
                $discountValue  = 0;
                if ($discountType === 'value' && $discountAmount > 0) {
                    $discountValue = min($discountAmount, $splitTotal);
                } elseif ($discountType === 'percent' && $discountAmount > 0) {
                    $pct = min($discountAmount, 100);
                    $discountValue = round($splitTotal * $pct / 100, 2);
                }
                $splitTotal = max(0, round($splitTotal - $discountValue, 2));

                // Count existing splits to generate label
                $splitNumber = $order->precontoSplits()->count() + 1;
                $label = $validated['label'] ?? "Preconto $splitNumber";

                $split = \App\Models\PrecontoSplit::create([
                    'table_order_id' => $order->id,
                    'label'          => $label,
                    'items'          => $splitItems,
                    'covers'         => $covers,
                    'total'          => $splitTotal,
                    'discount_type'  => $discountType,
                    'discount_amount' => $discountAmount,
                    'discount_value' => $discountValue,
                    'status'         => 'pending',
                ]);

                PrintPrecontoJob::dispatch($order->id, $operatorId, null, null, 0, $split->id);
                $this->logger->logPrintPreconto($order, $operatorId, null, ['split_id' => $split->id, 'label' => $label]);
                $order->update(['preconto_requested_at' => now()]);
                return response()->json([
                    'success' => true,
                    'message' => "Preconto parziale \"$label\" stampato (€" . number_format($splitTotal, 2) . ")",
                    'data'    => ['split_id' => $split->id, 'total' => $splitTotal],
                ]);
            }

            // ── Full or split-by-count preconto ──────────────────────────────
            $splitCount     = ($type === 'split') ? ($validated['split_count'] ?? null) : null;
            $discountType   = $validated['discount_type'] ?? 'none';
            $discountAmount = (float) ($validated['discount_amount'] ?? 0);
            Log::info(__METHOD__ . ': ' . __LINE__);
            PrintPrecontoJob::dispatch($order->id, $operatorId, $splitCount, $discountType, $discountAmount);
            Log::info(__METHOD__ . ': ' . __LINE__);
            $this->logger->logPrintPreconto($order, $operatorId, $splitCount);
            $order->update(['preconto_requested_at' => now()]);
            $message = 'PreConto stampato con successo';
            if ($splitCount && $splitCount > 1) {
                $message .= " (diviso per $splitCount persone)";
            }
            return response()->json(['success' => true, 'message' => $message]);

        } catch (\Exception $e) {
            Log::error('Error printing preconto: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nella stampa del PreConto'], 500);
        }
    }

    /**
     * Get pending preconto splits for a table order
     */
    public function getPrecontoSplits(RestaurantTable $table): JsonResponse
    {
        $order = $table->activeOrder;
        if (!$order) {
            return response()->json(['success' => true, 'data' => ['splits' => [], 'remaining' => 0, 'order_total' => 0]]);
        }

        $splits = $order->precontoSplits()->orderBy('created_at')->get();
        $paidTotal = $splits->where('status', 'paid')->sum('total');
        $effectiveTotal = $order->hasDiscount() ? $order->getDiscountedTotal() : (float) $order->total_amount;
        $remaining = max(0, round($effectiveTotal - $paidTotal, 2));

        return response()->json([
            'success' => true,
            'data' => [
                'splits' => $splits->map(fn($s) => [
                    'id'             => $s->id,
                    'label'          => $s->label,
                    'items'          => $s->items,
                    'covers'         => $s->covers,
                    'total'          => (float) $s->total,
                    'status'         => $s->status,
                    'payment_method' => $s->payment_method,
                ])->values(),
                'remaining'   => $remaining,
                'order_total' => (float) $order->total_amount,
            ],
        ]);
    }

    /**
     * Pay a single preconto split
     */
    public function payPrecontoSplit(Request $request, RestaurantTable $table, \App\Models\PrecontoSplit $split): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $paymentMethod = $request->input('payment_method', 'pos');
        $allowedMethods = ['pos', 'contanti', 'fattura', 'fattura_contanti', 'fattura_pos', 'bonifico', 'assegno', 'misto', 'chiusura_conto'];
        if (!in_array($paymentMethod, $allowedMethods)) {
            $paymentMethod = 'pos';
        }

        try {
            DB::beginTransaction();

            $order = $table->activeOrder;
            if (!$order || $split->table_order_id !== $order->id) {
                return response()->json(['success' => false, 'message' => 'Split non valido per questo ordine'], 404);
            }

            $split->update(['status' => 'paid', 'payment_method' => $paymentMethod, 'paid_at' => now()]);

            // Check if all splits paid and no remaining balance
            $order->refresh();
            $paidTotal = $order->precontoSplits->where('status', 'paid')->sum('total');
            $effectiveTotal = $order->hasDiscount() ? $order->getDiscountedTotal() : (float) $order->total_amount;
            $remaining = round($effectiveTotal - $paidTotal, 2);
            $orderClosed = false;

            $this->logger->logPayPrecontoSplit($order, $split, $paymentMethod, $operatorId);

            if ($remaining <= 0.01) {
                $this->logger->logPayOrder($order, $paymentMethod, $operatorId);
                $this->logger->logCloseOrder($order, $operatorId);
                $order->update(['preconto_requested_at' => null]);
                $order->close($paymentMethod);
                $orderClosed = true;
            }

            DB::commit();

            $splitPaidItems = collect($split->items ?? [])
                ->map(fn($item) => [
                    'order_item_id' => $item['order_item_id'] ?? null,
                    'paid_qty'      => (float) ($item['quantity'] ?? $item['qty'] ?? 1),
                ])
                ->filter(fn($item) => $item['order_item_id'] !== null)
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'message' => $orderClosed ? 'Conto chiuso completamente' : "Pagato €" . number_format($split->total, 2),
                'data'    => [
                    'order_closed'      => $orderClosed,
                    'table_order_id'    => $order->id,
                    'remaining'         => max(0, $remaining),
                    'paid_items'        => $splitPaidItems,
                    'paid_split_total'  => (float) $split->total,
                    'paid_cover_amount' => round((int) $split->covers * $order->getCoverChargePerPerson(), 2),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error paying split: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nel pagamento del preconto'], 500);
        }
    }

    /**
     * Delete a pending preconto split
     */
    public function deletePrecontoSplit(RestaurantTable $table, \App\Models\PrecontoSplit $split): JsonResponse
    {
        $order = $table->activeOrder;
        if (!$order || $split->table_order_id !== $order->id) {
            return response()->json(['success' => false, 'message' => 'Split non valido per questo ordine'], 404);
        }

        if ($split->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Impossibile eliminare un preconto già pagato'], 422);
        }

        $split->delete();

        // If no more splits, clear preconto_requested_at
        if ($order->precontoSplits()->count() === 0) {
            $order->update(['preconto_requested_at' => null]);
        }

        return response()->json(['success' => true, 'message' => 'Preconto eliminato']);
    }

    /**
     * Get all active banco orders
     */
    public function getBanco(): JsonResponse
    {
        try {
            $table = RestaurantTable::with(['activeOrders.items.dish'])
                ->where('is_banco', true)
                ->firstOrFail();

            $orders = $table->activeOrders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'total_amount' => $order->total_amount,
                    'opened_at' => $order->opened_at?->toIso8601String(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'table_id' => $table->id,
                    'orders' => $orders,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching banco: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nel caricamento del banco'], 500);
        }
    }

    /**
     * Open a new order on the banco (covers = 0) — always creates a new one
     */
    public function openBanco(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'operator_token' => 'required|string',
        ]);

        $operatorId = $this->verifyOperatorToken($validated['operator_token']);
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        try {
            DB::beginTransaction();

            $table = RestaurantTable::where('is_banco', true)->firstOrFail();

            $order = TableOrder::create([
                'restaurant_table_id' => $table->id,
                'covers' => 0,
                'status' => 'open',
                'waiter_id' => $operatorId,
            ]);

            $order->updateTotal();
            $table->update(['status' => 'occupied']);
            $this->logger->logCreateOrder($order, $operatorId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vendita al banco aperta',
                'data' => ['order_id' => $order->id],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error opening banco: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nell\'apertura del banco'], 500);
        }
    }

    /**
     * Get details for a specific order (used for banco multi-session)
     */
    public function getOrderDetails(TableOrder $order): JsonResponse
    {
        try {
            $order->load(['items.dish', 'items.autoconsumoUser', 'restaurantTable']);

            $items = $order->items->map(function ($item) {
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
                    'autoconsumo_user_id' => $item->autoconsumo_user_id,
                    'autoconsumo_user_name' => $item->autoconsumoUser?->name,
                ];
            });

            $table = $order->restaurantTable;

            return response()->json([
                'success' => true,
                'data' => [
                    'table' => [
                        'id' => $table->id,
                        'table_number' => $table->is_banco ? 'BANCO' : $table->table_number,
                        'status' => $table->status,
                        'is_banco' => (bool) $table->is_banco,
                    ],
                    'order' => [
                        'id' => $order->id,
                        'covers' => $order->covers,
                        'autoconsumo' => (bool) $order->autoconsumo,
                        'has_preconto' => $order->preconto_requested_at !== null,
                        'items_subtotal' => $order->getItemsSubtotal(),
                        'cover_charge_per_person' => $order->getCoverChargePerPerson(),
                        'cover_charge_total' => $order->getCoverChargeAmount(),
                        'has_cover_charge' => $order->hasCoverCharge(),
                        'total_amount' => $order->total_amount,
                        'items' => $items,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching order details: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nel caricamento dell\'ordine'], 500);
        }
    }

    /**
     * Reprint all items of the current order, grouped by printer
     */
    public function openCashDrawer(Request $request): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }
        $amount = $request->input('amount');
        $table_order_id = $request->input('table_order_id');
        $printer = Setting::getCashDrawerPrinter();
        if (!$printer) {
            return response()->json(['success' => false, 'message' => 'Nessuna stampante cassa configurata'], 422);
        }

        $opName = User::find($operatorId)?->name ?? 'auth_login';
        $opened = $this->printerService->openCashDrawer($printer, $amount, $opName);

        CashDrawerLog::create([
            'table_order_id' => $table_order_id ?: null,
            'operation_id'   => $opened['operation_id'] ?? null,
            'event_type'     => $opened['response'] ? 'start' : 'error',
            'payload'        => ['amount' => $amount, 'response' => $opened],
        ]);

        if ($opened['response'] && $table_order_id) {
            TableOrder::where('id', $table_order_id)
                ->update(['cash_drawer_operation_id' => $opened['operation_id']]);
        }

        return response()->json([
            'success'      => $opened['response'],
            'operation_id' => $opened['operation_id'] ?? null,
            'message'      => $opened['response'] ? 'Pagamento avviato' : 'Impossibile avviare il pagamento',
        ]);
    }

    public function pollCashDrawer(Request $request): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $operationId = $request->input('operation_id');
        $printer = Setting::getCashDrawerPrinter();
        if (!$printer) {
            return response()->json(['success' => false, 'message' => 'Nessuna stampante cassa configurata'], 422);
        }

        $result = $this->printerService->pollCashDrawer($printer, $operationId);
        $last_log = CashDrawerLog::where('operation_id', $operationId)
            ->whereNotNull('table_order_id')
            ->orderBy('created_at', 'desc')
            ->first();
        $table_order_id = $last_log->table_order_id ?? null;
        if ($result['payment_status'] === 1) {
            CashDrawerLog::create([
                'operation_id' => $operationId,
                'event_type'   => 'completed',
                'payload'      => $result,
                'table_order_id' => $table_order_id,
            ]);
        }

        return response()->json([
            'success'         => $result['success'],
            'payment_status'  => $result['payment_status'],
            'payment_details' => $result['payment_details'] ?? null,
        ]);
    }

    public function cancelCashDrawer(Request $request): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $operationId = $request->input('operation_id');
        $printer = Setting::getCashDrawerPrinter();
        if (!$printer) {
            return response()->json(['success' => false, 'message' => 'Nessuna stampante cassa configurata'], 422);
        }

        $result = $this->printerService->cancelCashDrawer($printer, $operationId);
        $last_log = CashDrawerLog::where('operation_id', $operationId)
            ->whereNotNull('table_order_id')
            ->orderBy('created_at', 'desc')
            ->first();
        $table_order_id = $last_log->table_order_id ?? null;

        CashDrawerLog::create([
            'operation_id' => $operationId,
            'event_type'   => 'cancel',
            'payload'      => $result,
            'table_order_id' => $table_order_id,
        ]);

        return response()->json(['success' => $result['success']]);
    }

    public function applyDiscount(Request $request, RestaurantTable $table): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $validated = $request->validate([
            'discount_type'   => 'required|string|in:percent,value',
            'discount_amount' => 'required|numeric|min:0',
            'original_total'  => 'required|numeric|min:0',
            'final_total'     => 'required|numeric|min:0',
        ]);

        $order = $table->activeOrder;
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Nessun ordine attivo'], 404);
        }

        $order->applyDiscount($validated['discount_type'], (float) $validated['discount_amount']);

        $this->logger->logApplyDiscount(
            $order,
            $validated['discount_type'],
            (float) $validated['discount_amount'],
            (float) $validated['original_total'],
            (float) $validated['final_total'],
            $operatorId,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'discount_type'   => $order->discount_type,
                'discount_amount' => (float) $order->discount_amount,
                'discount_value'  => (float) $order->discount_value,
                'discounted_total' => $order->getDiscountedTotal(),
            ],
        ]);
    }

    public function resetDiscount(Request $request, RestaurantTable $table): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $order = $table->activeOrder;
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Nessun ordine attivo'], 404);
        }

        $order->clearDiscount();

        return response()->json(['success' => true]);
    }

    public function reprintOrder(RestaurantTable $table): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        try {
            $order = $table->activeOrder;
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Nessun ordine attivo per questo tavolo'], 404);
            }

            $order->load(['items.dish.category.printer', 'restaurantTable']);

            if ($order->items->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Nessun articolo nell\'ordine'], 400);
            }

            $itemIds = $order->items->pluck('id')->toArray();
            PrintOrderItemsJob::dispatch($order->id, $itemIds, 'reprint', $operatorId);
            return response()->json(['success' => true, 'message' => 'Ordine in coda per la ristampa']);
        } catch (\Exception $e) {
            Log::error('Error reprinting order: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nella ristampa dell\'ordine'], 500);
        }
    }

    /**
     * Move all items from one table to another
     */
    public function moveTable(Request $request, RestaurantTable $table): JsonResponse
    {
        $validated = $request->validate([
            'destination_table_id' => 'required|exists:restaurant_tables,id|different:id',
        ]);

        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $destinationTable = RestaurantTable::findOrFail($validated['destination_table_id']);

        if ($destinationTable->id === $table->id) {
            return response()->json(['success' => false, 'message' => 'Tavolo sorgente e destinazione coincidono'], 422);
        }

        try {
            DB::beginTransaction();

            $sourceOrder = $table->activeOrder;
            if (!$sourceOrder) {
                return response()->json(['success' => false, 'message' => 'Nessun ordine attivo sul tavolo sorgente'], 404);
            }

            $sourceOrder->load(['items.dish.category.printer', 'restaurantTable']);

            // Stampa spostamento PRIMA di modificare i dati
            $this->printerService->setOperatorId($operatorId)->printSpostamento($sourceOrder, $destinationTable, $operatorId);

            $destOrder = $destinationTable->activeOrder;
            if ($destOrder) {
                // Tavolo destinazione occupato: sposta gli items sull'ordine esistente
                $sourceOrder->items()->update(['table_order_id' => $destOrder->id]);
                $destOrder->updateTotal();
                $sourceOrder->delete();
            } else {
                // Tavolo destinazione libero: sposta l'ordine aggiornando il tavolo
                $sourceOrder->restaurant_table_id = $destinationTable->id;
                $sourceOrder->save();
                $destOrder = $sourceOrder;
                $destinationTable->update(['status' => 'occupied']);
            }

            $table->update(['status' => 'free']);

            DB::commit();

            $this->logger->logMoveTable($sourceOrder, $destOrder, $operatorId);

            return response()->json([
                'success' => true,
                'message' => "Tavolo {$table->table_number} spostato sul tavolo {$destinationTable->table_number}",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore spostamento tavolo: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nello spostamento del tavolo'], 500);
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

            // Add coperto order_item if a coperto dish is configured
            $this->syncCopertoItem($order, $validated['covers'], $operatorId);

            // Update total (cover charge already in order_item if coperto_dish_id is set)
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

            // Invia comunicazione alla coda printers
            PrintComunicaJob::dispatch($printer->id, $validated['message'], $operatorId, $tableOrder?->id);
            return response()->json([
                'success' => true,
                'message' => 'Comunicazione inviata in coda per ' . $printer->label,
            ]);
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
     * Update covers for an active order
     */
    public function updateCovers(Request $request, RestaurantTable $table): JsonResponse
    {
        $validated = $request->validate([
            'covers' => 'required|integer|min:0',
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

            DB::beginTransaction();

            $oldCovers = $order->covers;
            $order->covers = $validated['covers'];
            $order->save();

            // Sync coperto order_item with new covers count
            $this->syncCopertoItem($order, $validated['covers'], $operatorId);

            $order->updateTotal();

            $this->logger->logUpdateCovers($order, $oldCovers, $validated['covers'], $operatorId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Coperti aggiornati',
                'data' => [
                    'covers' => $order->covers,
                    'cover_charge_total' => $order->getCoverChargeAmount(),
                    'total_amount' => $order->fresh()->total_amount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating covers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento dei coperti',
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

            $motivation = $request->input('motivation');
            // Log only if price actually changed
            if (abs((float) $dataBefore['unit_price'] - (float) $validated['unit_price']) > 0.001) {
                $this->logger->logUpdateItemPrice($item, $dataBefore['unit_price'], $validated['unit_price'], $operatorId, $motivation);
            }

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

    /**
     * Update notes, extras, removals of an existing order item
     */
    public function updateItemDetails(Request $request, OrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'notes'    => 'nullable|string|max:500',
            'extras'   => 'nullable|array',
            'removals' => 'nullable|array',
        ]);

        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        try {
            DB::beginTransaction();

            $item->notes    = $validated['notes'] ?? null;
            $item->extras   = !empty($validated['extras'])   ? $validated['extras']   : null;
            $item->removals = !empty($validated['removals']) ? $validated['removals'] : null;

            // Recalculate subtotal with new extras
            $extrasTotal = !empty($item->extras) ? array_sum($item->extras) : 0;
            $item->subtotal = round(($item->unit_price + $extrasTotal) * $item->quantity, 2);
            $item->save();

            $order = $item->order;
            $order->updateTotal();

            if (!empty($validated['notes'])) {
                $this->logger->logAddItemNotes($item, $validated['notes'], $operatorId);
            }
            if (!empty($validated['extras'])) {
                $this->logger->logAddItemExtras($item, $validated['extras'], $operatorId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Piatto aggiornato',
                'data'    => ['item' => $item->fresh(['dish']), 'order_total' => $order->fresh()->total_amount],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating item details: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nell\'aggiornamento del piatto'], 500);
        }
    }

    /**
     * Insert a segue separator item after a given order item.
     * No operator auth required — segue items carry no price.
     */
    public function addSegueItem(Request $request, RestaurantTable $table): JsonResponse
    {
        $validated = $request->validate(['after_item_id' => 'required|integer']);

        try {
            $order = $table->activeOrder;
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Nessun ordine aperto'], 404);
            }

            $afterItem = OrderItem::where('table_order_id', $order->id)
                ->where('id', $validated['after_item_id'])
                ->firstOrFail();

            // Shift all items with sort_order > afterItem->sort_order up by 1
            OrderItem::where('table_order_id', $order->id)
                ->where('sort_order', '>', $afterItem->sort_order)
                ->increment('sort_order');

            // Create the segue separator item
            $segueItem = OrderItem::create([
                'table_order_id' => $order->id,
                'dish_id'        => null,
                'quantity'       => 1,
                'unit_price'     => 0,
                'subtotal'       => 0,
                'segue'          => true,
                'status'         => 'pending',
                'sort_order'     => $afterItem->sort_order + 1,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id'         => $segueItem->id,
                    'dish_id'    => null,
                    'dish_name'  => null,
                    'quantity'   => 1,
                    'unit_price' => 0,
                    'subtotal'   => 0,
                    'segue'      => true,
                    'sort_order' => $segueItem->sort_order,
                    'notes'      => null,
                    'extras'     => null,
                    'removals'   => null,
                    'status'     => 'pending',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding segue item: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nell\'aggiunta del segue'], 500);
        }
    }

    /**
     * Delete a segue separator item.
     * No operator auth or print job — segue items carry no price.
     */
    public function removeSegueItem(OrderItem $item): JsonResponse
    {
        if (!$item->isSegueItem()) {
            return response()->json(['success' => false, 'message' => 'Item non è un segue'], 400);
        }

        try {
            $item->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error removing segue item: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nella rimozione del segue'], 500);
        }
    }

    /**
     * Change the dish of an existing order item and print a STORNO/AGGIUNTA slip
     */
    public function updateItemDish(Request $request, OrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'dish_id' => 'required|integer|exists:dishes,id',
        ]);

        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        try {
            DB::beginTransaction();

            // Load item with old dish relations before changing
            $item->load('dish.category.printer', 'addedBy');
            $order = $item->order;
            $order->load('restaurantTable');

            $oldDishId    = $item->dish_id;
            $oldDishName  = $item->dish->label ?? 'N/D';
            $oldPrinter   = $item->dish->category->printer ?? null;
            $oldUnitPrice = (float) $item->unit_price;

            // Load new dish
            $newDish = Dish::findOrFail($validated['dish_id']);

            // Update item
            $item->dish_id    = $newDish->id;
            $item->unit_price = $newDish->price;
            $extrasTotal = !empty($item->extras) ? array_sum($item->extras) : 0;
            $item->subtotal = round(($newDish->price + $extrasTotal) * $item->quantity, 2);
            $item->save();

            $order->updateTotal();

            $this->logger->logChangeDish(
                $item,
                $oldDishId,
                $oldDishName,
                $oldUnitPrice,
                $newDish->id,
                $newDish->label,
                (float) $newDish->price,
                $operatorId
            );

            DB::commit();

            // Print after commit
            PrintDishChangeJob::dispatch($order->id, $item->id, $oldDishName, $oldPrinter?->id, $operatorId);

            return response()->json([
                'success' => true,
                'message' => 'Piatto modificato',
                'data'    => ['item' => $item->fresh(['dish']), 'order_total' => $order->fresh()->total_amount],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating item dish: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore nel cambio piatto'], 500);
        }
    }

    /**
     * Send a purchase request to the local POS terminal (VEGA3000 via JSONPOS TCP).
     * Does NOT close the order — the frontend calls /pay afterwards on success.
     */
    public function posCharge(RestaurantTable $table): JsonResponse
    {
        try {
            $order = $table->activeOrder;
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nessun ordine attivo per questo tavolo',
                ], 404);
            }

            $posService = app(VegaPosService::class);

            if (!$posService->isConfigured()) {
                // POS not configured — let the frontend proceed with the normal payment flow
                return response()->json([
                    'success'     => true,
                    'pos_skipped' => true,
                    'message'     => 'Terminale POS non configurato — pagamento manuale',
                ]);
            }

            $result = $posService->sendPurchase(
                (float) $order->total_amount,
                'ORD-' . $order->id
            );

            return response()->json([
                'success'       => $result['success'],
                'pos_skipped'   => false,
                'response_code' => $result['response_code'],
                'message'       => $result['message'],
            ], $result['success'] ? 200 : 402);

        } catch (\Exception $e) {
            Log::error('Error in posCharge: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore comunicazione POS',
            ], 500);
        }
    }

    public function freeAmount(RestaurantTable $table): JsonResponse
    {
        // Verify operator token from header
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Token operatore non valido',
            ], 401);
        }

        $type = request()->input('type', 'full'); // 'full' or 'partial'
        $assignments = request()->input('assignments', []); // [{item_id, user_id}]

        try {
            DB::beginTransaction();

            $order = $table->activeOrder;
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Nessun ordine attivo'], 404);
            }

            if ($type === 'partial' && !empty($assignments)) {
                // Apply per-item assignments with optional partial quantity
                $assignmentMap = collect($assignments)->keyBy('item_id');
                $order->items()->update(['autoconsumo_user_id' => null]);

                foreach ($order->items()->get() as $item) {
                    if (!$assignmentMap->has($item->id)) continue;

                    $assignment = $assignmentMap[$item->id];
                    $autoconsumoQty = min((int)($assignment['quantity'] ?? $item->quantity), $item->quantity);

                    if ($autoconsumoQty <= 0) continue;

                    if ($autoconsumoQty >= $item->quantity) {
                        // Full item → mark for autoconsumo
                        $item->update(['autoconsumo_user_id' => $assignment['user_id']]);
                    } else {
                        // Partial qty → reduce item, mark the reduced portion implicitly via logging
                        $item->quantity = $item->quantity - $autoconsumoQty;
                        $item->subtotal = round((float)$item->unit_price * $item->quantity, 2);
                        $item->autoconsumo_user_id = null;
                        $item->save();
                        // Create a temporary marker for the autoconsumo portion so logger can record it
                        $assignment['actual_quantity'] = $autoconsumoQty;
                        $assignmentMap->put($item->id, $assignment);
                    }
                }

                $this->logger->logPartialAutoconsumo($order, $assignments, $operatorId);

                // Non eliminiamo gli item, solo recalcoliamo il totale
                $order->refresh();
                $order->recalculateTotal();
                $order->refresh();

                // Chiudi l'ordine, settando autoconsumo e liberando il tavolo
                $order->update([
                    'autoconsumo' => 1,
                    'status' => 'paid',
                    'closed_at' => now(),
                ]);
                $table->update(['status' => 'free']);
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Autoconsumo registrato con successo. L\'ordine è stato chiuso.']);

            } else {
                // Full autoconsumo: mark all items as autoconsumo
                $order->items()->update(['autoconsumo_user_id' => null]);
                $this->logger->logFreeAmount($order, $operatorId);

                // Chiudi l'ordine senza eliminare gli item
                $order->update([
                    'autoconsumo' => 1,
                    'status' => 'paid',
                    'closed_at' => now(),
                ]);
                $table->update(['status' => 'free']);
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Autoconsumo registrato con successo. L\'ordine è stato chiuso.']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in freeAmount: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'autoconsumo',
            ], 500);
        }
    }

    public function cancelAutoconsumo(RestaurantTable $table): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $order = $table->activeOrder;
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Nessun ordine attivo'], 404);
        }

        $this->logger->logAutoconsumoCancel($order, $operatorId);

        return response()->json(['success' => true]);
    }

    /**
     * Create, update or delete the coperto order_item based on covers count.
     * Does nothing if no coperto_dish_id setting is configured.
     */
    protected function syncCopertoItem(TableOrder $order, int $covers, int $operatorId): void
    {
        $coperto_dish_id = (int) Setting::get('coperto_dish_id', 0);
        if (!$coperto_dish_id) {
            return;
        }

        $dish = Dish::find($coperto_dish_id);
        if (!$dish) {
            return;
        }

        $copertoItem = $order->items()->where('dish_id', $coperto_dish_id)->first();

        if ($covers <= 0) {
            // Drinks mode: remove coperto item
            if ($copertoItem) {
                $copertoItem->forceDelete();
            }
            return;
        }

        if ($copertoItem) {
            // Update quantity to match new covers count
            $copertoItem->quantity  = $covers;
            $copertoItem->subtotal  = round($dish->price * $covers, 2);
            $copertoItem->save();
        } else {
            // Create coperto item
            OrderItem::create([
                'table_order_id' => $order->id,
                'dish_id'        => $coperto_dish_id,
                'added_by'       => $operatorId,
                'quantity'       => $covers,
                'unit_price'     => $dish->price,
                'subtotal'       => round($dish->price * $covers, 2),
                'status'         => 'served',
            ]);
        }
    }

    /**
     * Print all pending changes after a batch modify session
     */
    public function printSession(Request $request, RestaurantTable $table): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->input('operator_token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token non valido'], 401);
        }

        $order = $table->activeOrder;
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Nessun ordine attivo'], 404);
        }

        $newItemIds     = $request->input('new_item_ids', []);
        $updatedItemIds = $request->input('updated_item_ids', []);

        if (!empty($newItemIds)) {
            PrintOrderItemsJob::dispatch($order->id, $newItemIds, 'add', $operatorId);
        }
        if (!empty($updatedItemIds)) {
            PrintOrderItemsJob::dispatch($order->id, $updatedItemIds, 'update', $operatorId);
        }

        return response()->json(['success' => true]);
    }
}
