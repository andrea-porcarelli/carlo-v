<?php

namespace App\Http\Controllers\Frontoffice;

use App\Http\Controllers\Controller;
use App\Interfaces\PrinterServiceInterface;
use App\Models\Dish;
use App\Models\MenuOption;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\TableOrder;
use App\Services\FattureInCloudService;
use App\Services\TableOrderLoggerService;
use App\Services\VegaPosService;
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
                        'is_banco' => (bool) $table->is_banco,
                        'has_active_order' => $table->hasActiveOrder(),
                        'current_total' => $table->getCurrentTotal(),
                        'has_preconto' => $table->activeOrder?->preconto_requested_at !== null,
                        'active_order' => $table->activeOrder ? [
                            'id' => $table->activeOrder->id,
                            'items_count' => $table->activeOrder->items->count(),
                            'total_amount' => $table->activeOrder->total_amount,
                            'opened_at' => $table->activeOrder->opened_at->toIso8601String(),
                            'covers' => $table->activeOrder->covers,
                            'autoconsumo' => $table->activeOrder->autoconsumo,
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
                        'autoconsumo' => (bool) $order->autoconsumo,
                        'has_preconto' => $order->preconto_requested_at !== null,
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

            $addedItemIds = collect($addedItems)->pluck('id')->toArray();
            $skipPrint = $request->input('skip_print', false);
            if (!$skipPrint) {
                // Stampa gli articoli aggiunti sulla stampante POS
                try {
                    $itemsWithRelations = OrderItem::with('dish.category.printer')
                        ->whereIn('id', $addedItemIds)
                        ->get();
                    $this->printerService->setOperatorId($operatorId)->printOrderItems($order, $itemsWithRelations, 'add');
                } catch (\Exception $e) {
                    Log::warning('Errore durante la stampa POS multipla', [
                        'table_order_id' => $order->id,
                        'items_count' => count($addedItems),
                        'error' => $e->getMessage()
                    ]);
                }
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

            $skipPrint = $request->input('skip_print', false);
            if (!$skipPrint) {
                // Stampa l'articolo aggiunto sulla stampante POS
                try {
                    $item->load('dish.category.printer');
                    $this->printerService->setOperatorId($operatorId)->printOrderItems($order, collect([$item]), 'add');
                } catch (\Exception $e) {
                    Log::warning('Errore durante la stampa POS', [
                        'item_id' => $item->id,
                        'table_order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                }
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
                // Stampa la modifica sulla stampante POS
                try {
                    $item->load('dish.category.printer');
                    $this->printerService->setOperatorId($operatorId)->printOrderItems($order, collect([$item]), 'update');
                } catch (\Exception $e) {
                    Log::warning('Errore durante la stampa POS per modifica', [
                        'item_id' => $item->id,
                        'table_order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                }
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
        if (!in_array($paymentMethod, ['pos', 'contanti', 'fattura', 'misto'])) {
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
            'invoices'                   => 'required|array|min:1',
            'invoices.*.amount'          => 'required|numeric|min:0.01',
            'invoices.*.description'     => 'nullable|string|max:255',
            'invoices.*.customer_name'   => 'nullable|string|max:255',
            'invoices.*.customer_tax_code' => 'nullable|string|max:50',
            'remaining_amount'           => 'required|numeric|min:0',
            'remaining_method'           => 'nullable|string|in:pos,contanti',
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

            $ficService = app(FattureInCloudService::class);
            $ficResults = [];

            foreach ($validated['invoices'] as $invoiceData) {
                $invoiceData['description'] = $invoiceData['description'] ?: 'Pasto completo';

                // Try to send to Fatture in Cloud first (result stored in log)
                $ficResult = null;
                if ($ficService->isConfigured()) {
                    $ficResult = $ficService->createInvoice($invoiceData);
                    if ($ficResult) {
                        $ficResults[] = $ficResult;
                    }
                }

                // Log invoice creation including FIC outcome
                $this->logger->logCreateInvoice($order, $invoiceData, $operatorId, $ficResult);
            }

            $totalInvoiced = collect($validated['invoices'])->sum('amount');
            $remaining = (float) $validated['remaining_amount'];

            // Determine overall payment method
            if ($remaining > 0.01) {
                $paymentMethod = 'misto'; // part invoice, part pos/contanti
            } else {
                $paymentMethod = 'fattura';
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
                $order->update(['preconto_requested_at' => now()]);
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

            $this->printerService->setOperatorId($operatorId);
            $success = $this->printerService->printOrderItems($order, $order->items, 'reprint');

            if ($success) {
                return response()->json(['success' => true, 'message' => 'Ordine ristampato con successo']);
            } else {
                return response()->json(['success' => false, 'message' => 'Errore nella ristampa. Verificare la configurazione della stampante.'], 500);
            }
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

            // Recupera o crea l'ordine sul tavolo di destinazione
            $destOrder = $destinationTable->activeOrder;
            if (!$destOrder) {
                $destOrder = TableOrder::create([
                    'restaurant_table_id' => $destinationTable->id,
                    'covers' => $sourceOrder->covers,
                    'status' => 'open',
                    'opened_at' => $sourceOrder->opened_at,
                    'waiter_id' => $sourceOrder->waiter_id,
                    'autoconsumo' => $sourceOrder->autoconsumo,
                ]);
                $destinationTable->update(['status' => 'occupied']);
            }

            // Sposta tutti gli items sull'ordine di destinazione
            $sourceOrder->items()->update(['table_order_id' => $destOrder->id]);

            // Aggiorna i totali
            $destOrder->updateTotal();

            // Chiudi l'ordine sorgente e libera il tavolo
            $sourceOrder->delete();
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
            // Log the price update
            $this->logger->logUpdateItemPrice($item, $dataBefore['unit_price'], $validated['unit_price'], $operatorId, $motivation);

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
                // Reset all existing assignments first, then apply new ones
                $order->items()->update(['autoconsumo_user_id' => null]);
                $assignmentMap = collect($assignments)->keyBy('item_id');
                foreach ($order->items as $item) {
                    if ($assignmentMap->has($item->id)) {
                        $item->update(['autoconsumo_user_id' => $assignmentMap[$item->id]['user_id']]);
                    }
                }
                $this->logger->logPartialAutoconsumo($order, $assignments, $operatorId);
            } else {
                // Full autoconsumo: clear any per-item assignments
                $order->items()->update(['autoconsumo_user_id' => null]);
                $this->logger->logFreeAmount($order, $operatorId);
            }

            $order->update(['autoconsumo' => 1]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Autoconsumo registrato con successo',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in freeAmount: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'autoconsumo',
            ], 500);
        }
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

        try {
            if (!empty($newItemIds)) {
                $newItems = OrderItem::with('dish.category.printer')->whereIn('id', $newItemIds)->get();
                $this->printerService->setOperatorId($operatorId)->printOrderItems($order, $newItems, 'add');
            }
            if (!empty($updatedItemIds)) {
                $updatedItems = OrderItem::with('dish.category.printer')->whereIn('id', $updatedItemIds)->get();
                $this->printerService->setOperatorId($operatorId)->printOrderItems($order, $updatedItems, 'update');
            }
        } catch (\Exception $e) {
            Log::warning('Errore durante la stampa sessione', [
                'table_order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(['success' => true]);
    }
}
