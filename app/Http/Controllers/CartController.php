<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CartController extends Controller
{
    /**
     * Generate a unique order number.
     */
    private function generateOrderNumber()
    {
        $count = Cart::count() + 1;
        return 'ORD-' . Carbon::now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Display a listing of the carts.
     */
    public function index()
    {
        $carts = Cart::with(['customerType', 'customer', 'items'])->get();
        return response()->json([
            'data' => $carts,
            'message' => 'success'
        ], 200);
    }

    /**
     * Display the specified cart.
     */
    public function show($id)
    {
        $cart = Cart::with(['customerType', 'customer', 'items'])->find($id);
        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found'
            ], 404);
        }

        return response()->json([
            'data' => $cart,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created cart in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_type_id' => 'required|exists:customer_types,id',
            'customer_id' => 'nullable|exists:customers,id',
            'status' => 'nullable|in:in-cart,pending,completed,cancelled',
            'date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.unit' => 'required|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $total_amount = collect($request->items)->sum('amount');

            $cart = Cart::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_type_id' => $request->customer_type_id,
                'customer_id' => $request->customer_id,
                'total_amount' => $total_amount,
                'status' => $request->status ?? 'in-cart',
                'date' => $request->date ?? Carbon::today()->toDateTimeString()
            ]);

            foreach ($request->items as $item) {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'item_id' => $item['item_id'],
                    'name' => $item['name'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'amount' => $item['amount']
                ]);
            }

            return response()->json([
                'data' => $cart->load(['customerType', 'customer', 'items']),
                'message' => 'Cart created successfully'
            ], 201);
        });
    }

    /**
     * Update the specified cart in storage.
     */
    public function update(Request $request, $id)
    {
        $cart = Cart::find($id);
        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_type_id' => 'required|exists:customer_types,id',
            'customer_id' => 'nullable|exists:customers,id',
            'status' => 'nullable|in:in-cart,pending,completed,cancelled',
            'date' => 'nullable|date',
            'items' => 'nullable|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.unit' => 'required|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request, $cart) {
            $cart->update([
                'customer_type_id' => $request->customer_type_id,
                'customer_id' => $request->customer_id,
                'status' => $request->status ?? $cart->status,
                'date' => $request->date ?? $cart->date
            ]);

            if ($request->has('items')) {
                $cart->items()->delete();
                $total_amount = collect($request->items)->sum('amount');

                $cart->update(['total_amount' => $total_amount]);

                foreach ($request->items as $item) {
                    CartItem::create([
                        'cart_id' => $cart->id,
                        'item_id' => $item['item_id'],
                        'name' => $item['name'],
                        'unit' => $item['unit'],
                        'quantity' => $item['quantity'],
                        'amount' => $item['amount']
                    ]);
                }
            }

            return response()->json([
                'data' => $cart->load(['customerType', 'customer', 'items']),
                'message' => 'Cart updated successfully'
            ], 200);
        });
    }

    /**
     * Remove the specified cart from storage.
     */
    public function destroy($id)
    {
        $cart = Cart::find($id);
        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found'
            ], 404);
        }

        try {
            $cart->delete();
            return response()->json([
                'message' => 'Cart deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete cart due to existing references'
            ], 422);
        }
    }
}