<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\CustomerOrder;
use App\Models\ItemStock;
use App\Models\Stock;
use App\Models\Payment;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

class OrderController extends Controller
{
    /**
     * Validate stock availability for items in a store.
     */
    public function validateStocks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'store_id' => 'required|exists:stores,id',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for stock check', ['errors' => $validator->errors()->toArray()]);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()->toArray()
            ], 422);
        }

        $result = $this->checkStockAvailability($request->input('items'), $request->input('store_id'));
        return response()->json($result, $result['status'] === 'ok' ? 200 : 422);
    }

    /**
     * Helper method to check stock availability with locking.
     */
    protected function checkStockAvailability(array $items, $storeId)
    {
        try {
            // Lock stock records to prevent concurrent updates
            $stocks = DB::table('item_stocks as ist')
                ->join('stocks as s', 'ist.stock_id', '=', 's.id')
                ->whereIn('ist.item_id', array_column($items, 'item_id'))
                ->where('s.store_id', $storeId)
                ->select('ist.item_id', 'ist.stock_quantity')
                ->lockForUpdate() // Add locking
                ->get();

            foreach ($items as $item) {
                $stock = $stocks->firstWhere('item_id', (int)$item['item_id']);
                if (!$stock) {
                    Log::error("No stock found for item_id: {$item['item_id']} in store_id: {$storeId}");
                    return ['error' => "No stock found for item_id: {$item['item_id']} in store {$storeId}"];
                }

                $currentQuantity = (float)$stock->stock_quantity;
                if ($currentQuantity < $item['quantity']) {
                    Log::error("Insufficient stock for item_id: {$item['item_id']} in store_id: {$storeId}");
                    return [
                        'error' => "Insufficient stock for item_id: {$item['item_id']}. Available: {$currentQuantity}, Requested: {$item['quantity']}"
                    ];
                }
            }
            return ['status' => 'ok'];
        } catch (QueryException $e) {
            Log::error("Database error checking stock: {$e->getMessage()}");
            try {
                $itemStocksColumns = Schema::getColumnListing('item_stocks');
                $stocksColumns = Schema::getColumnListing('stocks');
                Log::error("item_stocks table columns: " . json_encode($itemStocksColumns));
                Log::error("stocks table columns: " . json_encode($stocksColumns));
            } catch (\Exception $e2) {
                Log::error("Failed to fetch table schema: {$e2->getMessage()}");
            }
            return ['error' => "Database error checking stock: {$e->getMessage()}"];
        } catch (\Exception $e) {
            Log::error("Error checking stock: {$e->getMessage()}");
            return ['error' => "Error checking stock: {$e->getMessage()}"];
        }
    }

    /**
     * Save a single order and deduct stock.
     */
    public function saveOrder(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order' => 'required|array',
                'order.order_number' => 'required|string|max:255|unique:orders,order_number',
                'order.receipt_number' => 'required|string|max:255',
                'order.date' => 'required|date',
                'order.customer_type_id' => 'nullable|exists:customer_types,id',
                'order.store_id' => 'required|exists:stores,id',
                'order.total_amount' => 'required|numeric|min:0',
                'order.discount' => 'nullable|numeric|min:0',
                'order.ground_total' => 'required|numeric|min:0',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|exists:items,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.discount' => 'nullable|numeric|min:0',
                'payment_id' => 'required|exists:payments,id',
                'customer_id' => 'nullable|exists:customers,id',
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for save order', ['errors' => $validator->errors()->toArray()]);
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors()->toArray()
                ], 422);
            }

            $orderData = $request->input('order');
            $items = $request->input('items');
            $paymentId = $request->input('payment_id');
            $customerId = $request->input('customer_id');

            // Validate stock availability
            $stockCheck = $this->checkStockAvailability($items, $orderData['store_id']);
            if (isset($stockCheck['error'])) {
                Log::warning('Stock validation failed for save order', ['error' => $stockCheck['error']]);
                return response()->json([
                    'error' => $stockCheck['error']
                ], 422);
            }

            // Validate order discount if "By Order"
            $orderDiscount = (float)($orderData['discount'] ?? 0.0);
            $isByOrderDiscount = empty(array_filter($items, fn($item) => !empty($item['discount'])));
            if ($isByOrderDiscount && $orderDiscount > 0) {
                $discount = Discount::where('store_id', $orderData['store_id'])
                    ->where('type', 'by order')
                    ->where(function ($query) {
                        $query->whereNull('discount_start_date')
                            ->orWhere('discount_start_date', '<=', Carbon::now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('discount_end_date')
                            ->orWhere('discount_end_date', '>=', Carbon::now());
                    })
                    ->first();

                if ($discount) {
                    if ($orderDiscount < $discount->discount_min || $orderDiscount > $discount->discount_max) {
                        Log::error("Order discount {$orderDiscount} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]");
                        return response()->json([
                            'error' => "Order discount {$orderDiscount} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]"
                        ], 422);
                    }
                } else {
                    Log::warning("No 'by order' discount defined for store_id {$orderData['store_id']}; discount ignored");
                    $orderData['discount'] = 0.0;
                    $orderData['ground_total'] = $orderData['total_amount'];
                }
            }

            return DB::transaction(function () use ($orderData, $items, $paymentId, $customerId) {
                $now = Carbon::now();

                // Create order
                $order = Order::create([
                    'order_number' => $orderData['order_number'],
                    'receipt_number' => $orderData['receipt_number'],
                    'date' => $orderData['date'],
                    'customer_type_id' => $orderData['customer_type_id'],
                    'store_id' => $orderData['store_id'],
                    'total_amount' => $orderData['total_amount'],
                    'discount' => $orderData['discount'] ?? 0,
                    'ground_total' => $orderData['ground_total'],
                    'is_active' => true,
                    'version' => 1,
                    'last_modified' => $now,
                    'is_synced' => false,
                    'operation' => 'create',
                    'created_at' => $now,
                    'updated_at' => $now
                ]);

                // Process items and discounts
                foreach ($items as $item) {
                    $itemDiscount = (float)($item['discount'] ?? 0.0);

                    // Validate item discount if present
                    if ($itemDiscount > 0) {
                        $discount = Discount::where('item_id', $item['item_id'])
                            ->where('store_id', $orderData['store_id'])
                            ->where('type', 'by items')
                            ->where(function ($query) {
                                $query->whereNull('discount_start_date')
                                    ->orWhere('discount_start_date', '<=', Carbon::now());
                            })
                            ->where(function ($query) {
                                $query->whereNull('discount_end_date')
                                    ->orWhere('discount_end_date', '>=', Carbon::now());
                            })
                            ->first();

                        if ($discount) {
                            if ($itemDiscount < $discount->discount_min || $itemDiscount > $discount->discount_max) {
                                Log::error("Discount {$itemDiscount} for item_id {$item['item_id']} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]");
                                return response()->json([
                                    'error' => "Discount {$itemDiscount} for item_id {$item['item_id']} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]"
                                ], 422);
                            }
                        } else {
                            Log::warning("No 'by items' discount defined for item_id {$item['item_id']} at store_id {$orderData['store_id']}; discount ignored");
                            $itemDiscount = 0.0;
                        }
                    }

                    // Create order item
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_id' => $item['item_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'discount' => $itemDiscount,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);

                    // Update stock with negative quantity check
                    $itemStock = ItemStock::whereIn('stock_id', function ($query) use ($item, $orderData) {
                        $query->select('id')
                            ->from('stocks')
                            ->where('store_id', $orderData['store_id']);
                    })->where('item_id', $item['item_id'])
                    ->lockForUpdate() // Lock stock record
                    ->first();

                    if (!$itemStock) {
                        Log::error("No stock record found for item_id: {$item['item_id']} in store_id: {$orderData['store_id']}");
                        throw new \Exception("No stock record found for item_id: {$item['item_id']}");
                    }

                    $newQuantity = $itemStock->stock_quantity - $item['quantity'];
                    if ($newQuantity < 0) {
                        Log::error("Stock update would result in negative quantity for item_id: {$item['item_id']}");
                        throw new \Exception("Stock update would result in negative quantity for item_id: {$item['item_id']}");
                    }

                    $updated = $itemStock->decrement('stock_quantity', $item['quantity'], [
                        'updated_at' => $now
                    ]);

                    if ($updated) {
                        Log::info("Stock updated for item_id: {$item['item_id']}, new quantity: {$newQuantity}");
                    } else {
                        Log::error("Failed to update stock for item_id: {$item['item_id']}");
                        throw new \Exception("Failed to update stock for item_id: {$item['item_id']}");
                    }
                }

                // Create order payment
                OrderPayment::create([
                    'order_id' => $order->id,
                    'payment_id' => $paymentId,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);

                // Create customer order if customer_id is provided
                if ($customerId) {
                    CustomerOrder::create([
                        'order_id' => $order->id,
                        'customer_id' => $customerId,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                }

                Log::info('Order saved successfully', ['order_id' => $order->id, 'order_number' => $order->order_number]);
                return response()->json([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'receipt_number' => $order->receipt_number
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to save order', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Validation\ValidationException ? 422 : 500);
        }
    }

    /**
     * Store a batch of orders and deduct stock.
     */
    public function storeBatch(Request $request)
    {
        try {
            $payloads = $request->all();
            if (!is_array($payloads)) {
                return response()->json([
                    'message' => 'Invalid payload format, expected array of orders'
                ], 422);
            }

            $orders = [];
            DB::beginTransaction();

            foreach ($payloads as $index => $payload) {
                $validator = Validator::make($payload, [
                    'order_data.order_number' => 'required|string|max:255|unique:orders,order_number',
                    'order_data.receipt_number' => 'required|string|max:255',
                    'order_data.date' => 'required|date',
                    'order_data.customer_type_id' => 'nullable|exists:customer_types,id',
                    'order_data.store_id' => 'required|exists:stores,id',
                    'order_data.total_amount' => 'required|numeric|min:0',
                    'order_data.tip' => 'nullable|numeric|min:0',
                    'order_data.discount' => 'nullable|numeric|min:0',
                    'order_data.ground_total' => 'required|numeric|min:0',
                    'items' => 'required|array|min:1',
                    'items.*.item_id' => 'required|exists:items,id',
                    'items.*.quantity' => 'required|integer|min:1',
                    'items.*.price' => 'required|numeric|min:0',
                    'items.*.discount' => 'nullable|numeric|min:0',
                    'payment_id' => 'required|exists:payments,id',
                    'customer_id' => 'nullable|exists:customers,id'
                ]);

                if ($validator->fails()) {
                    Log::warning("Order validation failed at index {$index}", ['errors' => $validator->errors()]);
                    DB::rollBack();
                    return response()->json([
                        'message' => "Validation failed for order at index {$index}",
                        'errors' => $validator->errors()
                    ], 422);
                }

                // Validate stock
                $stockCheck = $this->checkStockAvailability($payload['items'], $payload['order_data']['store_id']);
                if (isset($stockCheck['error'])) {
                    Log::warning("Stock validation failed for order at index {$index}", ['error' => $stockCheck['error']]);
                    DB::rollBack();
                    return response()->json([
                        'message' => "Stock validation failed for order at index {$index}",
                        'error' => $stockCheck['error']
                    ], 422);
                }

                $orderData = $payload['order_data'];
                $items = $payload['items'];

                // Validate order discount if "By Order"
                $orderDiscount = (float)($orderData['discount'] ?? 0.0);
                $isByOrderDiscount = empty(array_filter($items, fn($item) => !empty($item['discount'])));
                if ($isByOrderDiscount && $orderDiscount > 0) {
                    $discount = Discount::where('store_id', $orderData['store_id'])
                        ->where('type', 'by order')
                        ->where(function ($query) {
                            $query->whereNull('discount_start_date')
                                ->orWhere('discount_start_date', '<=', Carbon::now());
                        })
                        ->where(function ($query) {
                            $query->whereNull('discount_end_date')
                                ->orWhere('discount_end_date', '>=', Carbon::now());
                        })
                        ->first();

                    if ($discount) {
                        if ($orderDiscount < $discount->discount_min || $orderDiscount > $discount->discount_max) {
                            Log::error("Order discount {$orderDiscount} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]");
                            return response()->json([
                                'error' => "Order discount {$orderDiscount} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]"
                            ], 422);
                        }
                    } else {
                        Log::warning("No 'by order' discount defined for store_id {$orderData['store_id']}; discount ignored");
                        $orderData['discount'] = 0.0;
                        $orderData['ground_total'] = $orderData['total_amount'];
                    }
                }

                $order = Order::create([
                    'order_number' => $orderData['order_number'],
                    'receipt_number' => $orderData['receipt_number'],
                    'date' => $orderData['date'],
                    'customer_type_id' => $orderData['customer_type_id'],
                    'store_id' => $orderData['store_id'],
                    'total_amount' => $orderData['total_amount'],
                    'tip' => $orderData['tip'] ?? 0,
                    'discount' => $orderData['discount'] ?? 0,
                    'ground_total' => $orderData['ground_total'],
                    'is_active' => true,
                    'status' => 'completed',
                    'version' => 1,
                    'last_modified' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                foreach ($items as $item) {
                    $itemDiscount = (float)($item['discount'] ?? 0.0);

                    // Validate item discount if present
                    if ($itemDiscount > 0) {
                        $discount = Discount::where('item_id', $item['item_id'])
                            ->where('store_id', $orderData['store_id'])
                            ->where('type', 'by items')
                            ->where(function ($query) {
                                $query->whereNull('discount_start_date')
                                    ->orWhere('discount_start_date', '<=', Carbon::now());
                            })
                            ->where(function ($query) {
                                $query->whereNull('discount_end_date')
                                    ->orWhere('discount_end_date', '>=', Carbon::now());
                            })
                            ->first();

                        if ($discount) {
                            if ($itemDiscount < $discount->discount_min || $itemDiscount > $discount->discount_max) {
                                Log::error("Discount {$itemDiscount} for item_id {$item['item_id']} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]");
                                return response()->json([
                                    'error' => "Discount {$itemDiscount} for item_id {$item['item_id']} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]"
                                ], 422);
                            }
                        } else {
                            Log::warning("No 'by items' discount defined for item_id {$item['item_id']} at store_id {$orderData['store_id']}; discount ignored");
                            $itemDiscount = 0.0;
                        }
                    }

                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_id' => $item['item_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'discount' => $itemDiscount,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Update stock with negative quantity check
                    $itemStock = ItemStock::whereIn('stock_id', function ($query) use ($item, $orderData) {
                        $query->select('id')
                            ->from('stocks')
                            ->where('store_id', $orderData['store_id']);
                    })->where('item_id', $item['item_id'])
                    ->lockForUpdate()
                    ->first();

                    if (!$itemStock) {
                        Log::error("No stock record found for item_id: {$item['item_id']} in store_id: {$orderData['store_id']}");
                        throw new \Exception("No stock record found for item_id: {$item['item_id']}");
                    }

                    $newQuantity = $itemStock->stock_quantity - $item['quantity'];
                    if ($newQuantity < 0) {
                        Log::error("Stock update would result in negative quantity for item_id: {$item['item_id']}");
                        throw new \Exception("Stock update would result in negative quantity for item_id: {$item['item_id']}");
                    }

                    $updated = $itemStock->decrement('stock_quantity', $item['quantity'], [
                        'updated_at' => now()
                    ]);

                    if ($updated) {
                        Log::info("Stock updated for item_id: {$item['item_id']}, new quantity: {$newQuantity}");
                    } else {
                        Log::error("Failed to update stock for item_id: {$item['item_id']}");
                        throw new \Exception("Failed to update stock for item_id: {$item['item_id']}");
                    }
                }

                OrderPayment::create([
                    'order_id' => $order->id,
                    'payment_id' => $payload['payment_id'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                if ($payload['customer_id']) {
                    CustomerOrder::create([
                        'order_id' => $order->id,
                        'customer_id' => $payload['customer_id'],
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                $orders[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'receipt_number' => $order->receipt_number
                ];
            }

            DB::commit();
            Log::info('Batch orders created successfully', ['count' => count($orders)]);
            return response()->json([
                'data' => $orders,
                'message' => 'Batch orders created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create batch orders', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a paginated list of payments.
     */
    public function getPayments(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $query = Payment::query()->orderBy('created_at', 'desc');

            if ($request->query('payment_type_id')) {
                $query->where('payment_type_id', $request->query('payment_type_id'));
            }

            $payments = $query->with('paymentType')->paginate($perPage);

            return response()->json([
                'data' => $payments->items(),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                ],
                'message' => 'Payments retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve payments: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
                'details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Get a paginated list of orders.
     */
    public function getOrders(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $query = Order::query()->orderBy('date', 'desc');

            if ($request->query('order_number')) {
                $query->where('order_number', $request->query('order_number'));
            }
            if ($request->query('receipt_number')) {
                $query->where('receipt_number', $request->query('receipt_number'));
            }
            if ($request->query('store_id')) {
                $query->where('store_id', $request->query('store_id'));
            }
            if ($request->query('date')) {
                $query->where('date', $request->query('date'));
            }

            $orders = $query->with(['customerType', 'store', 'orderItems', 'orderPayments', 'customerOrders'])
                ->paginate($perPage);

            return response()->json([
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
                'message' => 'Orders retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve orders: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
                'details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Get a specific order by ID.
     */
    public function show($id)
    {
        try {
            $order = Order::with(['orderItems.item', 'orderPayments.payment', 'customerOrder.customer'])
                ->find($id);

            if (!$order) {
                Log::warning("Order with ID {$id} not found");
                return response()->json([
                    'message' => "Order with ID {$id} not found"
                ], 404);
            }

            return response()->json([
                'data' => $order,
                'message' => 'Order retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve order: ' . $e->getMessage(), [
                'order_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
                'details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Update an existing order and adjust stock.
     */
    public function update(Request $request, $id)
    {
        try {
            $order = Order::find($id);
            if (!$order) {
                Log::warning("Order with ID {$id} not found", ['request' => $request->all()]);
                return response()->json([
                    'message' => "Order with ID {$id} not found"
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'order_data' => 'required|array',
                'order_data.order_number' => 'required|string|unique:orders,order_number,' . $id,
                'order_data.receipt_number' => 'required|string|unique:orders,receipt_number,' . $id,
                'order_data.date' => 'required|date',
                'order_data.customer_type_id' => 'nullable|exists:customer_types,id',
                'order_data.store_id' => 'required|exists:stores,id',
                'order_data.total_amount' => 'required|numeric|min:0',
                'order_data.tip' => 'nullable|numeric|min:0',
                'order_data.discount' => 'nullable|numeric|min:0',
                'order_data.ground_total' => 'required|numeric|min:0',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|exists:items,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.discount' => 'nullable|numeric|min:0',
                'payment_id' => 'required|exists:payments,id',
                'customer_id' => 'nullable|exists:customers,id'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for order update', [
                    'order_id' => $id,
                    'errors' => $validator->errors()->toArray(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray()
                ], 422);
            }

            // Validate stock
            $stockCheck = $this->checkStockAvailability($request->input('items'), $request->input('order_data.store_id'));
            if (isset($stockCheck['error'])) {
                Log::warning("Stock validation failed for order update", ['error' => $stockCheck['error']]);
                return response()->json([
                    'message' => 'Stock validation failed',
                    'error' => $stockCheck['error']
                ], 422);
            }

            // Validate order discount if "By Order"
            $orderData = $request->input('order_data');
            $items = $request->input('items');
            $orderDiscount = (float)($orderData['discount'] ?? 0.0);
            $isByOrderDiscount = empty(array_filter($items, fn($item) => !empty($item['discount'])));
            if ($isByOrderDiscount && $orderDiscount > 0) {
                $discount = Discount::where('store_id', $orderData['store_id'])
                    ->where('type', 'by order')
                    ->where(function ($query) {
                        $query->whereNull('discount_start_date')
                            ->orWhere('discount_start_date', '<=', Carbon::now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('discount_end_date')
                            ->orWhere('discount_end_date', '>=', Carbon::now());
                    })
                    ->first();

                if ($discount) {
                    if ($orderDiscount < $discount->discount_min || $orderDiscount > $discount->discount_max) {
                        Log::error("Order discount {$orderDiscount} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]");
                        return response()->json([
                            'error' => "Order discount {$orderDiscount} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]"
                        ], 422);
                    }
                } else {
                    Log::warning("No 'by order' discount defined for store_id {$orderData['store_id']}; discount ignored");
                    $orderData['discount'] = 0.0;
                    $orderData['ground_total'] = $orderData['total_amount'];
                }
            }

            return DB::transaction(function () use ($request, $order, $items, $orderData) {
                $now = Carbon::now();

                // Restore stock for existing order items
                $existingItems = OrderItem::where('order_id', $order->id)->get();
                foreach ($existingItems as $existingItem) {
                    $itemStock = ItemStock::whereIn('stock_id', function ($query) use ($order) {
                        $query->select('id')
                            ->from('stocks')
                            ->where('store_id', $order->store_id);
                    })->where('item_id', $existingItem->item_id)
                    ->lockForUpdate()
                    ->first();

                    if ($itemStock) {
                        $itemStock->increment('stock_quantity', $existingItem->quantity, [
                            'updated_at' => $now
                        ]);
                        Log::info("Restored stock for item_id: {$existingItem->item_id}, quantity: {$existingItem->quantity}");
                    }
                }

                // Update order
                $order->update([
                    'order_number' => $orderData['order_number'],
                    'receipt_number' => $orderData['receipt_number'],
                    'date' => $orderData['date'],
                    'customer_type_id' => $orderData['customer_type_id'],
                    'store_id' => $orderData['store_id'],
                    'total_amount' => $orderData['total_amount'],
                    'tip' => $orderData['tip'] ?? 0,
                    'discount' => $orderData['discount'] ?? 0,
                    'ground_total' => $orderData['ground_total'],
                    'last_modified' => $now,
                    'version' => $order->version + 1,
                    'operation' => 'update'
                ]);

                // Delete existing order items and add new ones
                OrderItem::where('order_id', $order->id)->delete();
                foreach ($items as $item) {
                    $itemDiscount = (float)($item['discount'] ?? 0.0);

                    // Validate item discount if present
                    if ($itemDiscount > 0) {
                        $discount = Discount::where('item_id', $item['item_id'])
                            ->where('store_id', $orderData['store_id'])
                            ->where('type', 'by items')
                            ->where(function ($query) {
                                $query->whereNull('discount_start_date')
                                    ->orWhere('discount_start_date', '<=', Carbon::now());
                            })
                            ->where(function ($query) {
                                $query->whereNull('discount_end_date')
                                    ->orWhere('discount_end_date', '>=', Carbon::now());
                            })
                            ->first();

                        if ($discount) {
                            if ($itemDiscount < $discount->discount_min || $itemDiscount > $discount->discount_max) {
                                Log::error("Discount {$itemDiscount} for item_id {$item['item_id']} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]");
                                return response()->json([
                                    'error' => "Discount {$itemDiscount} for item_id {$item['item_id']} is outside valid range [{$discount->discount_min}, {$discount->discount_max}]"
                                ], 422);
                            }
                        } else {
                            Log::warning("No 'by items' discount defined for item_id {$item['item_id']} at store_id {$orderData['store_id']}; discount ignored");
                            $itemDiscount = 0.0;
                        }
                    }

                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_id' => $item['item_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'discount' => $itemDiscount,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);

                    // Update stock with negative quantity check
                    $itemStock = ItemStock::whereIn('stock_id', function ($query) use ($item, $orderData) {
                        $query->select('id')
                            ->from('stocks')
                            ->where('store_id', $orderData['store_id']);
                    })->where('item_id', $item['item_id'])
                    ->lockForUpdate()
                    ->first();

                    if (!$itemStock) {
                        Log::error("No stock record found for item_id: {$item['item_id']} in store_id: {$orderData['store_id']}");
                        throw new \Exception("No stock record found for item_id: {$item['item_id']}");
                    }

                    $newQuantity = $itemStock->stock_quantity - $item['quantity'];
                    if ($newQuantity < 0) {
                        Log::error("Stock update would result in negative quantity for item_id: {$item['item_id']}");
                        throw new \Exception("Stock update would result in negative quantity for item_id: {$item['item_id']}");
                    }

                    $updated = $itemStock->decrement('stock_quantity', $item['quantity'], [
                        'updated_at' => $now
                    ]);

                    if ($updated) {
                        Log::info("Stock updated for item_id: {$item['item_id']}, new quantity: {$newQuantity}");
                    } else {
                        Log::error("Failed to update stock for item_id: {$item['item_id']}");
                        throw new \Exception("Failed to update stock for item_id: {$item['item_id']}");
                    }
                }

                // Update order payment
                OrderPayment::where('order_id', $order->id)->delete();
                OrderPayment::create([
                    'order_id' => $order->id,
                    'payment_id' => $request->input('payment_id'),
                    'created_at' => $now,
                    'updated_at' => $now
                ]);

                // Update customer order
                CustomerOrder::where('order_id', $order->id)->delete();
                if ($request->input('customer_id')) {
                    CustomerOrder::create([
                        'customer_id' => $request->input('customer_id'),
                        'order_id' => $order->id,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                }

                Log::info('Order updated successfully', ['order_id' => $order->id, 'order_number' => $order->order_number]);
                return response()->json([
                    'data' => ['order_id' => $order->id],
                    'message' => 'Order updated successfully'
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Failed to update order: ' . $e->getMessage(), [
                'order_id' => $id,
                'request' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
                'details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }
}