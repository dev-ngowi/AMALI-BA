<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\CustomerOrder;
use App\Models\ItemStock;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Store a new order.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_data' => 'required|array',
                'order_data.order_number' => 'required|string|unique:orders,order_number',
                'order_data.receipt_number' => 'required|string|unique:orders,receipt_number',
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
                'payment_id' => 'required|exists:payments,id',
                'customer_id' => 'nullable|exists:customers,id'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for order creation', [
                    'errors' => $validator->errors()->toArray(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray()
                ], 422);
            }

            // Validate stock availability
            foreach ($request->items as $item) {
                $stock = ItemStock::whereIn('stock_id', function ($query) use ($item, $request) {
                    $query->select('id')
                        ->from('stocks')
                        ->where('item_id', $item['item_id'])
                        ->where('store_id', $request->order_data['store_id']);
                })->first();

                if (!$stock || $stock->stock_quantity < $item['quantity']) {
                    Log::warning("Insufficient stock for item ID {$item['item_id']}", [
                        'item_id' => $item['item_id'],
                        'store_id' => $request->order_data['store_id'],
                        'requested_quantity' => $item['quantity'],
                        'available_quantity' => $stock ? $stock->stock_quantity : 0
                    ]);
                    return response()->json([
                        'message' => "Insufficient stock for item ID {$item['item_id']}",
                        'details' => [
                            'item_id' => $item['item_id'],
                            'store_id' => $request->order_data['store_id'],
                            'requested_quantity' => $item['quantity'],
                            'available_quantity' => $stock ? $stock->stock_quantity : 0
                        ]
                    ], 422);
                }
            }

            return DB::transaction(function () use ($request) {
                $now = Carbon::now();

                // Create order
                $order = Order::create([
                    'order_number' => $request->order_data['order_number'],
                    'receipt_number' => $request->order_data['receipt_number'],
                    'date' => $request->order_data['date'],
                    'customer_type_id' => $request->order_data['customer_type_id'],
                    'store_id' => $request->order_data['store_id'],
                    'total_amount' => $request->order_data['total_amount'],
                    'tip' => $request->order_data['tip'] ?? 0,
                    'discount' => $request->order_data['discount'] ?? 0,
                    'ground_total' => $request->order_data['ground_total'],
                    'is_active' => true,
                    'status' => 'completed',
                    'version' => 1,
                    'last_modified' => $now,
                    'is_synced' => false,
                    'operation' => 'create'
                ]);

                // Create order items and update stock
                foreach ($request->items as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_id' => $item['item_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);

                    // Update stock
                    $updated = ItemStock::whereIn('stock_id', function ($query) use ($item, $request) {
                        $query->select('id')
                            ->from('stocks')
                            ->where('item_id', $item['item_id'])
                            ->where('store_id', $request->order_data['store_id']);
                    })->decrement('stock_quantity', $item['quantity'], [
                        'updated_at' => $now
                    ]);

                    if ($updated === 0) {
                        Log::error("Failed to update stock for item ID {$item['item_id']}", [
                            'item_id' => $item['item_id'],
                            'store_id' => $request->order_data['store_id'],
                            'quantity' => $item['quantity']
                        ]);
                        throw new \Exception("Failed to update stock for item ID {$item['item_id']}");
                    }
                }

                // Create order payment
                OrderPayment::create([
                    'order_id' => $order->id,
                    'payment_id' => $request->payment_id,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);

                // Create customer order if customer_id is provided
                if ($request->customer_id) {
                    CustomerOrder::create([
                        'customer_id' => $request->customer_id,
                        'order_id' => $order->id
                    ]);
                }

                Log::info('Order created successfully', ['order_id' => $order->id, 'order_number' => $order->order_number]);
                return response()->json([
                    'data' => ['order_id' => $order->id],
                    'message' => 'Order created successfully'
                ], 201);
            }, 5);
        } catch (\Exception $e) {
            Log::error('Failed to create order: ' . $e->getMessage(), [
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

    /**
     * Get a paginated list of orders or a specific order by order_number/receipt_number.
     */
    public function get(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $query = Order::with(['orderItems.item', 'orderPayments.payment', 'customerOrder.customer'])
                ->orderBy('date', 'desc');

            // Support filtering by order_number or receipt_number
            if ($request->query('order_number')) {
                $query->where('order_number', $request->query('order_number'));
            }
            if ($request->query('receipt_number')) {
                $query->where('receipt_number', $request->query('receipt_number'));
            }

            $orders = $query->paginate($perPage);

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
     * Update an existing order.
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

            // Validate stock availability
            foreach ($request->items as $item) {
                $stock = ItemStock::whereIn('stock_id', function ($query) use ($item, $request) {
                    $query->select('id')
                        ->from('stocks')
                        ->where('item_id', $item['item_id'])
                        ->where('store_id', $request->order_data['store_id']);
                })->first();

                if (!$stock || $stock->stock_quantity < $item['quantity']) {
                    Log::warning("Insufficient stock for item ID {$item['item_id']}", [
                        'item_id' => $item['item_id'],
                        'store_id' => $request->order_data['store_id'],
                        'requested_quantity' => $item['quantity'],
                        'available_quantity' => $stock ? $stock->stock_quantity : 0
                    ]);
                    return response()->json([
                        'message' => "Insufficient stock for item ID {$item['item_id']}",
                        'details' => [
                            'item_id' => $item['item_id'],
                            'store_id' => $request->order_data['store_id'],
                            'requested_quantity' => $item['quantity'],
                            'available_quantity' => $stock ? $stock->stock_quantity : 0
                        ]
                    ], 422);
                }
            }

            return DB::transaction(function () use ($request, $order) {
                $now = Carbon::now();

                // Update order
                $order->update([
                    'order_number' => $request->order_data['order_number'],
                    'receipt_number' => $request->order_data['receipt_number'],
                    'date' => $request->order_data['date'],
                    'customer_type_id' => $request->order_data['customer_type_id'],
                    'store_id' => $request->order_data['store_id'],
                    'total_amount' => $request->order_data['total_amount'],
                    'tip' => $request->order_data['tip'] ?? 0,
                    'discount' => $request->order_data['discount'] ?? 0,
                    'ground_total' => $request->order_data['ground_total'],
                    'last_modified' => $now,
                    'version' => $order->version + 1,
                    'operation' => 'update'
                ]);

                // Delete existing order items and add new ones
                OrderItem::where('order_id', $order->id)->delete();
                foreach ($request->items as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_id' => $item['item_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);

                    // Update stock
                    $updated = ItemStock::whereIn('stock_id', function ($query) use ($item, $request) {
                        $query->select('id')
                            ->from('stocks')
                            ->where('item_id', $item['item_id'])
                            ->where('store_id', $request->order_data['store_id']);
                    })->decrement('stock_quantity', $item['quantity'], [
                        'updated_at' => $now
                    ]);

                    if ($updated === 0) {
                        Log::error("Failed to update stock for item ID {$item['item_id']}", [
                            'item_id' => $item['item_id'],
                            'store_id' => $request->order_data['store_id'],
                            'quantity' => $item['quantity']
                        ]);
                        throw new \Exception("Failed to update stock for item ID {$item['item_id']}");
                    }
                }

                // Update order payment
                OrderPayment::where('order_id', $order->id)->delete();
                OrderPayment::create([
                    'order_id' => $order->id,
                    'payment_id' => $request->payment_id,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);

                // Update customer order
                CustomerOrder::where('order_id', $order->id)->delete();
                if ($request->customer_id) {
                    CustomerOrder::create([
                        'customer_id' => $request->customer_id,
                        'order_id' => $order->id
                    ]);
                }

                Log::info('Order updated successfully', ['order_id' => $order->id, 'order_number' => $order->order_number]);
                return response()->json([
                    'data' => ['order_id' => $order->id],
                    'message' => 'Order updated successfully'
                ], 200);
            }, 5);
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