<?php

namespace App\Http\Controllers\Frontoffice;

use App\Facades\Utils;
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
use App\Models\InvoiceMysondLog;
use App\Models\Setting;
use App\Models\TableOrder;
use App\Models\TableOrderInvoice;
use App\Models\User;
use App\Interfaces\ReceiptIssuerInterface;
use App\Services\MysondFatturaService;
use App\Support\IssuedReceiptDto;
use App\Services\RevolutPaymentCloser;
use App\Services\RevolutTerminalService;
use App\Services\TableOrderLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TableOrderController extends Controller
{
    protected TableOrderLoggerService $logger;
    protected PrinterServiceInterface $printerService;
    protected ReceiptIssuerInterface $receiptIssuer;

    public function __construct(
        TableOrderLoggerService $logger,
        PrinterServiceInterface $printerService,
        ReceiptIssuerInterface $receiptIssuer,
    ) {
        $this->logger = $logger;
        $this->printerService = $printerService;
        $this->receiptIssuer = $receiptIssuer;
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
                        'was_printed' => !is_null($item->first_printed_at),
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
                OrderItem::whereIn('id', $addedItemIds)
                    ->whereNull('first_printed_at')
                    ->update(['first_printed_at' => now()]);
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
                OrderItem::where('id', $item->id)
                    ->whereNull('first_printed_at')
                    ->update(['first_printed_at' => now()]);
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
     * Modify an existing order item: quantity and/or unit price in a single call.
     * - Decrement (qty < old) on already-printed items: requires reason and prints STORNO of |delta|.
     * - Increment (qty > old) on already-printed items: prints MODIFICA of +delta at the new price.
     * - Items never sent to kitchen (first_printed_at is null) are updated silently with no print.
     * - new_quantity == 0 deletes the item (and frees the table if it becomes empty).
     */
    public function modifyItem(Request $request, OrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'new_quantity'   => 'required|integer|min:0',
            'new_unit_price' => 'required|numeric|min:0',
            'reason'         => 'nullable|string|max:255',
        ]);

        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $newQty   = (int) $validated['new_quantity'];
        $newPrice = (float) $validated['new_unit_price'];
        $reason   = $validated['reason'] ?? null;

        $oldQty   = (int) $item->quantity;
        $oldPrice = (float) $item->unit_price;
        $delta    = $newQty - $oldQty;
        $wasPrinted   = $item->first_printed_at !== null;
        $priceChanged = abs($oldPrice - $newPrice) > 0.001;

        // Reason is mandatory only when decrementing/removing an already-printed item
        if ($wasPrinted && $newQty < $oldQty && empty($reason)) {
            return response()->json(['success' => false, 'message' => 'Motivazione obbligatoria per decremento o rimozione di voce già trasmessa'], 422);
        }

        try {
            DB::beginTransaction();

            $order = $item->order;
            $order->load('restaurantTable');
            $item->load('dish.category.printer', 'addedBy');

            // Case A: full removal
            if ($newQty === 0) {
                $this->logger->logRemoveItem($item, $operatorId, $reason);
                $item->status = 'cancelled';
                $item->saveQuietly();
                $item->delete();

                if ($order->items()->count() === 0) {
                    $table = $order->restaurantTable;
                    $table->update(['status' => 'free']);
                    $this->logger->logDeleteOrder($order, $operatorId);
                    $order->delete();
                }

                DB::commit();

                if ($wasPrinted) {
                    PrintOrderItemsJob::dispatch(
                        $order->id, [$item->id], 'remove', $operatorId,
                        [$item->id => $oldQty]
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Voce rimossa',
                    'data'    => ['removed' => true, 'order_total' => $order->fresh()?->total_amount],
                ]);
            }

            // Case B: update quantity and/or price
            if ($delta !== 0) {
                $this->logger->logUpdateItemQuantity($item, $oldQty, $newQty, $operatorId);
            }
            if ($priceChanged) {
                $this->logger->logUpdateItemPrice($item, $oldPrice, $newPrice, $operatorId);
            }

            $item->quantity   = $newQty;
            $item->unit_price = $newPrice;
            $item->save(); // recalculates subtotal

            DB::commit();

            // Print only when item was already sent to kitchen
            if ($wasPrinted && $delta !== 0) {
                $absDelta  = abs($delta);
                $operation = $delta > 0 ? 'update' : 'remove';
                PrintOrderItemsJob::dispatch(
                    $order->id, [$item->id], $operation, $operatorId,
                    [$item->id => $absDelta]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Voce aggiornata',
                'data'    => [
                    'item'        => $item->fresh(['dish']),
                    'order_total' => $order->fresh()->total_amount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error modifying item: ' . $e->getMessage() . ' line ' . $e->getLine());
            return response()->json(['success' => false, 'message' => 'Errore nella modifica della voce'], 500);
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
            $order = TableOrder::where('restaurant_table_id', $table->id)
                ->orderByDesc('id')
                ->first();
        }
        Log::info("Order", $order->toArray());
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Ordine non trovato'], 404);
        }
        // Totale al netto di eventuali sconti applicati al tavolo
        $effectiveTotalAmount = $order->hasDiscount()
            ? $order->getDiscountedTotal()
            : (float) $order->total_amount;
        $order_total = Utils::price($effectiveTotalAmount);

        // Metodo di pagamento da usare per registrare l'incasso. Default 'contanti'
        // per retro-compat con chiamate senza payload.
        $allowedMethods = ['contanti', 'chiusura_conto'];
        $paymentMethod = $request->input('payment_method', 'contanti');
        if (!in_array($paymentMethod, $allowedMethods, true)) {
            $paymentMethod = 'contanti';
        }

        // Se la richiesta riguarda uno split (pagamento di un singolo preconto), applichiamo
        // la stessa semantica di payPrecontoSplit: chiudiamo SOLO lo split, l'ordine intero
        // viene chiuso esclusivamente quando il residuo è zero.
        $splitId = $request->input('split_id');
        $split   = $splitId ? \App\Models\PrecontoSplit::where('table_order_id', $order->id)->find($splitId) : null;

        $corrispettivoInfo = null;
        $wasOpen = $order->status === 'open';

        if ($split && $split->isPending()) {
            $split->update(['status' => 'paid', 'payment_method' => $paymentMethod, 'paid_at' => now()]);

            $order->refresh();
            $paidTotal      = $order->precontoSplits->where('status', 'paid')->sum('total');
            $effectiveTotal = $order->hasDiscount() ? $order->getDiscountedTotal() : (float) $order->total_amount;
            $remaining      = round($effectiveTotal - $paidTotal, 2);

            $this->logger->logPayPrecontoSplit($order, $split, $paymentMethod, $operatorId);

            if ($remaining <= 0.01 && $wasOpen) {
                $this->logger->logPayOrder($order, $paymentMethod, $operatorId);
                $this->logger->logCloseOrder($order, $operatorId);
                $order->update(['preconto_requested_at' => null]);
                $order->close($paymentMethod);
            }

            $this->logger->logCashDrawerFailed($order, $operatorId);

            $corrispettivoInfo = $this->buildCorrispettivoResponse(
                $this->receiptIssuer->emettiPerSplit($split, $paymentMethod, $operatorId)
            );
        } else {
            // Pagamento del tavolo intero: comportamento originale.
            if ($wasOpen) {
                $order->close($paymentMethod);
            }
            $this->logger->logCashDrawerFailed($order, $operatorId);

            $corrispettivoInfo = $this->buildCorrispettivoResponse(
                $this->receiptIssuer->emettiPerOrdine($order, $paymentMethod, $operatorId)
            );
        }

        if (config('logging.channels.telegram.handler_with.apiKey')) {
            try {
                $operator = User::find($operatorId);
                $operatorName = $operator?->name ?? "ID {$operatorId}";
                // Il tavolo #999 è il banco (vedi CLAUDE.md).
                $tableNumber = $table->table_number ?? null;
                $tableLabel  = ((int) $tableNumber === 999)
                    ? 'Ordine al banco'
                    : ('Tavolo ' . e($tableNumber ?? $table->id));

                $msg = "🚨 <b>CASSA CONTANTI NON RAGGIUNGIBILE</b>\n\n"
                    . "🪑 <b>{$tableLabel}</b>\n"
                    . "🧾 Ordine: <b>#{$order->id}</b> — {$order_total}\n";

                if ($split) {
                    // Pagamento di un singolo preconto: riportiamo label + totale del preconto.
                    $splitLabel = e($split->label ?: ('Preconto #' . $split->id));
                    $splitTotal = Utils::price((float) $split->total);
                    $msg .= "📋 Preconto: <b>{$splitLabel}</b> — <b>{$splitTotal}</b>\n";
                    if ($split->covers > 0) {
                        $coverTotalSplit = Utils::price((float) $order->getCoverChargePerPerson() * (int) $split->covers);
                        $msg .= "🍽️ Coperti preconto: <b>{$split->covers}</b> ({$coverTotalSplit})\n";
                    }
                }

                // Elenco SEMPRE presente di tutti i piatti del tavolo, con aggiunzioni
                // (con prezzo) e rimozioni di ogni piatto. Così la notifica contiene
                // abbastanza contesto per risalire al dettaglio dell'incasso.
                $order->loadMissing(['items.dish']);
                $orderItems = $order->items->filter(fn($i) => !$i->isSegueItem() && (float) $i->subtotal > 0);
                if ($orderItems->isNotEmpty()) {
                    $msg .= "\n<b>Piatti del tavolo:</b>\n";
                    foreach ($orderItems as $it) {
                        $qty   = (int) $it->quantity;
                        $name  = e((string) ($it->dish->label ?? $it->dish->name ?? 'Articolo'));
                        $sub   = Utils::price((float) $it->subtotal);
                        $msg  .= "• {$qty} × {$name} — {$sub}\n";

                        if (is_array($it->extras) && !empty($it->extras)) {
                            foreach ($it->extras as $eName => $ePrice) {
                                $msg .= "   ↳ " . e((string) $eName) . " (+" . Utils::price((float) $ePrice) . ")\n";
                            }
                        }
                        if (is_array($it->removals) && !empty($it->removals)) {
                            foreach ($it->removals as $rName) {
                                $msg .= "   ↳ − " . e((string) $rName) . "\n";
                            }
                        }
                    }
                    if ($order->hasCoverCharge()) {
                        $coverTot = Utils::price((float) $order->getCoverChargeAmount());
                        $coverPP  = Utils::price((float) $order->getCoverChargePerPerson());
                        $msg .= "• Coperti: {$order->covers} × {$coverPP} — {$coverTot}\n";
                    }
                    if ($order->hasDiscount()) {
                        $dType = $order->discount_type;
                        $dAmt  = (float) $order->discount_amount;
                        $dLabel = $dType === 'percent' ? number_format($dAmt, 0) . '%' : Utils::price($dAmt);
                        $msg .= "• Sconto applicato: " . e($dLabel) . " (−" . Utils::price((float) $order->discount_value) . ")\n";
                    }
                }

                $msg .= "\n👤 Operatore: <b>" . e($operatorName) . "</b>\n"
                      . "🕒 " . now()->format('d/m/Y H:i') . "\n\n"
                      . "Il conto è stato chiuso manualmente come CONTANTI senza conferma della cassa automatica.";

                Log::channel('telegram')->critical($msg);
            } catch (\Throwable $e) {
                Log::error('Telegram cash-drawer-failed notification error: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'corrispettivo' => $corrispettivoInfo,
                'split_id'      => $split?->id,
                'order_closed'  => $order->status === 'paid',
            ],
        ]);
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

            $corrispettivoInfo = $this->buildCorrispettivoResponse(
                $this->receiptIssuer->emettiPerOrdine($order, $paymentMethod, $operatorId)
            );

            return response()->json([
                'success' => true,
                'message' => 'Conto incassato con successo',
                'data' => [
                    'total_paid' => $order->total_amount,
                    'table_order_id' => $order->id,
                    'corrispettivo' => $corrispettivoInfo,
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
     * Costruisce il blocco 'corrispettivo' da inserire nella response API.
     * Accetta un DTO normalizzato indipendente dal provider (mysond/ditron).
     * Ritorna null se l'emissione è stata saltata (metodo escluso o provider disabilitato).
     */
    private function buildCorrispettivoResponse(?IssuedReceiptDto $receipt): ?array
    {
        return $receipt?->toResponseArray();
    }

    /**
     * Avvia un pagamento sul Revolut Terminal: crea l'order, lo spinge al terminale,
     * mette il TableOrder in stato 'pending_payment'.
     *
     * Se l'integrazione POS è 'none', risponde { pos_skipped: true } e il frontend
     * fa il fallback al /pay diretto (chiusura manuale come "POS").
     */
    public function posPayStart(RestaurantTable $table, RevolutTerminalService $revolut): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $order = $table->activeOrder;
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Nessun ordine attivo per questo tavolo'], 404);
        }

        if (Setting::getPosIntegration() !== 'revolut') {
            return response()->json([
                'success'     => true,
                'pos_skipped' => true,
                'message'     => 'Integrazione POS non configurata',
            ]);
        }

        if (!$revolut->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Revolut non configurato (manca API key o location)',
            ], 422);
        }

        if ($order->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => "L'ordine non è in stato aperto",
            ], 422);
        }

        $revolutOrderId = null;
        try {
            $terminals = array_values($revolut->listTerminals());
            if (empty($terminals)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nessun terminale Revolut disponibile per la location configurata',
                ], 422);
            }
            $terminal = $terminals[0];

            $amountFloat = (float) ($order->hasDiscount() ? $order->getDiscountedTotal() : $order->total_amount);
            $amountMinor = (int) round($amountFloat * 100);

            if ($amountMinor <= 0) {
                return response()->json(['success' => false, 'message' => 'Importo non valido'], 422);
            }

            $revolutOrder = $revolut->createOrder($amountMinor, 'EUR', 'tableorder-' . $order->id);
            $revolutOrderId = $revolutOrder['id'];
            if ($revolutOrderId === '') {
                return response()->json(['success' => false, 'message' => 'Errore creazione ordine Revolut'], 502);
            }

            $revolut->pushPayment($revolutOrderId, $terminal['id']);

            $order->update([
                'status'                     => 'pending_payment',
                'revolut_order_id'           => $revolutOrderId,
                'revolut_payment_state'      => 'pending',
                'revolut_payment_started_at' => now(),
                'revolut_operator_id'        => $operatorId,
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'revolut_order_id' => $revolutOrderId,
                    'terminal'         => $terminal['name'] ?: $terminal['id'],
                    'timeout_seconds'  => Setting::getRevolutConfig()['timeout_seconds'],
                    'amount'           => $amountFloat,
                    'mock_mode'        => $revolut->isMock(),
                ],
            ]);
        } catch (\Throwable $e) {
            // Tentativo di cleanup lato Revolut se l'ordine è stato creato ma il push è fallito
            if ($revolutOrderId) {
                try { $revolut->cancelPayment($revolutOrderId); } catch (\Throwable $ce) { /* best effort */ }
            }
            Log::error('posPayStart failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore avvio pagamento'], 502);
        }
    }

    /**
     * Annulla un pagamento Revolut in corso. Gestisce la race condition:
     * se il cliente ha pagato proprio mentre il cassiere annullava, l'ordine
     * viene chiuso normalmente (con corrispettivo emesso) e il frontend mostra
     * "pagamento già completato".
     */
    public function posPayCancel(RestaurantTable $table, RevolutTerminalService $revolut): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $order = $table->activeOrder;
        if (!$order || !$order->isPendingPayment() || !$order->revolut_order_id) {
            return response()->json([
                'success' => false,
                'message' => 'Nessun pagamento in attesa per questo tavolo',
            ], 422);
        }

        try {
            try {
                $revolut->cancelPayment($order->revolut_order_id);
            } catch (\Throwable $e) {
                // Se il cancel fallisce è probabile che il pagamento sia già completato:
                // chiariamo la verità interrogando lo stato.
                Log::warning('Revolut cancelPayment failed (verifying state): ' . $e->getMessage());
            }

            $status = $revolut->getOrderStatus($order->revolut_order_id);
            $state  = strtolower($status['state'] ?? '');

            if (in_array($state, ['completed', 'authorised'], true)) {
                $closed = app(RevolutPaymentCloser::class)->closeAfterPayment($order, $state);
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'state'         => 'completed',
                        'already_paid'  => true,
                        'total_paid'    => $closed['total_paid'],
                        'corrispettivo' => $this->buildCorrispettivoResponse($closed['corrispettivo']),
                    ],
                ]);
            }

            $order->update([
                'status'                     => 'open',
                'revolut_order_id'           => null,
                'revolut_payment_state'      => 'cancelled',
                'revolut_payment_started_at' => null,
                'revolut_operator_id'        => null,
            ]);

            return response()->json([
                'success' => true,
                'data'    => ['state' => 'cancelled'],
            ]);
        } catch (\Throwable $e) {
            Log::error('posPayCancel failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Errore annullamento pagamento'], 502);
        }
    }

    /**
     * MOCK ONLY: simula la conferma di pagamento (come se fosse arrivato il webhook
     * Revolut con stato 'completed'). Disponibile solo se mock_mode è attivo —
     * altrimenti risponde 403. Usato dal bottone "Simula pagamento OK" nell'overlay
     * cassiere per testare il flusso end-to-end senza un terminale fisico.
     */
    public function posPayMockComplete(RestaurantTable $table, RevolutTerminalService $revolut): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        if (!$revolut->isMock()) {
            return response()->json(['success' => false, 'message' => 'Mock mode non attiva'], 403);
        }

        $order = $table->activeOrder;
        if (!$order || !$order->isPendingPayment()) {
            return response()->json(['success' => false, 'message' => 'Nessun pagamento in attesa per questo tavolo'], 422);
        }

        $closed = app(RevolutPaymentCloser::class)->closeAfterPayment($order, 'completed');

        return response()->json([
            'success' => true,
            'data'    => [
                'state'         => 'completed',
                'mock'          => true,
                'total_paid'    => $closed['total_paid'],
                'corrispettivo' => $this->buildCorrispettivoResponse($closed['corrispettivo']),
            ],
        ]);
    }

    /**
     * Polling di stato del pagamento Revolut. Funziona da fallback se il webhook
     * non arriva, e disambigua quando l'ordine è effettivamente completato/fallito.
     */
    public function posPayStatus(RestaurantTable $table, RevolutTerminalService $revolut): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $order = $table->activeOrder;
        if (!$order) {
            // Tavolo già libero: il pagamento è andato a buon fine via webhook
            return response()->json([
                'success' => true,
                'data'    => ['state' => 'completed', 'already_paid' => true],
            ]);
        }

        if ($order->status === 'paid') {
            return response()->json([
                'success' => true,
                'data'    => ['state' => 'completed', 'already_paid' => true],
            ]);
        }

        if (!$order->isPendingPayment() || !$order->revolut_order_id) {
            return response()->json([
                'success' => true,
                'data'    => ['state' => 'idle'],
            ]);
        }

        $cfg       = Setting::getRevolutConfig();
        $startedAt = $order->revolut_payment_started_at;
        $elapsed   = $startedAt ? (int) abs(now()->diffInSeconds($startedAt)) : 0;
        $timedOut  = $elapsed >= $cfg['timeout_seconds'];

        try {
            $status = $revolut->getOrderStatus($order->revolut_order_id);
            $state  = strtolower($status['state'] ?? '');

            if ($order->revolut_payment_state !== $state && $state !== '') {
                $order->update(['revolut_payment_state' => $state]);
            }

            if (in_array($state, ['completed', 'authorised'], true)) {
                $closed = app(RevolutPaymentCloser::class)->closeAfterPayment($order->fresh(), $state);
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'state'         => 'completed',
                        'total_paid'    => $closed['total_paid'],
                        'corrispettivo' => $this->buildCorrispettivoResponse($closed['corrispettivo']),
                    ],
                ]);
            }

            if (in_array($state, ['failed', 'cancelled', 'declined'], true)) {
                $order->update([
                    'status'                     => 'open',
                    'revolut_order_id'           => null,
                    'revolut_payment_state'      => $state,
                    'revolut_payment_started_at' => null,
                    'revolut_operator_id'        => null,
                ]);
                return response()->json([
                    'success' => true,
                    'data'    => ['state' => $state],
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'state'           => $state ?: 'pending',
                    'elapsed_seconds' => $elapsed,
                    'timed_out'       => $timedOut,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('posPayStatus polling error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data'    => [
                    'state'           => 'pending',
                    'elapsed_seconds' => $elapsed,
                    'timed_out'       => $timedOut,
                    'polling_error'   => true,
                ],
            ]);
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

        // Allinea il contatore locale al massimo progressivo già emesso su
        // MySond per l'anno corrente. Fuori transazione: la chiamata SOAP non
        // deve tenere lock sulla tabella settings. Fail-soft (vedi syncer).
        app(\App\Services\InvoiceCounterSyncer::class)->syncFromMysond();

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
                $invoiceCode = $year . '-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
                $invoiceName = TableOrderInvoice::toAlphanumeric($counter);

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

                // 5. Generate XML and persist it — actual send is handled asynchronously by SendInvoiceToMysondJob
                $result = $mySondFature->createInvoice($tableOrderInvoice);

                InvoiceMysondLog::logCreateInvoice($tableOrderInvoice->id, $result);

                $updateData = [
                    'mysond_response' => is_array($result) ? json_encode($result) : (string) $result,
                ];
                if (($result['response'] ?? '') === 'success') {
                    $updateData['xml_content'] = $result['content'] ?? null;
                    $ficResults[] = $result;
                } else {
                    $updateData['status'] = 'error';
                }
                $tableOrderInvoice->update($updateData);

                if (($result['response'] ?? '') === 'success') {
                    \App\Jobs\SendInvoiceToMysondJob::dispatch($tableOrderInvoice->id);
                }

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

            // Marca first_printed_at su tutti gli item ancora non trasmessi (sync, prima del job)
            OrderItem::where('table_order_id', $order->id)
                ->whereNull('first_printed_at')
                ->update(['first_printed_at' => now()]);

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
            'type'            => 'nullable|string|in:full,split,items,amounts',
            'items'           => 'nullable|array',
            'items.*.order_item_id' => 'required_with:items|integer',
            'items.*.quantity'      => 'required_with:items|integer|min:1',
            'amounts'         => 'nullable|array|min:2',
            'amounts.*'       => 'required_with:amounts|numeric|min:0.01',
            'covers'          => 'nullable|integer|min:0',
            'label'           => 'nullable|string|max:100',
            'discount_type'   => 'nullable|string|in:none,value,percent',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_override_value' => 'nullable|numeric|min:0',
            'order_id'        => 'nullable|integer',
            'pay_now'         => 'nullable|boolean',
        ]);

        $payNow = (bool) ($validated['pay_now'] ?? false);

        $operatorId = $this->verifyOperatorToken(request()->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        try {
            // For banco (multi-session) the client pins the specific order; otherwise fall back to activeOrder.
            $order = null;
            if (!empty($validated['order_id'])) {
                $order = TableOrder::where('id', $validated['order_id'])
                    ->where('restaurant_table_id', $table->id)
                    ->where('status', 'open')
                    ->first();
            }
            $order = $order ?? $table->activeOrder;
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
                    // Includiamo le aggiunzioni a pagamento (extras) nel prezzo effettivo.
                    $extrasTotal = is_array($item->extras) ? array_sum(array_map('floatval', $item->extras)) : 0.0;
                    $unitPriceEffective = (float) $item->unit_price + $extrasTotal;
                    $subtotal = round($unitPriceEffective * $qty, 2);
                    $splitItems[] = [
                        'order_item_id' => $item->id,
                        'dish_name'     => $item->dish->label ?? $item->dish->name ?? 'N/D',
                        'quantity'      => $qty,
                        'unit_price'    => $unitPriceEffective,
                        'subtotal'      => $subtotal,
                        'extras'        => $item->extras,
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

                // Discount: se il chiamante lo passa esplicitamente, prevale;
                // altrimenti eredita lo sconto applicato al TableOrder.
                // Per gli sconti 'value' (importo €) applichiamo proporzionalmente al peso
                // dello split sul totale ordine, altrimenti uno split pagherebbe tutto lo sconto.
                $discountType   = $validated['discount_type'] ?? 'none';
                $discountAmount = (float) ($validated['discount_amount'] ?? 0);

                $inheritFromOrder = ($discountType === 'none' || $discountAmount <= 0)
                    && $order->discount_type
                    && (float) $order->discount_amount > 0;

                if ($inheritFromOrder) {
                    $discountType   = $order->discount_type;
                    if ($discountType === 'percent') {
                        $discountAmount = (float) $order->discount_amount;
                    } else {
                        // 'value': ripartizione proporzionale sul subtotale dello split (default)
                        $orderTotal = (float) $order->total_amount;
                        $orderDiscount = (float) $order->discount_amount;
                        $ratio = $orderTotal > 0 ? min(1.0, $splitTotal / $orderTotal) : 0.0;
                        $discountAmount = round($orderDiscount * $ratio, 2);
                    }
                }

                // Override: l'operatore può scegliere esplicitamente quanto sconto (€)
                // applicare a QUESTO preconto. Valido solo per sconti 'value'.
                // Il valore è limitato dal residuo disponibile (sconto ordine − già assegnato agli altri split).
                $overrideValue = isset($validated['discount_override_value']) ? (float) $validated['discount_override_value'] : null;
                if ($overrideValue !== null && $discountType === 'value') {
                    $assignedByOtherSplits = (float) $order->precontoSplits()->sum('discount_value');
                    $availableFromOrder    = max(0, (float) ($order->discount_value ?? 0) - $assignedByOtherSplits);
                    // override non può superare il residuo né il subtotale dello split
                    $discountAmount = round(min($overrideValue, $availableFromOrder, $splitTotal), 2);
                }

                $discountValue = 0;
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

                if (!$payNow) {
                    PrintPrecontoJob::dispatch($order->id, $operatorId, null, null, 0, $split->id);
                }
                $this->logger->logPrintPreconto($order, $operatorId, null, ['split_id' => $split->id, 'label' => $label], !$payNow);
                $order->update(['preconto_requested_at' => now()]);
                return response()->json([
                    'success' => true,
                    'message' => $payNow
                        ? "Quota \"$label\" creata (€" . number_format($splitTotal, 2) . ") — procedi all'incasso"
                        : "Preconto parziale \"$label\" stampato (€" . number_format($splitTotal, 2) . ")",
                    'data'    => [
                        'split_id' => $split->id,
                        'total'    => $splitTotal,
                        'pay_now'  => $payNow,
                    ],
                ]);
            }

            // ── Split-by-count / Split-by-amounts ─────────────────────────────
            // Entrambi producono N PrecontoSplit persistiti e N stampe separate.
            if ($type === 'split' || $type === 'amounts') {
                // Totale effettivo da ripartire = totale ordine (include già coperti) − sconto applicato.
                $effectiveTotal = $order->hasDiscount() ? $order->getDiscountedTotal() : (float) $order->total_amount;

                // Non consentire split su ordini gia parzialmente coperti da preconti pending.
                $existingAssigned = (float) $order->precontoSplits()->sum('total');
                if ($existingAssigned > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Esistono gia preconti parziali per questo tavolo: completa o elimina quelli esistenti prima di dividere l\'intero conto.',
                    ], 422);
                }

                // Costruisco la lista delle quote.
                $quotas = [];
                if ($type === 'split') {
                    $splitCount = (int) ($validated['split_count'] ?? 0);
                    if ($splitCount < 2) {
                        return response()->json(['success' => false, 'message' => 'Numero di persone non valido'], 422);
                    }
                    // Divisione equa con resto sull'ultimo per evitare arrotondamenti.
                    $per = floor(($effectiveTotal / $splitCount) * 100) / 100;
                    $sum = 0;
                    for ($i = 0; $i < $splitCount - 1; $i++) {
                        $quotas[] = round($per, 2);
                        $sum += $per;
                    }
                    $quotas[] = round($effectiveTotal - $sum, 2);
                } else { // amounts
                    $amounts = array_map('floatval', $validated['amounts'] ?? []);
                    if (count($amounts) < 2) {
                        return response()->json(['success' => false, 'message' => 'Devi specificare almeno 2 importi'], 422);
                    }
                    $sum = round(array_sum($amounts), 2);
                    if (abs($sum - round($effectiveTotal, 2)) > 0.01) {
                        return response()->json([
                            'success' => false,
                            'message' => 'La somma degli importi (' . number_format($sum, 2) . ') deve essere uguale al totale (' . number_format($effectiveTotal, 2) . ')',
                        ], 422);
                    }
                    foreach ($amounts as $a) {
                        $quotas[] = round($a, 2);
                    }
                }

                $total = count($quotas);
                $createdSplits = DB::transaction(function () use ($order, $quotas, $total) {
                    $splits = [];
                    foreach ($quotas as $i => $q) {
                        $splits[] = \App\Models\PrecontoSplit::create([
                            'table_order_id'  => $order->id,
                            'label'           => 'Preconto ' . ($i + 1) . '/' . $total,
                            'items'           => null,
                            'covers'          => 0,
                            'total'           => $q,
                            'discount_type'   => 'none',
                            'discount_amount' => 0,
                            'discount_value'  => 0,
                            'status'          => 'pending',
                        ]);
                    }
                    return $splits;
                });

                // Stampa N ricevute separate, una per split (salvo incasso diretto).
                if (!$payNow) {
                    foreach ($createdSplits as $s) {
                        PrintPrecontoJob::dispatch($order->id, $operatorId, null, null, 0, $s->id);
                    }
                }

                $this->logger->logPrintPreconto($order, $operatorId, $total, [
                    'split_type' => $type,
                    'split_ids'  => collect($createdSplits)->pluck('id')->all(),
                ], !$payNow);
                $order->update(['preconto_requested_at' => now()]);

                if ($payNow) {
                    $message = $type === 'split'
                        ? "Conto diviso per $total persone — procedi all'incasso"
                        : "Conto diviso in $total importi — procedi all'incasso";
                } else {
                    $message = $type === 'split'
                        ? "PreConto diviso per $total persone (stampate $total ricevute)"
                        : "PreConto diviso in $total importi (stampate $total ricevute)";
                }
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data'    => [
                        'pay_now'   => $payNow,
                        'split_ids' => collect($createdSplits)->pluck('id')->all(),
                    ],
                ]);
            }

            // ── Full preconto (intero) ────────────────────────────────────────
            $discountType   = $validated['discount_type'] ?? 'none';
            $discountAmount = (float) ($validated['discount_amount'] ?? 0);
            PrintPrecontoJob::dispatch($order->id, $operatorId, null, $discountType, $discountAmount);
            $this->logger->logPrintPreconto($order, $operatorId, null);
            $order->update(['preconto_requested_at' => now()]);
            return response()->json(['success' => true, 'message' => 'PreConto stampato con successo']);

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
        $effectiveTotal = $order->hasDiscount() ? $order->getDiscountedTotal() : (float) $order->total_amount;
        // Il "Resto" nella UI è la parte dell'ordine NON ancora assegnata a nessun preconto
        // (né pending né paid): se tutti gli items/coperti sono stati coperti da preconti,
        // non deve esserci una riga Resto separata.
        $assignedTotal = (float) $splits->sum('total');
        $remaining = max(0, round($effectiveTotal - $assignedTotal, 2));

        // Quando l'ordine ha uno sconto di tipo 'value', calcoliamo quanto sconto residuo
        // è ancora disponibile per nuovi preconti (totale sconto ordine − sconto già assegnato ai split).
        $orderDiscountType  = $order->discount_type;
        $orderDiscountTotal = (float) ($order->discount_value ?? 0);
        $assignedDiscount   = (float) $splits->sum('discount_value');
        $discountRemaining  = $orderDiscountType === 'value'
            ? max(0, round($orderDiscountTotal - $assignedDiscount, 2))
            : 0;

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
                    'discount_value' => (float) ($s->discount_value ?? 0),
                ])->values(),
                'remaining'           => $remaining,
                'order_total'         => (float) $order->total_amount,
                'order_discounted_total' => $effectiveTotal,
                'order_discount_type'    => $orderDiscountType,
                'order_discount_total'   => $orderDiscountTotal,
                'order_discount_remaining' => $discountRemaining,
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

            // Ogni split emette il proprio corrispettivo (se metodo pagamento non è fattura)
            $corrispettivoInfo = $this->buildCorrispettivoResponse(
                $this->receiptIssuer->emettiPerSplit($split, $paymentMethod, $operatorId)
            );

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
                    'corrispettivo'     => $corrispettivoInfo,
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
     * Cancel an empty banco order (no items).
     */
    public function cancelBanco(Request $request, TableOrder $order): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $order->load('restaurantTable');

        if (!$order->restaurantTable?->is_banco) {
            return response()->json(['success' => false, 'message' => 'Questo ordine non è un ordine banco'], 400);
        }

        if ($order->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Ordine già chiuso'], 400);
        }

        if ($order->items()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Impossibile annullare: l\'ordine contiene articoli'], 400);
        }

        $order->update([
            'status' => 'cancelled',
            'closed_at' => now(),
        ]);

        // Free table only if no other open orders remain
        $openCount = TableOrder::where('restaurant_table_id', $order->restaurant_table_id)
            ->where('status', 'open')
            ->count();
        if ($openCount === 0) {
            $order->restaurantTable->update(['status' => 'free']);
        }

        return response()->json(['success' => true, 'message' => 'Ordine banco annullato']);
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
                    'was_printed' => !is_null($item->first_printed_at),
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

        // Integrazione disabilitata: il frontend chiude direttamente il tavolo via /pay
        if (!Setting::isCashDrawerEnabled()) {
            return response()->json([
                'success'      => true,
                'skipped'      => true,
                'operation_id' => null,
                'message'      => 'Cassa automatica non attiva',
            ]);
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
            'order_id'        => 'nullable|integer',
        ]);

        // In modalità banco il tavolo può avere più ordini aperti contemporaneamente.
        // Se il client specifica order_id lo usiamo, altrimenti fall-back a activeOrder.
        $order = null;
        if (!empty($validated['order_id'])) {
            $order = TableOrder::where('id', $validated['order_id'])
                ->where('restaurant_table_id', $table->id)
                ->where('status', 'open')
                ->first();
        }
        $order = $order ?? $table->activeOrder;

        if (!$order) {
            Log::warning('applyDiscount: nessun ordine attivo', [
                'table_id'    => $table->id,
                'order_id'    => $validated['order_id'] ?? null,
                'operator_id' => $operatorId,
            ]);
            return response()->json(['success' => false, 'message' => 'Nessun ordine attivo'], 404);
        }

        $order->applyDiscount($validated['discount_type'], (float) $validated['discount_amount']);
        $order->refresh();

        Log::info('applyDiscount: sconto salvato', [
            'order_id'        => $order->id,
            'table_id'        => $table->id,
            'operator_id'     => $operatorId,
            'discount_type'   => $order->discount_type,
            'discount_amount' => (float) $order->discount_amount,
            'discount_value'  => (float) $order->discount_value,
        ]);

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
                'order_id'         => $order->id,
                'discount_type'    => $order->discount_type,
                'discount_amount'  => (float) $order->discount_amount,
                'discount_value'   => (float) $order->discount_value,
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
            OrderItem::whereIn('id', $newItemIds)
                ->whereNull('first_printed_at')
                ->update(['first_printed_at' => now()]);
            PrintOrderItemsJob::dispatch($order->id, $newItemIds, 'add', $operatorId);
        }
        if (!empty($updatedItemIds)) {
            OrderItem::whereIn('id', $updatedItemIds)
                ->whereNull('first_printed_at')
                ->update(['first_printed_at' => now()]);
            PrintOrderItemsJob::dispatch($order->id, $updatedItemIds, 'update', $operatorId);
        }

        return response()->json(['success' => true]);
    }
}
