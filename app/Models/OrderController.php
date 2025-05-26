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

class OrderController extends Controller
{
    /**
     * Store a new order.
     */
    public function store(Request $request)
    {
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
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
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
                return response()->json([
                    'message' => "Insufficient stock for item ID {$item['item_id']}"
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
                ItemStock::whereIn('stock_id', function ($query) use ($item, $request) {
                    $query->select('id')
                        ->from('stocks')
                        ->where('item_id', $item['item_id'])
                        ->where('store_id', $request->order_data['store_id']);
                })->decrement('stock_quantity', $item['quantity'], [
                    'updated_at' => $now
                ]);
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

            return response()->json([
                'data' => ['order_id' => $order->id],
                'message' => 'Order created successfully'
            ], 201);
        }, 5); // Retry transaction up to 5 times in case of deadlock
    }
}